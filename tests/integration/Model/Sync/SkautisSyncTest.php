<?php

declare(strict_types=1);

namespace App\Tests\Integration\Model\Sync;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventService;
use App\Model\Event\EventState;
use App\Model\Registration\Registration;
use App\Model\Registration\RegistrationAnswer;
use App\Model\Registration\RegistrationService;
use App\Model\Registration\Sex;
use App\Model\Skautis\Dto\Camp;
use App\Model\Sync\SkautisSync;
use App\Model\Sync\SyncNotAllowedException;
use App\Tests\Support\IntegrationTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Against the fixture skautIS: event 5001 already lists Petr (102, participant 7002) and a stranger (555),
 * every insert answers participant 7001 (new person 9001), camp 5001 is approved and camp 5003 is a draft.
 */
final class SkautisSyncTest extends Unit
{
    protected IntegrationTester $tester;

    private EventService $events;

    private RegistrationService $registrations;

    private SkautisSync $sync;

    protected function _before(): void
    {
        $this->tester->grabService(EntityManagerInterface::class)->createQuery('DELETE FROM '.Event::class)->execute();
        $this->events = $this->tester->grabService(EventService::class);
        $this->registrations = $this->tester->grabService(RegistrationService::class);
        $this->sync = $this->tester->grabService(SkautisSync::class);
    }

    public function testEventIsCreatedAndParticipantsWrittenInBatches(): void
    {
        $event = $this->openEvent();
        self::assertSame('Akce ještě není ve skautISu založená.', $this->sync->blocker($event));

        self::assertSame(5001, $this->sync->createEvent($event));
        self::assertSame(5001, $event->getSkautisEventId());
        self::assertNull($this->sync->blocker($event));
        try {
            $this->sync->createEvent($event);
            self::fail('An event is created once.');
        } catch (SyncNotAllowedException) {
        }

        [$anna, $petr] = $this->registrations->inviteMembers($event, [101 => null, 102 => null], 1);
        $karel = $this->registrations->inviteMembers($event, [105 => null], 1)[0];
        $guest = $this->registrations->inviteNonMember($event, 'host@example.cz', null, null, 1);
        foreach ([$anna, $petr] as $registration) {
            $this->registrations->respond($registration, new RegistrationAnswer(attending: true, contactEmail: 'rodic@example.cz'));
        }
        $this->registrations->respond($guest, new RegistrationAnswer(attending: true, contactEmail: 'host@example.cz', firstName: 'Pavel', lastName: 'Host', sex: Sex::Male, birthDate: new DateTimeImmutable('2012-02-02'), street: 'A 1', city: 'Brno', postcode: '60200'));

        $diff = $this->sync->diff($event);
        self::assertSame([$anna, $guest], $this->sorted($diff->missing));
        self::assertSame([$petr], $diff->synced, 'Petr is in skautIS already although Zvadlo never wrote him');
        self::assertSame([], $diff->toRemove);
        self::assertSame([555], array_map(static fn ($p): ?int => $p->personId, $diff->foreign), 'only the stranger is unknown to Zvadlo');
        self::assertFalse($diff->isInSync());

        self::assertSame(['remaining' => 1, 'next' => 0], $this->sync->syncBatch($event, 1, batchSize: 1));
        self::assertSame(['remaining' => 0, 'next' => 0], $this->sync->syncBatch($event, 1, batchSize: 1));

        self::assertSame(7001, $anna->getSkautisParticipantId());
        self::assertSame(101, $anna->getSkautisPersonId());
        self::assertSame(7001, $guest->getSkautisParticipantId());
        self::assertSame(9001, $guest->getSkautisPersonId(), 'the created person is remembered');
        self::assertNull($karel->getSkautisParticipantId(), 'not attending, not written');

        $diff = $this->sync->diff($event);
        self::assertTrue($diff->isInSync());
        self::assertCount(3, $diff->synced);

        $this->registrations->cancelByLeader($anna, 1);
        $diff = $this->sync->diff($event);
        self::assertSame([$anna], $diff->toRemove);
        self::assertSame(['remaining' => 0, 'next' => 0], $this->sync->removeBatch($event, 1));
        self::assertNull($anna->getSkautisParticipantId());
        self::assertTrue($this->sync->diff($event)->isInSync());
        self::assertStringContainsString('odebrána ze skautISu', $anna->getLogs()[\count($anna->getLogs()) - 1]->getMessage());
    }

