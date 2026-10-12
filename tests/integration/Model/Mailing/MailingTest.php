<?php

declare(strict_types=1);

namespace App\Tests\Integration\Model\Mailing;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventService;
use App\Model\Event\EventState;
use App\Model\Mailing\EditCodeService;
use App\Model\Mailing\EditCodeThrottledException;
use App\Model\Mailing\EmailMessageRepository;
use App\Model\Mailing\EmailType;
use App\Model\Mailing\LockNoticeScheduler;
use App\Model\Mailing\MailComposer;
use App\Model\Registration\RegistrationAnswer;
use App\Model\Registration\RegistrationService;
use App\Model\Registration\Sex;
use App\Model\Task\Task;
use App\Model\Task\TaskAlreadyRunningException;
use App\Model\Task\TaskQueue;
use App\Model\Task\TaskRepository;
use App\Model\Task\TaskRunner;
use App\Model\Task\TaskState;
use App\Model\Task\TaskType;
use App\Tests\Support\IntegrationTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Queue, worker and mail log end to end; mail goes to Mailpit over SMTP and is checked through its API.
 */
final class MailingTest extends Unit
{
    private const string MAILPIT = 'http://mailpit:8025';

    protected IntegrationTester $tester;

    private EntityManagerInterface $entityManager;

    private EventService $events;

    private RegistrationService $registrations;

    private TaskQueue $queue;

    private TaskRepository $tasks;

    private TaskRunner $runner;

    private EmailMessageRepository $messages;

