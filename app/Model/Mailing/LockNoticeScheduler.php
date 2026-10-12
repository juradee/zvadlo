<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use App\Model\Event\EventRepository;
use App\Model\Registration\Registration;
use App\Model\Registration\RegistrationRepository;
use App\Model\Task\TaskQueue;
use App\Model\Task\TaskRepository;
use App\Model\Task\TaskType;
use DateTimeImmutable;

/**
 * Daily job: queues the lock notice for events whose notice day is today.
 */
final class LockNoticeScheduler
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly RegistrationRepository $registrations,
        private readonly TaskRepository $tasks,
        private readonly TaskQueue $queue,
    ) {
    }

    /**
     * @return int number of tasks queued
     */
    public function run(?DateTimeImmutable $today = null): int
    {
        $today = ($today ?? new DateTimeImmutable())->setTime(0, 0);
        $queued = 0;

        foreach ($this->events->findOpenWithLockNotice() as $event) {
            $noticeDate = $event->getLockNoticeDate();
            if ($noticeDate === null || $noticeDate->format('Y-m-d') !== $today->format('Y-m-d') || $this->tasks->existsForEvent($event, TaskType::SendLockNotice)) {
                continue;
            }

            $attending = array_values(array_filter(
                $this->registrations->findByEvent($event),
                static fn (Registration $registration): bool => $registration->getState()->isAttending() && $registration->getMailAddresses() !== [],
            ));
            if ($attending === []) {
                continue;
            }

            $this->queue->enqueue(TaskType::SendLockNotice, $event, ['type' => EmailType::LockNotice->value], $attending, null);
            ++$queued;
        }

        return $queued;
    }
}
