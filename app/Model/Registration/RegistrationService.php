<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Event\Event;
use App\Model\Event\EventFieldOption;
use App\Model\Event\EventFieldType;
use App\Model\Mailing\AutomaticMails;
use App\Model\Security\Crypto;
use App\Model\Security\Fingerprint;
use App\Model\Skautis\Dto\Person;
use App\Model\Skautis\MemberDirectory;
use DateTimeImmutable;

/**
 * Use cases around invitations and registrations: what leaders do in the administration and what
 * participants do through their personal link.
 */
final class RegistrationService
{
    public function __construct(
        private readonly RegistrationRepository $registrations,
        private readonly MemberDirectory $members,
        private readonly MemberAddresses $addresses,
        private readonly Crypto $crypto,
        private readonly Fingerprint $fingerprint,
        private readonly AutomaticMails $mails,
    ) {
    }

    /**
     * Invites members of the event's unit tree with the addresses the leader chose for each (person id => e-mails;
     * null keeps the default choice). Every address skautIS knows is kept, so the leader can switch them later;
     * a chosen address skautIS does not know is ignored. Persons already in the event are skipped.
     *
     * @param array<int, list<string>|null> $chosen
     *
     * @return list<Registration>
     */
    public function inviteMembers(Event $event, array $chosen, int $userId): array
    {
        $invited = $this->registrations->findInvitedPersonIds($event);
        $now = new DateTimeImmutable();
        $created = [];

        foreach ($this->members->getMembers($event->getUnitId()) as $person) {
            if (! \array_key_exists($person->id, $chosen) || \in_array($person->id, $invited, true)) {
                continue;
            }

            $registration = Registration::inviteMember($event, $person, RegistrationToken::generate($this->crypto), $userId, $now);
            $contacts = $this->addresses->find($person->id, $person->birthday, $now);
            $emails = $chosen[$person->id] === null ? null : array_map(static fn (string $email): string => mb_strtolower(trim($email)), $chosen[$person->id]);
            foreach ($contacts->addresses as $address) {
                $registration->addRecipient($address->email, $address->name, $address->source, $emails === null ? $address->selected : \in_array($address->email, $emails, true));
            }
            $registration->setPhoneIfEmpty($contacts->phone);
            $this->registrations->add($registration);
            $created[] = $registration;
        }

        return $created;
    }

    public function inviteNonMember(Event $event, string $email, ?string $firstName, ?string $lastName, int $userId): Registration
    {
        $registration = Registration::inviteNonMember($event, $email, $firstName, $lastName, RegistrationToken::generate($this->crypto), $userId, new DateTimeImmutable());
        $registration->recordEmailHash($this->fingerprint->email($email));
        $this->registrations->add($registration);

        return $registration;
    }

    public function addRecipient(Registration $registration, string $email, ?string $name, int $userId): bool
    {
        $added = $registration->addRecipient($email, $name, RecipientSource::Manual);
        if ($added) {
            $registration->log(LogActor::User, $userId, 'Přidán e-mail '.mb_strtolower(trim($email)).'.', null, new DateTimeImmutable());
            $this->registrations->save();
        }

        return $added;
    }

    public function removeRecipient(Registration $registration, RegistrationRecipient $recipient, int $userId): void
    {
        $registration->removeRecipient($recipient);
        $registration->log(LogActor::User, $userId, 'Odebrán e-mail '.$recipient->getEmail().'.', null, new DateTimeImmutable());
        $this->registrations->save();
    }

    /**
     * The leader's edit of one row of the list: which addresses get the mail, optionally one more by hand.
     *
     * @param list<int> $selectedIds recipient ids
     *
     * @return bool whether the extra address was new
     */
    public function updateRecipients(Registration $registration, array $selectedIds, ?string $newEmail, int $userId): bool
    {
        $before = $registration->getSelectedRecipientEmails();
        foreach ($registration->getRecipients() as $recipient) {
            $recipient->setSelected(\in_array($recipient->getId(), $selectedIds, true));
        }
        $added = $newEmail !== null && $registration->addRecipient($newEmail, null, RecipientSource::Manual);
        $after = $registration->getSelectedRecipientEmails();
        if ($before !== $after) {
            $registration->log(LogActor::User, $userId, 'Pošta chodí na: '.($after === [] ? 'žádnou adresu' : implode(', ', $after)).'.', null, new DateTimeImmutable());
        }
        $this->registrations->save();

        return $added;
    }

    /**
     * The personal link token in the clear, for showing to the leader or putting into an e-mail.
     */
    public function getToken(Registration $registration): string
    {
        return $this->crypto->decrypt($registration->getTokenEncrypted());
    }