    protected function _before(): void
    {
        $this->entityManager = $this->tester->grabService(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM '.Task::class)->execute();
        $this->entityManager->createQuery('DELETE FROM '.Event::class)->execute();
        $this->events = $this->tester->grabService(EventService::class);
        $this->registrations = $this->tester->grabService(RegistrationService::class);
        $this->queue = $this->tester->grabService(TaskQueue::class);
        $this->tasks = $this->tester->grabService(TaskRepository::class);
        $this->runner = $this->tester->grabService(TaskRunner::class);
        $this->messages = $this->tester->grabService(EmailMessageRepository::class);
        @file_get_contents(self::MAILPIT.'/api/v1/messages', false, stream_context_create(['http' => ['method' => 'DELETE']]));
        // Registration ids start again with every database reset, the code throttle in the cache does not.
        (new \Nette\Caching\Cache($this->tester->grabService(\Nette\Caching\Storage::class), 'registration.editCode'))->clean([\Nette\Caching\Cache::All => true]);
    }

    public function testInvitationsGoOutThroughTheQueueAndAreLogged(): void
    {
        $event = $this->openEvent();
        [$anna, $petr] = $this->registrations->inviteMembers($event, [101 => null, 102 => null], 1);
        $karel = $this->registrations->inviteMembers($event, [105 => null], 1)[0];

        $task = $this->queue->enqueue(TaskType::SendInvitations, $event, ['type' => EmailType::Invitation->value], [$anna, $petr, $karel], 1);
        try {
            $this->queue->enqueue(TaskType::SendInvitations, $event, ['type' => EmailType::Invitation->value], [$anna], 1);
            self::fail('The same task must not run twice at once.');
        } catch (TaskAlreadyRunningException) {
        }

        self::assertGreaterThan(0, $this->runner->run(10));
        $this->entityManager->refresh($task);

        self::assertSame(TaskState::Done, $task->getState());
        self::assertSame(3, $task->getProgressDone());
        self::assertNotNull($anna->getInvitedAt());
        self::assertNull($karel->getInvitedAt(), 'no address, nothing sent');

        $annaLog = $this->messages->findByRegistration($anna);
        self::assertCount(2, $annaLog, 'both parents');
        self::assertSame(['otec.maly@example.cz', 'matka.mala@example.cz'], array_map(static fn ($m): string => $m->getToEmail(), $annaLog));
        self::assertTrue($annaLog[0]->isSent());
        self::assertStringContainsString('Podzimní výprava', $annaLog[0]->getSubject());
        self::assertStringContainsString('/prihlaska/'.$this->registrations->getToken($anna), $annaLog[0]->getBody(), 'the personal link is in the mail');

        $karelLog = $this->messages->findByRegistration($karel);
        self::assertCount(1, $karelLog);
        self::assertFalse($karelLog[0]->isSent());

        self::assertSame(2, $this->mailpitCount('to:matka.mala@example.cz'));
    }

    public function testAnswersQueueAutomaticMailsAndRejectionRendersBeforeAnonymisation(): void
    {
        $event = $this->openEvent();
        $anna = $this->registrations->inviteMembers($event, [101 => null], 1)[0];

        $this->registrations->respond($anna, new RegistrationAnswer(attending: true, contactEmail: 'matka.mala@example.cz'));
        $this->registrations->respond($anna, new RegistrationAnswer(attending: true, contactEmail: 'matka.mala@example.cz', note: 'změna'));
        $this->registrations->respond($anna, new RegistrationAnswer(attending: false, contactEmail: 'matka.mala@example.cz'));
        $this->registrations->reject($anna, 1, 'nepatří k nám');

        $types = array_map(static fn (Task $task): string => $task->getPayloadString('type'), $this->tasks->findAutomaticByEvent($event, TaskState::Queued));
        self::assertSame(['confirmation', 'changed', 'declined', 'rejected'], $types);

        $this->runner->run(10);
        $log = array_reverse($this->messages->findByRegistration($anna));
        self::assertSame(['confirmation', 'changed', 'declined', 'rejected', 'rejected'], array_map(static fn ($m): string => $m->getType()->value, $log));
        self::assertStringContainsString('nepatří k nám', $log[3]->getBody());
        self::assertSame(['matka.mala@example.cz', 'otec.maly@example.cz'], [$log[3]->getToEmail(), $log[4]->getToEmail()], 'the chosen addresses survived the anonymisation inside the task');
        self::assertNull($anna->getContactEmail());
    }

    public function testEditCodeIsSentAtOnceAndUnlocksOnce(): void
    {
        $event = $this->openEvent();
        $guest = $this->registrations->inviteNonMember($event, 'host@example.cz', null, null, 1);
        $this->registrations->respond($guest, new RegistrationAnswer(attending: true, contactEmail: 'host@example.cz', firstName: 'Pavel', lastName: 'Host', sex: Sex::Male, birthDate: new DateTimeImmutable('2012-02-02'), street: 'A 1', city: 'Brno', postcode: '60200'));

        $codes = $this->tester->grabService(EditCodeService::class);
        self::assertTrue($codes->issue($guest));
        $message = $this->messages->findByRegistration($guest)[0];
        self::assertSame(EmailType::EditCode, $message->getType());
        self::assertSame(1, preg_match('~\*\*(\d{4})\*\*~', $message->getBody(), $match));
        self::assertTrue($guest->hasValidEditCode(new DateTimeImmutable()));

        self::assertFalse($codes->verify($guest, '0000'));
        self::assertTrue($codes->verify($guest, $match[1]));
        self::assertFalse($codes->verify($guest, $match[1]), 'a code works once');
    }

    public function testTasksOfADeadWorkerAreReleased(): void
    {
        $event = $this->openEvent();
        $anna = $this->registrations->inviteMembers($event, [101 => null], 1)[0];
        $task = $this->queue->enqueue(TaskType::SendInvitations, $event, ['type' => EmailType::Invitation->value], [$anna], 1);

        // A worker claims the task and dies before finishing it.
        $claimedAt = new DateTimeImmutable('-10 minutes');
        self::assertSame($task->getId(), $this->tasks->claimNext($claimedAt)?->getId());
        $this->entityManager->refresh($task);
        self::assertSame(TaskState::Running, $task->getState());
        self::assertSame(0, $this->tasks->releaseStale($claimedAt->modify('-1 second'), new DateTimeImmutable()), 'a fresh heartbeat is left alone');

        $this->runner->run(10);
        $this->entityManager->refresh($task);
        self::assertSame(TaskState::Done, $task->getState(), 'the next run releases it and finishes the work');
        self::assertNotNull($anna->getInvitedAt());

        // Out of attempts: failed, so the leader sees it and can retry.
        $other = $this->queue->enqueue(TaskType::SendReminder, $event, ['type' => EmailType::Reminder->value], [$anna], 1);
        for ($attempt = 1; $attempt <= Task::MAX_ATTEMPTS; ++$attempt) {
            $this->tasks->claimNext($claimedAt);
            if ($attempt < Task::MAX_ATTEMPTS) {
                $this->tasks->releaseStale(new DateTimeImmutable(), new DateTimeImmutable());
            }
        }
        self::assertSame(1, $this->tasks->releaseStale(new DateTimeImmutable(), new DateTimeImmutable()));
        $this->entityManager->refresh($other);
        self::assertSame(TaskState::Failed, $other->getState());
        self::assertStringContainsString('přerušeno', (string) $other->getError());
    }

    public function testEditCodeRequestsAreThrottled(): void
    {
        $event = $this->openEvent();
        $guest = $this->registrations->inviteNonMember($event, 'host2@example.cz', null, null, 1);
        $this->registrations->respond($guest, new RegistrationAnswer(attending: true, contactEmail: 'host2@example.cz', firstName: 'Petra', lastName: 'Host', sex: Sex::Female, birthDate: new DateTimeImmutable('2012-02-02'), street: 'A 1', city: 'Brno', postcode: '60200'));
        $codes = $this->tester->grabService(EditCodeService::class);

        self::assertTrue($codes->issue($guest));
        $message = $this->messages->findByRegistration($guest)[0];
        self::assertSame(1, preg_match('~\*\*(\d{4})\*\*~', $message->getBody(), $match));
        try {
            $codes->issue($guest);
            self::fail('A second code within a minute must be refused.');
        } catch (EditCodeThrottledException) {
        }
        self::assertCount(1, $this->messages->findByRegistration($guest), 'nothing else was sent');
        self::assertTrue($codes->verify($guest, $match[1]), 'the code already sent stays valid');
    }

    public function testLockNoticeIsQueuedOnTheRightDayOnlyOnce(): void
    {
        $event = $this->openEvent(dateFrom: '2027-03-20', deadline: '2027-03-16', lockNoticeDays: 2);
        $anna = $this->registrations->inviteMembers($event, [101 => null], 1)[0];
        $this->registrations->respond($anna, new RegistrationAnswer(attending: true, contactEmail: 'matka.mala@example.cz'));

        $scheduler = $this->tester->grabService(LockNoticeScheduler::class);
        self::assertSame(0, $scheduler->run(new DateTimeImmutable('2027-03-13')));
        self::assertSame(1, $scheduler->run(new DateTimeImmutable('2027-03-14')), 'deadline on 16th, notice two days earlier');
        self::assertSame(0, $scheduler->run(new DateTimeImmutable('2027-03-14')), 'never twice');

        $noDeadline = $this->openEvent(dateFrom: '2027-03-20', lockNoticeDays: 2);
        self::assertNull($noDeadline->getLockNoticeDate(), 'without a deadline there is nothing to warn about');
    }

    public function testComposerFillsTheSymbols(): void
    {
        $event = $this->openEvent();
        $composer = $this->tester->grabService(MailComposer::class);
        $values = $composer->values($event, null);

        self::assertSame('Podzimní výprava', $values['akce']);
        self::assertStringContainsString('Jan Novák, jan@example.cz', $values['kontakt']);
        self::assertStringContainsString('/1003/podzimni-vyprava?t='.$event->getWebToken(), $values['odkaz-web']);
        self::assertSame($event->getDateTo()->format('d.m.Y'), $values['uzaverka'], 'no deadline: the last day of the event');
        self::assertArrayNotHasKey('zamek', $values);
        self::assertSame('16.03.2027', $composer->values($this->openEvent(dateFrom: '2027-03-20', deadline: '2027-03-16'), null)['uzaverka']);
    }

    private function openEvent(string $dateFrom = '+30 days', ?string $deadline = null, int $lockNoticeDays = 2): Event
    {
        $event = $this->events->create(1003, new EventInput(
            name: 'Podzimní výprava',
            skautisTypeId: '2',
            skautisScopeId: '2',
            dateFrom: new DateTimeImmutable($dateFrom),
            dateTo: new DateTimeImmutable($dateFrom),
            place: 'Louka',
            description: null,
            registrationDeadline: $deadline === null ? null : new DateTimeImmutable($deadline),
            capacity: null,
            price: null,
            contactName: 'Jan Novák',
            contactEmail: 'jan@example.cz',
            contactPhone: null,
            lockNoticeDays: $lockNoticeDays,
        ), 1);
        $this->events->changeState($event, EventState::Open);

        return $event;
    }

    private function mailpitCount(string $query): int
    {
        $json = @file_get_contents(self::MAILPIT.'/api/v1/search?query='.urlencode($query));
        $data = \is_string($json) ? json_decode($json, true) : null;

        return \is_array($data) ? (int) ($data['messages_count'] ?? 0) : 0;
    }
}
