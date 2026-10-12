<?php

declare(strict_types=1);

namespace App\Tests\Integration\Model\Task;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventService;
use App\Model\Event\EventState;
use App\Model\Mailing\EmailType;
use App\Model\Registration\RegistrationAnswer;
use App\Model\Registration\RegistrationService;
use App\Model\Registration\Sex;
use App\Model\Task\Task;
use App\Model\Task\TaskQueue;
use App\Model\Task\TaskRunner;
use App\Model\Task\TaskState;
use App\Model\Task\TaskType;
use App\Tests\Support\IntegrationTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Load check of the plan (phase 7): a message to 200 participants goes through the queue within one worker run.
 */
final class QueueLoadTest extends Unit
{
    private const string MAILPIT = 'http://mailpit:8025';

    protected IntegrationTester $tester;

    public function testTwoHundredRecipientsInOneRun(): void
    {
        $entityManager = $this->tester->grabService(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $entityManager->createQuery('DELETE FROM '.Task::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Event::class)->execute();
        @file_get_contents(self::MAILPIT.'/api/v1/messages', false, stream_context_create(['http' => ['method' => 'DELETE']]));

        $events = $this->tester->grabService(EventService::class);
        \assert($events instanceof EventService);
        $registrations = $this->tester->grabService(RegistrationService::class);
        \assert($registrations instanceof RegistrationService);
        $event = $events->create(1003, new EventInput('Velká akce', '2', '2', new DateTimeImmutable('+30 days'), new DateTimeImmutable('+31 days'), 'Louka', null, null, null, null, 'Jan Novák', 'jan@example.cz', null), 1);
        $events->changeState($event, EventState::Open);

        $targets = [];
        for ($i = 1; $i <= 200; ++$i) {
            $guest = $registrations->inviteNonMember($event, \sprintf('host%03d@example.cz', $i), null, null, 1);
            $registrations->respond($guest, new RegistrationAnswer(attending: true, contactEmail: \sprintf('host%03d@example.cz', $i), firstName: 'Host', lastName: (string) $i, sex: Sex::Male, birthDate: new DateTimeImmutable('2000-01-01'), street: 'A 1', city: 'Brno', postcode: '60200'));
            $targets[] = $guest;
        }
        $entityManager->createQuery('DELETE FROM '.Task::class)->execute();

        $queue = $this->tester->grabService(TaskQueue::class);
        \assert($queue instanceof TaskQueue);
        $task = $queue->enqueue(TaskType::SendInfo, $event, ['type' => EmailType::Info->value, 'note' => 'Sraz je v 8:00 před klubovnou.'], $targets, 1);

        $runner = $this->tester->grabService(TaskRunner::class);
        \assert($runner instanceof TaskRunner);
        $started = microtime(true);
        $runner->run(TaskRunner::TIME_LIMIT_SECONDS);
        $elapsed = microtime(true) - $started;

        $entityManager->refresh($task);
        self::assertSame(TaskState::Done, $task->getState(), 'error: '.($task->getError() ?? '-'));
        self::assertSame(200, $task->getProgressDone());
        self::assertLessThan(TaskRunner::TIME_LIMIT_SECONDS, $elapsed, 'one worker run is enough for 200 recipients');

        $json = @file_get_contents(self::MAILPIT.'/api/v1/messages?limit=1');
        $data = \is_string($json) ? json_decode($json, true) : null;
        self::assertSame(200, \is_array($data) ? (int) ($data['total'] ?? 0) : 0, '200 mails reached Mailpit');
    }
}
