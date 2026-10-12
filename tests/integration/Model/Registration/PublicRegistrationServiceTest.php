<?php

declare(strict_types=1);

namespace App\Tests\Integration\Model\Registration;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventService;
use App\Model\Event\EventState;
use App\Model\Event\PublicRegistration;
use App\Model\Registration\DuplicateRegistrationException;
use App\Model\Registration\MemberFingerprints;
use App\Model\Registration\PairingHint;
use App\Model\Registration\PublicMatch;
use App\Model\Registration\PublicRegistrationService;
use App\Model\Registration\RegistrationAnswer;
use App\Model\Registration\RegistrationRepository;
use App\Model\Registration\RegistrationService;
use App\Model\Registration\RegistrationState;
use App\Model\Registration\Sex;
use App\Model\Task\TaskState;
use App\Tests\Support\IntegrationTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

/**
 * Public sign-ups against the fixture dataset: unit 1003 has Anna Malá (101, 2015-03-12) and Karel Bezmailu (105).
 */
final class PublicRegistrationServiceTest extends Unit
{
    protected IntegrationTester $tester;

    private EventService $events;

    private PublicRegistrationService $public;

    private RegistrationService $service;

    private RegistrationRepository $registrations;

    private MemberFingerprints $fingerprints;

    protected function _before(): void
    {
        $entityManager = $this->tester->grabService(EntityManagerInterface::class);
        $entityManager->createQuery('DELETE FROM '.Event::class)->execute();

        $this->events = $this->tester->grabService(EventService::class);
        $this->public = $this->tester->grabService(PublicRegistrationService::class);
        $this->service = $this->tester->grabService(RegistrationService::class);
        $this->registrations = $this->tester->grabService(RegistrationRepository::class);
        $this->fingerprints = $this->tester->grabService(MemberFingerprints::class);
        $this->fingerprints->refresh(1003);
        // The per-IP limit lives in the cache, which outlives the test database.
        (new Cache($this->tester->grabService(Storage::class), 'registration.public'))->clean([Cache::All => true]);
    }

    public function testMemberIsPairedByNameAndBirthDate(): void
    {
        $event = $this->openEvent();

        $match = $this->public->match($event, 'anna', 'MALÁ', new DateTimeImmutable('2015-03-12'));
        self::assertTrue($match->isMember());
        self::assertSame(101, $match->member?->id);
        $anna = $this->public->register($event, $this->answer('anna', 'MALÁ', '2015-03-12', 'rodic@example.cz'), $match, '10.0.0.1');
        self::assertSame(101, $anna->getSkautisPersonId());
        self::assertSame('Malá Anna', $anna->getDisplayName());
        self::assertSame(RegistrationState::Registered, $anna->getState());
        self::assertNull($anna->getPairingHint());

        try {
            $this->public->match($event, 'Anna', 'Malá', new DateTimeImmutable('2015-03-12'));
            self::fail('The same member must not sign up twice.');
        } catch (DuplicateRegistrationException) {
        }

        $typoMatch = $this->public->match($event, 'Anka', 'Malá', new DateTimeImmutable('2015-03-12'));
        self::assertFalse($typoMatch->isMember());
        $typo = $this->public->register($event, $this->answer('Anka', 'Malá', '2015-03-12', 'jiny@example.cz'), $typoMatch, '10.0.0.1');
        self::assertNull($typo->getSkautisPersonId());
        self::assertSame(PairingHint::Possible, $typo->getPairingHint(), 'surname and birth date match, first name does not');

        $strangerMatch = $this->public->match($event, 'Cizí', 'Člověk', new DateTimeImmutable('2010-01-01'));
        self::assertFalse($strangerMatch->isMember(), 'no match: a non-member, nothing to confirm');
        $stranger = $this->public->register($event, $this->answer('Cizí', 'Člověk', '2010-01-01', 'cizi@example.cz'), $strangerMatch, '10.0.0.1');
        self::assertNull($stranger->getPairingHint());

        $guest = $this->public->register($event, $this->answer('Host', 'Bez', '2011-05-05', 'host@example.cz'), $this->public->match($event, 'Host', 'Bez', new DateTimeImmutable('2011-05-05')), '10.0.0.1');
        self::assertNull($guest->getPairingHint());
        self::assertSame(4, $this->registrations->countRegistered($event));

        // Leader confirms the typo case; Karel (105) is offered by birth date, Anna is already taken.
        $suggested = array_map(static fn ($person): int => $person->id, $this->service->suggestPairs($typo));
        self::assertNotContains(101, $suggested);
        $this->service->pair($guest, 105, 1);
        self::assertSame(105, $guest->getSkautisPersonId());
        self::assertSame('Bezmailu Karel', $guest->getDisplayName());
    }

