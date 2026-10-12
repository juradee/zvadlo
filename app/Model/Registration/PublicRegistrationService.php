<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Event\Event;
use App\Model\Mailing\AutomaticMails;
use App\Model\Security\Crypto;
use App\Model\Security\Fingerprint;
use App\Model\Skautis\Dto\Person;
use DateTimeImmutable;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

/**
 * Sign-up through the public link: no invitation, no verification, but a duplicate check, a rate limit
 * and pairing with a member when the name and birth date match the unit's fingerprints.
 */
final class PublicRegistrationService
{
    public const int MAX_PER_IP_PER_HOUR = 10;

    /** Step one answers whether a name and birth date match a member, so it is rate-limited as well. */
    public const int MAX_MATCHES_PER_IP_PER_HOUR = 30;

    private readonly Cache $cache;

    public function __construct(
        private readonly RegistrationRepository $registrations,
        private readonly RegistrationService $service,
        private readonly MemberFingerprints $fingerprints,
        private readonly Fingerprint $fingerprint,
        private readonly Crypto $crypto,
        private readonly AutomaticMails $mails,
        Storage $storage,
    ) {
        $this->cache = new Cache($storage, 'registration.public');
    }

    /**
     * Step one of the public form: who the participant is. Exactly one member with this name and birth date
     * makes the participant a member (who then fills in far less); a surname and birth date match leaves
     * a hint for the leader; anything else is a non-member.
     *
     * @throws RegistrationNotAllowedException when the event does not accept public sign-ups now
     * @throws DuplicateRegistrationException  when the matched member is already in the event
     * @throws TooManyRegistrationsException   when one address tries too many names
     */
    public function match(Event $event, string $firstName, string $lastName, DateTimeImmutable $birthDate, string $ip = 'unknown'): PublicMatch
    {
        if (! $event->isPublicRegistrationOpen(new DateTimeImmutable())) {
            throw new RegistrationNotAllowedException('Přihlašování na tuto akci teď neběží.');
        }
        $this->countMatch($ip);

        $unitId = $event->getUnitId();
        $exact = $this->fingerprints->matchExact($unitId, $firstName, $lastName, $birthDate);
        if (\count($exact) === 1) {
            if ($this->registrations->findByPerson($event, $exact[0]) !== null) {
                throw new DuplicateRegistrationException('Přihlášku pro tuto osobu už evidujeme. Použijte odkaz z e-mailu, nebo napište vedoucímu.');
            }

            return new PublicMatch($this->fingerprints->findPerson($unitId, $exact[0], $firstName, $lastName, $birthDate), null);
        }

        // Two members with the same name and birth date, or only the surname matches: the leader decides.
        if (\count($exact) > 1 || $this->fingerprints->matchSurname($unitId, $firstName, $lastName, $birthDate) !== []) {
            return new PublicMatch(null, PairingHint::Possible);
        }

        return new PublicMatch(null, null);
    }

    /**
     * Step two: the registration itself.
     *
     * @throws RegistrationNotAllowedException when the event does not accept public sign-ups now
     * @throws TooManyRegistrationsException   when one address sends too many sign-ups
     * @throws DuplicateRegistrationException  when the person or e-mail is already in the event
     * @throws EventFullException              when the event is full and has no waiting list
     */
    public function register(Event $event, RegistrationAnswer $answer, PublicMatch $match, string $ip): Registration
    {
        $now = new DateTimeImmutable();
        if (! $event->isPublicRegistrationOpen($now)) {
            throw new RegistrationNotAllowedException('Přihlašování na tuto akci teď neběží.');
        }
        if ($answer->contactEmail === null || $answer->firstName === null || $answer->lastName === null || $answer->birthDate === null) {
            throw new RegistrationNotAllowedException('Chybí jméno, datum narození nebo e-mail.');
        }

        $this->checkRateLimit($ip);

        $emailHash = $this->fingerprint->email($answer->contactEmail);
        if ($this->registrations->existsWithEmailHash($event, $emailHash) && $this->sameNameExists($event, $answer)) {
            throw new DuplicateRegistrationException('Přihlášku pro tuto osobu už evidujeme. Použijte odkaz z e-mailu, nebo napište vedoucímu.');
        }
        if ($match->member !== null && $this->registrations->findByPerson($event, $match->member->id) !== null) {
            throw new DuplicateRegistrationException('Přihlášku pro tuto osobu už evidujeme. Použijte odkaz z e-mailu, nebo napište vedoucímu.');
        }

        // The submit decides about the place, counted under the event lock.
        $registration = $this->registrations->underCapacityLock($event, function () use ($event, $answer, $match, $emailHash, $now): Registration {
            $this->service->assertCanTakeNewAttendee($event);
            $registration = Registration::registerPublic(
                $event,
                $answer,
                RegistrationToken::generate($this->crypto),
                $match->member,
                $match->hint,
                $this->service->hasFreeCapacity($event),
                $now,
            );
            $registration->recordEmailHash($emailHash);
            $this->service->applyFieldValues($registration, $answer);
            $this->registrations->add($registration);

            return $registration;
        });
        $this->countRegistration($ip);
        // The confirmation carries the personal link: without it the family could never open the registration again.
        $this->mails->afterAnswer($registration, RegistrationState::Invited);

        return $registration;
    }

