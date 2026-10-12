<?php

declare(strict_types=1);

namespace App\Model\Task;

use App\Model\Event\Event;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class TaskRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(int $id): ?Task
    {
        return $this->entityManager->find(Task::class, $id);
    }

    /**
     * Takes the oldest queued task for this worker; the UPDATE with the state condition makes two workers
     * never take the same one.
     */
    public function claimNext(DateTimeImmutable $now): ?Task
    {
        $connection = $this->entityManager->getConnection();
        $id = $connection->fetchOne('SELECT id FROM task WHERE state = ? ORDER BY id LIMIT 1', [TaskState::Queued->value]);
        if ($id === false || $id === null) {
            return null;
        }

        $claimed = $connection->executeStatement(
            'UPDATE task SET state = ?, started_at = COALESCE(started_at, ?), heartbeat_at = ?, attempts = attempts + 1 WHERE id = ? AND state = ?',
            [TaskState::Running->value, $now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), (int) $id, TaskState::Queued->value],
        );
        if ($claimed !== 1) {
            return null;
        }

        $task = $this->entityManager->find(Task::class, (int) $id);
        if ($task !== null) {
            $this->entityManager->refresh($task);
        }

        return $task;
    }

    /**
     * Tasks left running by a worker that died (deploy, out of memory, lost database): back to the queue,
     * or failed when the attempts are used up, so the leader can retry them. A live worker touches its
     * task after every batch and never runs longer than TaskRunner::TIME_LIMIT_SECONDS.
     *
     * @return int number of tasks released
     */
    public function releaseStale(DateTimeImmutable $staleBefore, DateTimeImmutable $now): int
    {
        $connection = $this->entityManager->getConnection();
        $stale = [TaskState::Running->value, $staleBefore->format('Y-m-d H:i:s')];
        $where = 'state = ? AND (heartbeat_at IS NULL OR heartbeat_at < ?)';

        $failed = $connection->executeStatement(
            'UPDATE task SET state = ?, error = ?, finished_at = ? WHERE '.$where.' AND attempts >= ?',
            [TaskState::Failed->value, 'Zpracování úlohy bylo přerušeno (worker přestal odpovídat).', $now->format('Y-m-d H:i:s'), ...$stale, Task::MAX_ATTEMPTS],
        );
        $queued = $connection->executeStatement(
            'UPDATE task SET state = ?, error = ? WHERE '.$where,
            [TaskState::Queued->value, 'Zpracování bylo přerušeno, úloha pokračuje.', ...$stale],
        );

        return (int) $failed + (int) $queued;
    }

    public function hasActive(Event $event, TaskType $type): bool
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Task::class, 't')
            ->where('t.event = :event')
            ->andWhere('t.type = :type')
            ->andWhere('t.state IN (:states)')
            ->setParameter('event', $event)
            ->setParameter('type', $type)
            ->setParameter('states', [TaskState::Queued, TaskState::Running])
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function existsForEvent(Event $event, TaskType $type): bool
    {
        return $this->entityManager->getRepository(Task::class)->count(['event' => $event, 'type' => $type]) > 0;
    }

    /**
     * Newest tasks a leader started (not the automatic single mails), for the progress panel.
     *
     * @return list<Task>
     */
    public function findStartedByEvent(Event $event, int $limit = 5): array
    {
        /** @var list<Task> $tasks */
        $tasks = $this->entityManager->createQueryBuilder()
            ->select('t')->from(Task::class, 't')
            ->where('t.event = :event')->andWhere('t.type != :automatic')
            ->setParameter('event', $event)->setParameter('automatic', TaskType::SendEmail)
            ->orderBy('t.id', 'DESC')->setMaxResults($limit)
            ->getQuery()->getResult();

        return $tasks;
    }

    /**
     * Automatic single mails of an event in the given states; the page shows them as one summary.
     *
     * @return list<Task>
     */
    public function findAutomaticByEvent(Event $event, TaskState ...$states): array
    {
        /** @var list<Task> $tasks */
        $tasks = $this->entityManager->getRepository(Task::class)->findBy(['event' => $event, 'type' => TaskType::SendEmail, 'state' => $states], ['id' => 'ASC']);

        return $tasks;
    }

    /**
     * @return list<Task>
     */
    public function findActiveByEvent(Event $event): array
    {
        /** @var list<Task> $tasks */
        $tasks = $this->entityManager->getRepository(Task::class)->findBy(['event' => $event, 'state' => [TaskState::Queued, TaskState::Running]], ['id' => 'ASC']);

        return $tasks;
    }

    public function add(Task $task): void
    {
        $this->entityManager->persist($task);
        $this->entityManager->flush();
    }

    public function save(): void
    {
        $this->entityManager->flush();
    }
}
