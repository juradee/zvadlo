<?php

declare(strict_types=1);

namespace App\Model\Task;

use App\Model\Event\Event;
use App\Model\Registration\Registration;
use DateTimeImmutable;

/**
 * The only way to put work into the queue: the request stores the task, the worker does the rest.
 */
final class TaskQueue
{
    public function __construct(private readonly TaskRepository $tasks)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<Registration>   $registrations
     *
     * @throws TaskAlreadyRunningException when the same kind of task is still running for the event
     */
    public function enqueue(TaskType $type, ?Event $event, array $payload, array $registrations, ?int $userId): Task
    {
        if ($event !== null && $type->isExclusivePerEvent() && $this->tasks->hasActive($event, $type)) {
            throw new TaskAlreadyRunningException(\sprintf('Úloha „%s“ pro tuto akci ještě běží. Počkejte, až skončí.', $type->label()));
        }

        $task = new Task($type, $event, $payload, $registrations, $userId, new DateTimeImmutable());
        $this->tasks->add($task);

        return $task;
    }

    public function retry(Task $task): void
    {
        $task->requeue();
        $this->tasks->save();
    }
}
