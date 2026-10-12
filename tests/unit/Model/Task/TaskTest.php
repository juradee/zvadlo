<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Task;

use App\Model\Task\Task;
use App\Model\Task\TaskState;
use App\Model\Task\TaskType;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class TaskTest extends Unit
{
    public function testHeartbeatIsSetOnStartAndTouch(): void
    {
        $now = new DateTimeImmutable('2026-10-05 10:00');
        $task = new Task(TaskType::SendInvitations, null, ['type' => 'invitation'], [], 1, $now);
        self::assertNull($task->getHeartbeatAt());

        $task->start($now);
        self::assertEquals($now, $task->getHeartbeatAt());
        $task->touch($now->modify('+20 seconds'));
        self::assertEquals($now->modify('+20 seconds'), $task->getHeartbeatAt());
    }

    public function testProgressAndFailureAfterThreeAttempts(): void
    {
        $now = new DateTimeImmutable('2026-10-05 10:00');
        $task = new Task(TaskType::SendInvitations, null, ['type' => 'invitation'], [], 1, $now);

        self::assertSame(TaskState::Queued, $task->getState());
        self::assertSame(100, $task->getProgressPercent(), 'nothing to do counts as done');

        $task->start($now);
        $task->failAttempt('SMTP down', $now);
        self::assertSame(TaskState::Queued, $task->getState());
        $task->start($now);
        $task->failAttempt('SMTP down', $now);
        $task->start($now);
        $task->failAttempt('SMTP down', $now);
        self::assertSame(TaskState::Failed, $task->getState());
        self::assertSame('SMTP down', $task->getError());

        $task->requeue();
        self::assertSame(TaskState::Queued, $task->getState());
        self::assertSame(0, $task->getAttempts());
        self::assertNull($task->getError());

        $task->start($now);
        $task->requeueKeepingAttempts();
        self::assertSame(0, $task->getAttempts(), 'running out of time is not a failed attempt');

        $task->start($now);
        $task->finish($now);
        self::assertSame(TaskState::Done, $task->getState());
        self::assertEquals($now, $task->getFinishedAt());
    }
}