    public function testCampParticipantsWaitUntilTheCampLeavesTheDraftState(): void
    {
        $approved = $this->events->createFromCamp($this->camp(5001), $this->input(), 1);
        $draft = $this->events->createFromCamp($this->camp(5003), $this->input(), 1);
        $this->events->changeState($approved, EventState::Open);
        $this->events->changeState($draft, EventState::Open);

        self::assertNull($this->sync->blocker($approved));
        self::assertStringContainsString('rozpracovaný', (string) $this->sync->blocker($draft));

        $anna = $this->registrations->inviteMembers($approved, [101 => null], 1)[0];
        $this->registrations->respond($anna, new RegistrationAnswer(attending: true, contactEmail: 'rodic@example.cz'));
        self::assertSame(['remaining' => 0, 'next' => 0], $this->sync->syncBatch($approved, 1));
        self::assertSame(7101, $anna->getSkautisParticipantId());

        $this->expectException(SyncNotAllowedException::class);
        $this->sync->createEvent($approved);
    }

    public function testFailingRowsAreTriedOnceAndDoNotBlockTheRest(): void
    {
        $event = $this->openEvent();
        $this->sync->createEvent($event);
        // The fixture answers ParticipantGeneralInsert for Karel (105) with nothing, a permanent failure.
        [$karel, $anna] = $this->registrations->inviteMembers($event, [105 => null, 101 => null], 1);
        foreach ([$karel, $anna] as $registration) {
            $this->registrations->respond($registration, new RegistrationAnswer(attending: true, contactEmail: 'rodic@example.cz'));
        }

        $first = $this->sync->syncBatch($event, 1, batchSize: 1);
        self::assertSame(['remaining' => 1, 'next' => 1], $first, 'Karel failed: the next batch starts after him');
        self::assertNotNull($karel->getSkautisError());

        self::assertSame(['remaining' => 0, 'next' => 1], $this->sync->syncBatch($event, 1, $first['next'], batchSize: 1));
        self::assertSame(7001, $anna->getSkautisParticipantId(), 'the row after the failing one was written');
        self::assertNull($karel->getSkautisParticipantId());

        $diff = $this->sync->diff($event);
        self::assertSame([$karel], $diff->missing);
        self::assertSame([$karel], $diff->failed, 'the page shows the failure after the run');
    }

    /**
     * @param list<Registration> $registrations
     *
     * @return list<Registration>
     */
    private function sorted(array $registrations): array
    {
        usort($registrations, static fn (Registration $a, Registration $b): int => $a->getId() <=> $b->getId());

        return $registrations;
    }

    private function camp(int $id): Camp
    {
        return new Camp($id, 'Tábor '.$id, 1003, 'Oddíl', new DateTimeImmutable('2027-07-03'), new DateTimeImmutable('2027-07-17'), 'Louka', 'unknown', 'Neznámý');
    }

    private function input(): EventInput
    {
        return new EventInput(
            name: 'Podzimní výprava',
            skautisTypeId: '2',
            skautisScopeId: '2',
            dateFrom: new DateTimeImmutable('+30 days'),
            dateTo: new DateTimeImmutable('+31 days'),
            place: 'Louka',
            description: null,
            registrationDeadline: null,
            capacity: null,
            price: null,
            contactName: 'Jan Novák',
            contactEmail: 'jan@example.cz',
            contactPhone: null,
        );
    }

    private function openEvent(): Event
    {
        $event = $this->events->create(1003, $this->input(), 1);
        $this->events->changeState($event, EventState::Open);

        return $event;
    }
}
