<?php

declare(strict_types=1);

namespace App\Model\Task;

use App\Model\Mailing\InvalidOAuthException;
use DateTimeImmutable;
use RuntimeException;
use Throwable;
use Tracy\ILogger;

/**
 * The worker the scheduler starts every minute: claims tasks, runs batches until the time is up.
 */
final class TaskRunner
{
    public const int TIME_LIMIT_SECONDS = 50;
    /** A running task without a heartbeat for this long belonged to a worker that died. */
    public const int STALE_AFTER_SECONDS = 300;
    public const string GENERIC_ERROR = 'Úlohu se nepodařilo dokončit, chybu jsme zaznamenali.';

    /**
     * @param iterable<TaskHandler> $handlers
     */
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly iterable $handlers,
        private readonly ILogger $logger,
    ) {
    }

    /**
     * @return int number of batches processed
     */
    public function run(int $timeLimitSeconds = self::TIME_LIMIT_SECONDS): int
    {
        $deadline = microtime(true) + $timeLimitSeconds;
        $batches = 0;
        $now = new DateTimeImmutable();
        $this->tasks->releaseStale($now->modify(\sprintf('-%d seconds', self::STALE_AFTER_SECONDS)), $now);

        while (microtime(true) < $deadline) {
            $now = new DateTimeImmutable();
            $task = $this->tasks->claimNext($now);
            if ($task === null) {
                break;
            }

            $batches += $this->process($task, $deadline);
        }

        return $batches;
    }

    /**
     * Runs one claimed task in batches until finished or out of time; the task then goes back to the queue.
     */
    private function process(Task $task, float $deadline): int
    {
        $handler = $this->handlerFor($task->getType());
        $batches = 0;

        try {
            do {
                $finished = $handler->handleBatch($task);
                ++$batches;
                $task->touch(new DateTimeImmutable());
                $this->tasks->save();
                if ($finished) {
                    $task->finish(new DateTimeImmutable());
                    $this->tasks->save();

                    return $batches;
                }
            } while (microtime(true) < $deadline);

            // Out of time: the next run continues where this one stopped, without counting as a failed attempt.
            $task->requeueKeepingAttempts();
            $this->tasks->save();
        } catch (Throwable $exception) {
            $this->logger->log($exception, ILogger::ERROR);
            // Only the broken-account message is meant for the leader; anything else stays in the log and Sentry.
            $task->failAttempt($exception instanceof InvalidOAuthException ? $exception->getMessage() : self::GENERIC_ERROR, new DateTimeImmutable());
            $this->tasks->save();
        }

        return $batches;
    }

    private function handlerFor(TaskType $type): TaskHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($type)) {
                return $handler;
            }
        }

        throw new RuntimeException(\sprintf('No handler for task type %s.', $type->value));
    }
}
