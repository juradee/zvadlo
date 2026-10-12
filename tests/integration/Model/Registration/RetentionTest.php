<?php

declare(strict_types=1);

namespace App\Tests\Integration\Model\Registration;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventService;
use App\Model\Event\EventState;
use App\Model\Event\PublicRegistration;
use App\Model\Registration\LogActor;
use App\Model\Registration\MemberFingerprints;
use App\Model\Registration\Registration;
use App\Model\Registration\RegistrationAnswer;
use App\Model\Registration\RegistrationService;
use App\Model\Registration\RetentionService;
use App\Model\Registration\Sex;
use App\Model\Task\Task;
use App\Tests\Support\IntegrationTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Retention rules of spec ch. 14 with the configured periods (12 and 24 months in .env.ci).
 */
final class RetentionTest extends Unit
{
    protected IntegrationTester $tester;

    private EntityManagerInterface $entityManager;

    private EventService $events;

    private RegistrationService $registrations;

    protected function _before(): void
    {
        $this->entityManager = $this->tester->grabService(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM '.Task::class)->execute();
        $this->entityManager->createQuery('DELETE FROM '.Event::class)->execute();
        $this->events = $this->tester->grabService(EventService::class);
        $this->registrations = $this->tester->grabService(RegistrationService::class);
    }

    public function testAnswersGoFirstNonMembersFollowMembersStay(): void
    {
        $now = new DateTimeImmutable('2028-06-01 04:00');
        $lastYear = $this->openEvent();
        $member = $this->registrations->inviteMembers($lastYear, [101 => null], 1)[0];
        $this->registrations->respond($member, new RegistrationAnswer(attending: true, contactEmail: 'matka.mala@example.cz', note: 'bezlepková dieta'));
        $synced = $this->guest($lastYear, 'synced@example.cz', 'Pavel');
        $synced->recordSkautisParticipant(7001, 9001, LogActor::System, null, new DateTimeImmutable('2027-05-01'));
        $unsynced = $this->guest($lastYear, 'unsynced@example.cz', 'Petra');
        $this->finish($lastYear, '2027-05-10');

        $twoYearsAgo = $this->openEvent();
        $forgotten = $this->guest($twoYearsAgo, 'old@example.cz', 'Karel');
        $this->finish($twoYearsAgo, '2026-04-10');

        $retention = $this->tester->grabService(RetentionService::class);
        \assert($retention instanceof RetentionService);

        $preview = $retention->run($now, dryRun: true);
        self::assertSame(2, $preview->eventsForgotten);
        self::assertSame(1, $preview->anonymizedSynced);
        self::assertSame(1, $preview->anonymizedUnsynced);
        self::assertSame('bezlepková dieta', $this->reload($member)->getNote(), 'a dry run changes nothing');

        $result = $retention->run($now);
        self::assertSame(2, $result->eventsForgotten);
        self::assertSame(4, $result->answersForgotten);
        self::assertSame(1, $result->anonymizedSynced);
        self::assertSame(1, $result->anonymizedUnsynced);

        $this->entityManager->clear();
        $member = $this->reload($member);
        self::assertStringContainsString('Malá', $member->getDisplayName(), 'members keep their skautIS identity');
        self::assertNull($member->getNote());
        self::assertNull($member->getAnonymizedAt());

        $synced = $this->reload($synced);
        self::assertNotNull($synced->getAnonymizedAt(), 'the person lives in skautIS now');
        self::assertSame('anonymizovaná přihláška', $synced->getDisplayName());
        self::assertSame(9001, $synced->getSkautisPersonId(), 'the reference to skautIS stays');
        self::assertTrue($synced->wasPersonCreatedInSkautis());

        $unsynced = $this->reload($unsynced);
        self::assertNull($unsynced->getAnonymizedAt(), 'never written to skautIS: waits for the longer period');
        self::assertSame('Host Petra', $unsynced->getDisplayName());
        self::assertNull($unsynced->getNote(), 'but the answers are gone');

        self::assertNotNull($this->reload($forgotten)->getAnonymizedAt());
        self::assertNotNull($this->entityManager->find(Event::class, $lastYear->getId())?->getAnswersForgottenAt());

        self::assertSame(0, $retention->run($now)->eventsForgotten, 'the second run has nothing left to do');
    }

    public function testFingerprintsOfUnitsWithoutPublicEventsAreDeleted(): void
    {
        $fingerprints = $this->tester->grabService(MemberFingerprints::class);
        \assert($fingerprints instanceof MemberFingerprints);
        $retention = $this->tester->grabService(RetentionService::class);
        \assert($retention instanceof RetentionService);
        $now = new DateTimeImmutable();

        $fingerprints->refresh(1003);
        self::assertTrue($fingerprints->hasFingerprints(1003));

        $event = $this->events->create(1003, $this->input('+10 days', PublicRegistration::Open), 1);
        self::assertSame(0, $retention->run($now)->fingerprintUnits, 'an event open to the public keeps them');
        self::assertTrue($fingerprints->hasFingerprints(1003));

        $this->events->changeState($event, EventState::Open);
        $this->events->changeState($event, EventState::Closed);
        self::assertSame(1, $retention->run($now)->fingerprintUnits);
        self::assertFalse($fingerprints->hasFingerprints(1003));
    }

    /**
     * Events are created in the future so that answers can be filled in, and dated back afterwards.
     */
    private function openEvent(): Event
    {
        $event = $this->events->create(1003, $this->input('+30 days'), 1);
        $this->events->changeState($event, EventState::Open);

        return $event;
    }

    private function finish(Event $event, string $date): void
    {
        $this->events->update($event, $this->input($date));
        $this->events->changeState($event, EventState::Closed);
        $this->events->changeState($event, EventState::Archived);
    }

    private function guest(Event $event, string $email, string $firstName): Registration
    {
        $guest = $this->registrations->inviteNonMember($event, $email, null, null, 1);
        $this->registrations->respond($guest, new RegistrationAnswer(attending: true, contactEmail: $email, note: 'poznámka', firstName: $firstName, lastName: 'Host', sex: Sex::Female, birthDate: new DateTimeImmutable('2012-02-02'), street: 'A 1', city: 'Brno', postcode: '60200'));

        return $guest;
    }

    private function input(string $date, PublicRegistration $public = PublicRegistration::None): EventInput
    {
        return new EventInput(
            name: 'Výprava',
            skautisTypeId: '2',
            skautisScopeId: '2',
            dateFrom: new DateTimeImmutable($date),
            dateTo: new DateTimeImmutable($date),
            place: 'Louka',
            description: null,
            registrationDeadline: null,
            capacity: null,
            price: null,
            contactName: 'Jan Novák',
            contactEmail: 'jan@example.cz',
            contactPhone: null,
            publicRegistration: $public,
        );
    }

    private function reload(Registration $registration): Registration
    {
        return $this->entityManager->find(Registration::class, $registration->getId()) ?? self::fail('registration vanished');
    }
}