    /**
     * Only when the link leaked: the old link stops working.
     */
    public function replaceToken(Registration $registration, int $userId): string
    {
        $token = RegistrationToken::generate($this->crypto);
        $registration->replaceToken($token, $userId, new DateTimeImmutable());
        $this->registrations->save();

        return $this->crypto->decrypt($token->encrypted);
    }

    public function findByToken(string $token): ?Registration
    {
        return $this->registrations->findByTokenHash(RegistrationToken::hash($token));
    }

    public function verifyBirthDate(Registration $registration, DateTimeImmutable $birthDate): bool
    {
        $verified = $registration->verifyBirthDate($birthDate, new DateTimeImmutable());
        $this->registrations->save();

        return $verified;
    }

    public function verifyEmail(Registration $registration, string $email): bool
    {
        $verified = $registration->verifyEmail($email, new DateTimeImmutable());
        $this->registrations->save();

        return $verified;
    }

    public function markSeen(Registration $registration): void
    {
        $registration->markSeen(new DateTimeImmutable());
        $this->registrations->save();
    }

    /**
     * @throws RegistrationNotAllowedException
     * @throws EventFullException              when the last place was taken by an earlier submit and there is no waiting list
     */
    public function respond(Registration $registration, RegistrationAnswer $answer): void
    {
        $now = new DateTimeImmutable();
        if (! $registration->canBeChangedByParticipant($now)) {
            throw new RegistrationNotAllowedException('Přihlášku teď nelze měnit.');
        }

        $previous = $registration->getState();
        $event = $registration->getEvent();
        // The submit decides: the free places are counted under the event lock, never while filling in.
        $this->registrations->underCapacityLock($event, function () use ($registration, $answer, $previous, $event, $now): void {
            if ($answer->attending && ! $previous->isAttending()) {
                $this->assertCanTakeNewAttendee($event);
            }
            $registration->respond($answer, $this->hasFreeCapacity($event), $now);
            $registration->recordEmailHash($answer->contactEmail === null ? $registration->getEmailHash() : $this->fingerprint->email($answer->contactEmail));
            $this->applyFieldValues($registration, $answer);
        });
        $this->mails->afterAnswer($registration, $previous);
        if ($previous === RegistrationState::Registered && $registration->getState() !== RegistrationState::Registered) {
            $this->fillFreedPlace($registration->getEvent());
        }
    }

    /**
     * @throws EventFullException when the event is full and has no waiting list
     */
    public function assertCanTakeNewAttendee(Event $event): void
    {
        if (! $event->isWaitlistEnabled() && ! $this->hasFreeCapacity($event)) {
            throw new EventFullException('Akce je už plně obsazená, další přihlášky nepřijímá. Napište prosím vedoucímu.');
        }
    }

    /**
     * An attendee cancelled themselves: the first on the waiting list takes the place and gets an e-mail.
     * (A leader's cancellation leaves the place free and the leader decides, see promote().).
     */
    private function fillFreedPlace(Event $event): void
    {
        $promoted = $this->registrations->underCapacityLock($event, function () use ($event): ?Registration {
            $next = $this->registrations->findWaitlisted($event)[0] ?? null;
            if ($next === null || ! $this->hasFreeCapacity($event)) {
                return null;
            }
            $next->promote(LogActor::System, null, false, new DateTimeImmutable());

            return $next;
        });
        if ($promoted !== null) {
            $this->mails->afterPromotion($promoted);
        }
    }

    /**
     * @throws RegistrationNotAllowedException
     */
    public function cancelByParticipant(Registration $registration): void
    {
        $now = new DateTimeImmutable();
        if (! $registration->canBeCancelledByParticipant($now)) {
            throw new RegistrationNotAllowedException('Účast teď nelze zrušit.');
        }

        $wasRegistered = $registration->getState() === RegistrationState::Registered;
        $registration->cancel(LogActor::Participant, null, $now);
        $this->registrations->save();
        $this->mails->afterChangeByLeader($registration);
        if ($wasRegistered) {
            $this->fillFreedPlace($registration->getEvent());
        }
    }

    public function cancelByLeader(Registration $registration, int $userId): void
    {
        $registration->cancel(LogActor::User, $userId, new DateTimeImmutable());
        $this->registrations->save();
        $this->mails->afterChangeByLeader($registration);
    }