    /**
     * Sign-up of a person proven by a skautIS login (4.6): no fingerprints, no membership question.
     *
     * @throws RegistrationNotAllowedException when the event does not accept skautIS sign-ups now
     * @throws TooManyRegistrationsException   when one address sends too many sign-ups
     * @throws DuplicateRegistrationException  when the person is already in the event
     * @throws EventFullException              when the event is full and has no waiting list
     */
    public function registerViaSkautis(Event $event, RegistrationAnswer $answer, Person $person, string $ip): Registration
    {
        $now = new DateTimeImmutable();
        if (! $event->acceptsSkautisLogin($now)) {
            throw new RegistrationNotAllowedException('Přihlašování na tuto akci teď neběží.');
        }
        if ($answer->contactEmail === null) {
            throw new RegistrationNotAllowedException('Chybí kontaktní e-mail.');
        }
        if ($this->registrations->findByPerson($event, $person->id) !== null) {
            throw new DuplicateRegistrationException('Přihlášku pro tuto osobu už evidujeme. Přihlaste se přes skautIS znovu a otevřete ji.');
        }
        $emailHash = $this->fingerprint->email($answer->contactEmail);
        if ($this->isRejectedEmail($event, $emailHash)) {
            throw new DuplicateRegistrationException('Přihlášku pro tuto osobu už evidujeme. Použijte odkaz z e-mailu, nebo napište vedoucímu.');
        }

        $this->checkRateLimit($ip);

        $registration = $this->registrations->underCapacityLock($event, function () use ($event, $answer, $person, $emailHash, $now): Registration {
            $this->service->assertCanTakeNewAttendee($event);
            $registration = Registration::registerViaSkautis($event, $answer, RegistrationToken::generate($this->crypto), $person, $this->service->hasFreeCapacity($event), $now);
            $registration->recordEmailHash($emailHash);
            $this->service->applyFieldValues($registration, $answer);
            $this->registrations->add($registration);

            return $registration;
        });
        $this->countRegistration($ip);
        $this->mails->afterAnswer($registration, RegistrationState::Invited);

        return $registration;
    }

    public function findMember(Event $event, int $personId, string $firstName, string $lastName, DateTimeImmutable $birthDate): Person
    {
        return $this->fingerprints->findPerson($event->getUnitId(), $personId, $firstName, $lastName, $birthDate);
    }

    private function sameNameExists(Event $event, RegistrationAnswer $answer): bool
    {
        $first = Fingerprint::normalize((string) $answer->firstName);
        $last = Fingerprint::normalize((string) $answer->lastName);
        $hash = $this->fingerprint->email((string) $answer->contactEmail);

        foreach ($this->registrations->findByEvent($event) as $registration) {
            if ($registration->getEmailHash() !== $hash) {
                continue;
            }
            if (Fingerprint::normalize((string) $registration->getFirstName()) === $first && Fingerprint::normalize((string) $registration->getLastName()) === $last) {
                return true;
            }
        }

        return $this->isRejectedEmail($event, $hash);
    }

    /**
     * A rejected person is blocked by the e-mail alone; the name is gone with the anonymisation.
     */
    private function isRejectedEmail(Event $event, string $emailHash): bool
    {
        foreach ($this->registrations->findByEvent($event, RegistrationState::Rejected) as $registration) {
            if ($registration->getEmailHash() === $emailHash) {
                return true;
            }
        }

        return false;
    }

    private function countMatch(string $ip): void
    {
        $count = $this->cache->load('match-'.$ip);
        $count = \is_int($count) ? $count : 0;
        if ($count >= self::MAX_MATCHES_PER_IP_PER_HOUR) {
            throw new TooManyRegistrationsException('Z této adresy přišlo příliš mnoho pokusů. Zkuste to za hodinu.');
        }
        $this->cache->save('match-'.$ip, $count + 1, [Cache::Expire => '1 hour']);
    }

    private function checkRateLimit(string $ip): void
    {
        $count = $this->cache->load('ip-'.$ip);
        if (\is_int($count) && $count >= self::MAX_PER_IP_PER_HOUR) {
            throw new TooManyRegistrationsException('Z této adresy přišlo příliš mnoho přihlášek. Zkuste to za hodinu.');
        }
    }

    private function countRegistration(string $ip): void
    {
        $count = $this->cache->load('ip-'.$ip);
        $this->cache->save('ip-'.$ip, (\is_int($count) ? $count : 0) + 1, [Cache::Expire => '1 hour']);
    }
}
