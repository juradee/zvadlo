<?php

declare(strict_types=1);

namespace App\Tests\Integration\Model\Registration;

use App\Model\Event\Event;
use App\Model\Event\EventFieldInput;
use App\Model\Event\EventFieldType;
use App\Model\Event\EventInput;
use App\Model\Event\EventService;
use App\Model\Event\EventState;
use App\Model\Registration\EventFullException;
use App\Model\Registration\FieldInUseException;
use App\Model\Registration\Registration;
use App\Model\Registration\RegistrationAnswer;
use App\Model\Registration\RegistrationRepository;
use App\Model\Registration\RegistrationService;
use App\Model\Registration\RegistrationState;
use App\Tests\Support\IntegrationTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Runs against the skautIS fixture dataset: unit 1003 has Anna (101, parents), Petr (102, mother)
 * and Karel (105, no contacts at all).
 */
final class RegistrationServiceTest extends Unit
{
    protected IntegrationTester $tester;

    private EntityManagerInterface $entityManager;

    private EventService $events;

    private RegistrationService $service;

    private RegistrationRepository $registrations;

    protected function _before(): void
    {
        $this->entityManager = $this->tester->grabService(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM '.Event::class)->execute();

        $this->events = $this->tester->grabService(EventService::class);
        $this->service = $this->tester->grabService(RegistrationService::class);
        $this->registrations = $this->tester->grabService(RegistrationRepository::class);
    }

    public function testInvitationsKeepTheAddressesChosenForEachMember(): void
    {
        $event = $this->openEvent();

        $created = $this->service->inviteMembers($event, [101 => null, 102 => null, 105 => null, 999 => null], 1);
        self::assertCount(3, $created);
        self::assertCount(0, $this->service->inviteMembers($event, [101 => null], 1), 'already invited');
        $guest = $this->service->inviteNonMember($event, 'Pavel@Example.cz', null, null, 1);

        $byPerson = [];
        foreach ($this->registrations->findByEvent($event) as $registration) {
            $byPerson[$registration->getSkautisPersonId() ?? 0] = $registration;
        }

        self::assertSame(['matka.mala@example.cz', 'otec.maly@example.cz'], $byPerson[101]->getSelectedRecipientEmails(), 'without a choice a child gets the parents');
        self::assertSame(['matka.mala@example.cz', 'otec.maly@example.cz', 'anna.mala@example.cz'], $byPerson[101]->getRecipientEmails(), 'every address is kept for a later change');
        self::assertSame(['matka.mala@example.cz'], $byPerson[102]->getRecipientEmails());
        self::assertSame([], $byPerson[105]->getRecipientEmails());
        self::assertSame(['pavel@example.cz'], $guest->getRecipientEmails());
        self::assertSame('pavel@example.cz', $guest->getDisplayName());
        self::assertSame([101, 102, 105], $this->sorted($this->registrations->findInvitedPersonIds($event)));

        // The leader picked the father and the child's own address for Anna; an address skautIS does not know is ignored.
        $other = $this->openEvent();
        $anna = $this->service->inviteMembers($other, [101 => ['otec.maly@example.cz', 'Anna.Mala@example.cz', 'cizi@example.cz']], 1)[0];
        self::assertSame(['otec.maly@example.cz', 'anna.mala@example.cz'], $anna->getSelectedRecipientEmails());
        self::assertSame(['otec.maly@example.cz', 'anna.mala@example.cz'], $anna->getMailAddresses(), 'every mail of an invited member goes to the chosen addresses');
        self::assertFalse($anna->asksForContactEmail());

        $mother = $anna->getRecipients()[0];
        self::assertTrue($this->service->updateRecipients($anna, [(int) $mother->getId()], 'babicka@example.cz', 1));
        self::assertSame(['matka.mala@example.cz', 'babicka@example.cz'], $anna->getSelectedRecipientEmails(), 'the inline edit switches and adds addresses');
        $logs = $anna->getLogs();
        self::assertStringContainsString('matka.mala@example.cz, babicka@example.cz', end($logs) === false ? '' : end($logs)->getMessage());
    }

    public function testTokenLookupAnswerCapacityAndFieldGuard(): void
    {
        $event = $this->openEvent(capacity: 1);
        $text = $this->events->addField($event, new EventFieldInput(EventFieldType::Text, 'Alergie', null, false, []));
        $choice = $this->events->addField($event, new EventFieldInput(EventFieldType::Choice, 'Doprava', null, true, ['autobus', 'vlastní']));
        [$anna, $petr] = $this->service->inviteMembers($event, [101 => null, 102 => null], 1);

        $token = $this->service->getToken($anna);
        self::assertSame($anna, $this->service->findByToken($token));
        self::assertSame($token, $this->service->getToken($anna), 'the link is stable');
        self::assertNull($this->service->findByToken('unknown'));
        $replaced = $this->service->replaceToken($anna, 1);
        self::assertNull($this->service->findByToken($token));
        self::assertSame($anna, $this->service->findByToken($replaced));

        self::assertFalse($this->service->verifyBirthDate($anna, new DateTimeImmutable('2000-01-01')));
        self::assertTrue($this->service->verifyBirthDate($anna, new DateTimeImmutable('2015-03-12')));

        $bus = $choice->getOptions()[0]->getId();
        $this->service->respond($anna, new RegistrationAnswer(attending: true, contactEmail: 'matka@example.cz', fieldValues: [
            $text->getId() => 'ořechy',
            $choice->getId() => (string) $bus,
        ]));
        $this->service->respond($petr, new RegistrationAnswer(attending: true, contactEmail: 'matka@example.cz', fieldValues: [
            $choice->getId() => $choice->getOptions()[1]->getId(),
        ]));

        self::assertSame(RegistrationState::Registered, $anna->getState());
        self::assertSame(RegistrationState::Waitlisted, $petr->getState());
        self::assertSame(1, $this->registrations->countRegistered($event));

        $this->entityManager->clear();
        $reloaded = $this->registrations->find($anna->getId());
        self::assertInstanceOf(Registration::class, $reloaded);
        $fields = $reloaded->getEvent()->getFields();
        self::assertSame('ořechy', $reloaded->getFieldValueText($fields[0]));
        self::assertSame('autobus', $reloaded->getFieldValueText($fields[1]));

        self::assertTrue($this->registrations->hasValuesForField($fields[1]));
        $this->events->updateField($fields[1], new EventFieldInput(EventFieldType::Choice, 'Doprava na akci', 'nápověda', true, ['autobus', 'vlastní']));
        self::assertSame('Doprava na akci', $fields[1]->getLabel());
        self::assertSame('autobus', $reloaded->getFieldValueText($fields[1]), 'unchanged options keep the answers');

        try {
            $this->events->updateField($fields[1], new EventFieldInput(EventFieldType::Choice, 'Doprava', null, true, ['vlak']));
            self::fail('Changing options of an answered field must be refused.');
        } catch (FieldInUseException) {
        }

        try {
            $this->events->removeField($fields[0]);
            self::fail('Removing an answered field must be refused.');
        } catch (FieldInUseException) {
        }

        $this->events->setFieldHidden($fields[0], true);
        self::assertTrue($fields[0]->isHidden());

        $counts = $this->registrations->countByState($reloaded->getEvent());
        self::assertSame(1, $counts[RegistrationState::Registered->value]);
        self::assertSame(1, $counts[RegistrationState::Waitlisted->value]);
    }

    public function testFreedPlacesWaitingListAndOverbooking(): void
    {
        $event = $this->openEvent(capacity: 1);
        [$anna, $petr, $karel] = $this->service->inviteMembers($event, [101 => null, 102 => null, 105 => null], 1);
        foreach ([$anna, $petr, $karel] as $registration) {
            $this->service->respond($registration, new RegistrationAnswer(attending: true, contactEmail: 'rodic@example.cz'));
        }
        self::assertSame(RegistrationState::Registered, $anna->getState());
        self::assertSame([$petr, $karel], $this->service->placesAndWaitlist($event)['waitlisted'], 'in the order they got on the list');

        // Anna cancels herself: the first on the waiting list moves up and gets an e-mail.
        $this->service->cancelByParticipant($anna);
        self::assertSame(RegistrationState::Registered, $petr->getState());
        self::assertSame(RegistrationState::Waitlisted, $karel->getState());
        $tasks = $this->tester->grabService(\App\Model\Task\TaskRepository::class);
        \assert($tasks instanceof \App\Model\Task\TaskRepository);
        self::assertContains('promoted', array_map(static fn (\App\Model\Task\Task $task): string => $task->getPayloadString('type'), $tasks->findAutomaticByEvent($event, \App\Model\Task\TaskState::Queued)));

        // The leader cancels Petr: the place stays free until the leader decides.
        $this->service->cancelByLeader($petr, 1);
        self::assertSame(RegistrationState::Waitlisted, $karel->getState());
        self::assertSame(1, $this->service->placesAndWaitlist($event)['free']);
        $this->service->promote($karel, 1);
        self::assertSame(RegistrationState::Registered, $karel->getState());

        // Full again: a waitlisted person comes in only with the leader's explicit overbooking.
        $guest = $this->service->inviteNonMember($event, 'host@example.cz', 'Pavel', 'Host', 1);
        $this->service->respond($guest, new RegistrationAnswer(attending: true, contactEmail: 'host@example.cz', firstName: 'Pavel', lastName: 'Host', sex: \App\Model\Registration\Sex::Male, birthDate: new DateTimeImmutable('2012-02-02'), street: 'A 1', city: 'Brno', postcode: '60200'));
        self::assertSame(RegistrationState::Waitlisted, $guest->getState());
        try {
            $this->service->promote($guest, 1);
            self::fail('a full event needs the confirmation');
        } catch (EventFullException) {
        }
        $this->service->promote($guest, 1, overbook: true);
        self::assertSame(RegistrationState::Registered, $guest->getState());
        self::assertSame(2, $this->registrations->countRegistered($event));
        self::assertStringContainsString('nad kapacitu', $guest->getLogs()[\count($guest->getLogs()) - 1]->getMessage());
    }

    public function testTheSubmitLocksTheEventOnlyWhileTheDecisionIsMade(): void
    {
        $event = $this->openEvent(capacity: 1);
        $connection = $this->entityManager->getConnection();
        // A second database session, as a second visitor sending the form at the same moment.
        $other = \Doctrine\DBAL\DriverManager::getConnection($connection->getParams());
        $lockedByOther = static function () use ($other, $event): bool {
            try {
                $other->beginTransaction();
                $other->executeQuery('SELECT id FROM event WHERE id = ? FOR UPDATE NOWAIT', [$event->getId()]);
                $other->rollBack();

                return false;
            } catch (\Doctrine\DBAL\Exception) {
                $other->rollBack();

                return true;
            }
        };

        self::assertFalse($lockedByOther(), 'filling in the form holds no lock');
        $insideTheSubmit = $this->registrations->underCapacityLock($event, $lockedByOther);
        self::assertTrue($insideTheSubmit, 'the submit holds the event row until it is committed');
        self::assertFalse($lockedByOther(), 'and releases it right after');
        $other->close();
    }

    public function testFullEventWithoutAWaitingListRefusesNewAttendees(): void
    {
        $event = $this->openEvent(capacity: 1, waitlist: false);
        [$anna, $petr] = $this->service->inviteMembers($event, [101 => null, 102 => null], 1);
        $this->service->respond($anna, new RegistrationAnswer(attending: true, contactEmail: 'rodic@example.cz'));

        $this->service->respond($petr, new RegistrationAnswer(attending: false, contactEmail: 'rodic@example.cz'));
        self::assertSame(RegistrationState::Declined, $petr->getState(), 'declining is always possible');
        $this->expectException(EventFullException::class);
        $this->service->respond($petr, new RegistrationAnswer(attending: true, contactEmail: 'rodic@example.cz'));
    }

    private function openEvent(?int $capacity = null, bool $waitlist = true): Event
    {
        $event = $this->events->create(1003, new EventInput(
            name: 'Podzimní výprava',
            skautisTypeId: '2',
            skautisScopeId: '2',
            dateFrom: new DateTimeImmutable('+30 days'),
            dateTo: new DateTimeImmutable('+31 days'),
            place: null,
            description: null,
            registrationDeadline: null,
            capacity: $capacity,
            price: null,
            contactName: 'Jan Novák',
            contactEmail: 'jan@example.cz',
            contactPhone: null,
            waitlistEnabled: $waitlist,
        ), 1);
        $this->events->changeState($event, EventState::Open);

        return $event;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}
