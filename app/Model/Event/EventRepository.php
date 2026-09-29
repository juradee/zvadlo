<?php

declare(strict_types=1);

namespace App\Model\Event;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class EventRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(int $id): ?Event
    {
        return $this->entityManager->find(Event::class, $id);
    }

    public function findBySlug(int $unitId, string $slug): ?Event
    {
        return $this->entityManager->getRepository(Event::class)->findOneBy(['unitId' => $unitId, 'slug' => $slug]);
    }

    public function slugExists(int $unitId, string $slug, ?int $ignoreEventId = null): bool
    {
        $event = $this->findBySlug($unitId, $slug);

        return $event !== null && $event->getId() !== $ignoreEventId;
    }

    /**
     * Events of the given units, newest first; optionally limited to one year and one state.
     *
     * @param list<int> $unitIds
     *
     * @return list<Event>
     */
    public function findByUnits(array $unitIds, ?int $year = null, ?EventState $state = null): array
    {
        if ($unitIds === []) {
            return [];
        }

        $builder = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.unitId IN (:units)')
            ->setParameter('units', $unitIds)
            ->orderBy('e.dateFrom', 'DESC')
            ->addOrderBy('e.id', 'DESC');

        if ($year !== null) {
            $builder->andWhere('e.dateFrom >= :from AND e.dateFrom < :to')
                ->setParameter('from', \sprintf('%d-01-01', $year))
                ->setParameter('to', \sprintf('%d-01-01', $year + 1));
        }

        if ($state !== null) {
            $builder->andWhere('e.state = :state')->setParameter('state', $state);
        }

        /** @var list<Event> $events */
        $events = $builder->getQuery()->getResult();

        return $events;
    }

    /**
     * Upcoming events of the given units (ending today or later), soonest first.
     *
     * @param list<int> $unitIds
     *
     * @return list<Event>
     */
    public function findUpcoming(array $unitIds, DateTimeImmutable $now, int $limit): array
    {
        if ($unitIds === []) {
            return [];
        }

        /** @var list<Event> $events */
        $events = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.unitId IN (:units)')
            ->andWhere('e.dateTo >= :today')
            ->andWhere('e.state != :archived')
            ->setParameter('units', $unitIds)
            ->setParameter('today', $now->format('Y-m-d'))
            ->setParameter('archived', EventState::Archived)
            ->orderBy('e.dateFrom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $events;
    }

    /**
     * Years that have at least one event in the given units, newest first.
     *
     * @param list<int> $unitIds
     *
     * @return list<int>
     */
    public function findYears(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        /** @var list<array{dateFrom: DateTimeImmutable}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('e.dateFrom')
            ->from(Event::class, 'e')
            ->where('e.unitId IN (:units)')
            ->setParameter('units', $unitIds)
            ->getQuery()
            ->getArrayResult();

        $years = array_unique(array_map(static fn (array $row): int => (int) $row['dateFrom']->format('Y'), $rows));
        rsort($years);

        return $years;
    }

    public function add(Event $event): void
    {
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    public function save(): void
    {
        $this->entityManager->flush();
    }
}