    public function testPairingWorksWithoutTheMemberCacheAndWithoutSkautis(): void
    {
        $event = $this->openEvent();
        // The public page has no skautIS token: once the leader's member cache expired, the person is built
        // from what the parent typed (the fingerprint proved it), never fetched from skautIS.
        (new Cache($this->tester->grabService(Storage::class), 'skautis.members'))->clean([Cache::All => true]);

        $match = $this->public->match($event, 'anna', 'malá', new DateTimeImmutable('2015-03-12'));
        self::assertSame(101, $match->member?->id);
        self::assertSame('Malá Anna', $match->member->getDisplayName(), 'lower-case input is title-cased');
        self::assertNull($match->member->sex, 'nothing beyond the typed data without skautIS');
        $anna = $this->public->register($event, $this->answer('anna', 'malá', '2015-03-12', 'rodic@example.cz'), $match, '10.0.0.2');
        self::assertSame(101, $anna->getSkautisPersonId());
        self::assertTrue($anna->isMember());
    }

    public function testPublicSignUpQueuesTheConfirmationAndMatchingIsRateLimited(): void
    {
        $event = $this->openEvent();
        $match = $this->public->match($event, 'Anna', 'Malá', new DateTimeImmutable('2015-03-12'), '10.0.0.9');
        $this->public->register($event, $this->answer('Anna', 'Malá', '2015-03-12', 'rodic@example.cz'), $match, '10.0.0.9');
        $tasks = $this->tester->grabService(\App\Model\Task\TaskRepository::class);
        \assert($tasks instanceof \App\Model\Task\TaskRepository);
        self::assertSame(['confirmation'], array_map(static fn (\App\Model\Task\Task $task): string => $task->getPayloadString('type'), $tasks->findAutomaticByEvent($event, TaskState::Queued)));

        // Step one says whether a name and birth date belong to a member, so guessing is limited per address.
        for ($i = 0; $i < PublicRegistrationService::MAX_MATCHES_PER_IP_PER_HOUR; ++$i) {
            $this->public->match($event, 'Někdo', 'Jiný', new DateTimeImmutable('2010-01-01'), '10.0.0.10');
        }
        $this->expectException(\App\Model\Registration\TooManyRegistrationsException::class);
        $this->public->match($event, 'Někdo', 'Jiný', new DateTimeImmutable('2010-01-01'), '10.0.0.10');
    }

    public function testRejectedPersonCannotComeBackAndDisappearsFromTheList(): void
    {
        $event = $this->openEvent();
        $guest = $this->public->register($event, $this->answer('Host', 'Nezvaný', '2011-05-05', 'host@example.cz'), $this->public->match($event, 'Host', 'Nezvaný', new DateTimeImmutable('2011-05-05')), '10.0.0.2');
        $member = $this->public->register($event, $this->answer('Anna', 'Malá', '2015-03-12', 'rodic@example.cz'), $this->public->match($event, 'Anna', 'Malá', new DateTimeImmutable('2015-03-12')), '10.0.0.2');

        $this->service->reject($guest, 1, 'nepatří k nám');
        $this->service->reject($member, 1, null);

        self::assertCount(0, $this->registrations->findByEvent($event));
        self::assertCount(2, $this->registrations->findByEvent($event, RegistrationState::Rejected));
        self::assertSame(2, $this->registrations->countByState($event)[RegistrationState::Rejected->value]);
        self::assertNull($guest->getContactEmail());
        self::assertNotNull($guest->getEmailHash());

        try {
            $this->public->register($event, $this->answer('Host', 'Nezvaný', '2011-05-05', 'host@example.cz'), new PublicMatch(null, null), '10.0.0.2');
            self::fail('A rejected e-mail is blocked.');
        } catch (DuplicateRegistrationException) {
        }
        try {
            $this->public->match($event, 'Anna', 'Malá', new DateTimeImmutable('2015-03-12'));
            self::fail('A rejected member is blocked.');
        } catch (DuplicateRegistrationException) {
        }
    }

    private function answer(string $firstName, string $lastName, string $birthDate, string $email): RegistrationAnswer
    {
        return new RegistrationAnswer(
            attending: true,
            contactEmail: $email,
            firstName: $firstName,
            lastName: $lastName,
            sex: Sex::Female,
            birthDate: new DateTimeImmutable($birthDate),
            street: 'Ulice 1',
            city: 'Praha',
            postcode: '11000',
        );
    }

    private function openEvent(): Event
    {
        $event = $this->events->create(1003, new EventInput(
            name: 'Den otevřených dveří',
            skautisTypeId: '9',
            skautisScopeId: '2',
            dateFrom: new DateTimeImmutable('+30 days'),
            dateTo: new DateTimeImmutable('+30 days'),
            place: null,
            description: null,
            registrationDeadline: null,
            capacity: null,
            price: null,
            contactName: 'Jan Novák',
            contactEmail: 'jan@example.cz',
            contactPhone: null,
            publicRegistration: PublicRegistration::Open,
        ), 1);
        $this->events->changeState($event, EventState::Open);

        return $event;
    }
}
