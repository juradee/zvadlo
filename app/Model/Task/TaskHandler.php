<?php

declare(strict_types=1);

namespace App\Model\Task;

interface TaskHandler
{
    public function supports(TaskType $type): bool;

    /**
     * Processes one batch and reports progress on the task.
     *
     * @return bool true when nothing is left to do
     */
    public function handleBatch(Task $task): bool;
}