    /**
     * The leader accepts someone from the waiting list; over the capacity only with an explicit confirmation.
     *
     * @throws EventFullException when the event is full and the leader did not confirm the overbooking
     */
    public function promote(Registration $registration, int $userId, bool $overbook = false): void
    {
        $event = $registration->getEvent();
        $this->registrations->underCapacityLock($event, function () use ($registration, $event, $userId, $overbook): void {
            $full = ! $this->hasFreeCapacity($event);
            if ($full && ! $overbook) {
                throw new EventFullException('Akce je plně obsazená. Náhradníka lze přijmout jen nad kapacitu, potvrďte to tlačítkem „Přijmout nad kapacitu“.');
            }
            $registration->promote(LogActor::User, $userId, $full, new DateTimeImmutable());
        });
        $this->mails->afterPromotion($registration);
    }

    /**
     * Free places and the waiting list, for the leader's hint after a place was freed.
     *
     * @return array{free: int|null, waitlisted: list<Registration>}
     */
    public function placesAndWaitlist(Event $event): array
    {
        $capacity = $event->getCapacity();

        return [
            'free' => $capacity === null ? null : max(0, $capacity - $this->registrations->countRegistered($event)),
            'waitlisted' => $this->registrations->findWaitlisted($event),
        ];
    }

    public function markDeclined(Registration $registration, int $userId, ?string $reason): void
    {
        $registration->markDeclinedByLeader($userId, $reason, new DateTimeImmutable());
        $this->registrations->save();
    }

    /**
     * Turns away someone who does not belong to the event; personal data are removed at once.
     */
    public function reject(Registration $registration, int $userId, ?string $reason): void
    {
        $this->mails->beforeReject($registration, $reason);
        $email = $registration->getContactEmail() ?? ($registration->getRecipientEmails()[0] ?? null);
        $registration->reject($userId, $reason, $email === null ? null : $this->fingerprint->email($email), new DateTimeImmutable());
        $this->registrations->save();
    }

    /**
     * Members of the unit tree who could be the person behind an unpaired public registration.
     *
     * @return list<Person>
     */
    public function suggestPairs(Registration $registration): array
    {
        $event = $registration->getEvent();
        $invited = $this->registrations->findInvitedPersonIds($event);
        $birthDate = $registration->getBirthDate()?->format('Y-m-d');
        $surname = Fingerprint::normalize((string) $registration->getLastName());

        return array_values(array_filter($this->members->getMembers($event->getUnitId()), static function (Person $person) use ($invited, $birthDate, $surname): bool {
            if (\in_array($person->id, $invited, true)) {
                return false;
            }
            if ($birthDate !== null && $person->birthday?->format('Y-m-d') === $birthDate) {
                return true;
            }

            return $surname !== '' && Fingerprint::normalize($person->lastName) === $surname;
        }));
    }

    /**
     * @throws DuplicateRegistrationException when the person already has a registration in the event
     */
    public function pair(Registration $registration, int $personId, int $userId): void
    {
        $event = $registration->getEvent();
        if ($this->registrations->findByPerson($event, $personId) !== null) {
            throw new DuplicateRegistrationException('Tato osoba už v akci přihlášku má.');
        }

        foreach ($this->members->getMembers($event->getUnitId()) as $person) {
            if ($person->id === $personId) {
                $registration->pairWithPerson($person, $userId, new DateTimeImmutable());
                $this->registrations->save();

                return;
            }
        }

        throw new DuplicateRegistrationException('Osoba nebyla v jednotce nalezena.');
    }

    public function hasFreeCapacity(Event $event): bool
    {
        return $event->getCapacity() === null || $this->registrations->countRegistered($event) < $event->getCapacity();
    }

    public function applyFieldValues(Registration $registration, RegistrationAnswer $answer): void
    {
        foreach ($registration->getEvent()->getVisibleFields() as $field) {
            if (! \array_key_exists($field->getId(), $answer->fieldValues)) {
                continue;
            }
            $value = $answer->fieldValues[$field->getId()];

            switch ($field->getType()) {
                case EventFieldType::Text:
                    $registration->setTextValue($field, \is_string($value) ? trim($value) : null);
                    break;
                case EventFieldType::Boolean:
                    $registration->setBooleanValue($field, (bool) $value);
                    break;
                case EventFieldType::Choice:
                case EventFieldType::MultiChoice:
                    $ids = \is_array($value) ? $value : ($value === null ? [] : [$value]);
                    $ids = array_map(intval(...), array_values(array_filter($ids, is_scalar(...))));
                    $options = array_values(array_filter(
                        $field->getOptions(),
                        static fn (EventFieldOption $option): bool => \in_array($option->getId(), $ids, true),
                    ));
                    $registration->setOptionValues($field, $options);
                    break;
            }
        }
    }
}
