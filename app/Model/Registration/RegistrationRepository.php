<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Event\Event;
use App\Model\Event\EventField;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

class RegistrationRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Runs one capacity decision (who gets a place, who waits, who is refused) with the event row locked
     * (SELECT ... FOR UPDATE) and commits it. Only the moment of the submit is locked, never the filling in:
     * a second submit for the same event waits here until the first is committed and then counts with it.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function underCapacityLock(Event $event, callable $work): mixed
    {
        // Not wrapInTransaction(): it closes the EntityManager on an exception, and a refused sign-up
        // (EventFullException) must leave the request able to render the form with the message.
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $this->entityManager->lock($event, LockMode::PESSIMISTIC_WRITE);
            $result = $work();
            $this->entityManager->flush();
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }

    public function find(int $id): ?Registration
    {
        return $this->entityManager->find(Registration::class, $id);
    }

    public function findByTokenHash(string $tokenHash): ?Registration
    {
        return $this->entityManager->getRepository(Registration::class)->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * Registrations of an event sorted by name; without a state filter the rejected ones are left out.
     *
     * @return list<Registration>
     */
    public function findByEvent(Event $event, ?RegistrationState $state = null): array
    {
        $builder = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Registration::class, 'r')
            ->where('r.event = :event')
            ->setParameter('event', $event)
            ->orderBy('r.lastName', 'ASC')
            ->addOrderBy('r.firstName', 'ASC')
            ->addOrderBy('r.id', 'ASC');

        if ($state !== null) {
            $builder->andWhere('r.state = :state')->setParameter('state', $state);
        } else {
            $builder->andWhere('r.state != :rejected')->setParameter('rejected', RegistrationState::Rejected);
        }

        /** @var list<Registration> $registrations */
        $registrations = $builder->getQuery()->getResult();

        return $registrations;
    }

    /**
     * skautIS person ids with a registration in the event (any state, rejected included).
     *
     * @return list<int>
     */
    public function findInvitedPersonIds(Event $event): array
    {
        /** @var list<array{skautisPersonId: int|null}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('r.skautisPersonId')
            ->from(Registration::class, 'r')
            ->where('r.event = :event')
            ->andWhere('r.skautisPersonId IS NOT NULL')
            ->setParameter('event', $event)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(static fn (array $row): ?int => $row['skautisPersonId'], $rows), static fn (?int $id): bool => $id !== null));
    }

    public function findByPerson(Event $event, int $skautisPersonId): ?Registration
    {
        return $this->entityManager->getRepository(Registration::class)->findOneBy(['event' => $event, 'skautisPersonId' => $skautisPersonId]);
    }

    public function existsWithEmailHash(Event $event, string $emailHash): bool
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Registration::class, 'r')
            ->where('r.event = :event')
            ->andWhere('r.emailHash = :hash')
            ->setParameter('event', $event)
            ->setParameter('hash', $emailHash)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Number of registrations per state, every state present.
     *
     * @return array<string, int>
     */
    public function countByState(Event $event): array
    {
        $counts = [];
        foreach (RegistrationState::cases() as $state) {
            $counts[$state->value] = 0;
        }

        /** @var list<array{state: RegistrationState, total: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('r.state AS state, COUNT(r.id) AS total')
            ->from(Registration::class, 'r')
            ->where('r.event = :event')
            ->setParameter('event', $event)
            ->groupBy('r.state')
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $counts[$row['state']->value] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Waiting list in the order people got on it.
     *
     * @return list<Registration>
     */
    public function findWaitlisted(Event $event): array
    {
        /** @var list<Registration> $registrations */
        $registrations = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Registration::class, 'r')
            ->where('r.event = :event')
            ->andWhere('r.state = :state')
            ->setParameter('event', $event)
            ->setParameter('state', RegistrationState::Waitlisted)
            ->orderBy('r.waitlistedAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $registrations;
    }

    public function countRegistered(Event $event): int
    {
        return $this->countByState($event)[RegistrationState::Registered->value];
    }

    /**
     * Whether any registration answered this field; such a field must keep its options.
     */
    public function hasValuesForField(EventField $field): bool
    {
        $text = $this->entityManager->createQueryBuilder()
            ->select('COUNT(v.value)')
            ->from(RegistrationTextValue::class, 'v')
            ->where('v.field = :field')
            ->setParameter('field', $field)
            ->getQuery()
            ->getSingleScalarResult();
        $boolean = $this->entityManager->createQueryBuilder()
            ->select('COUNT(v.value)')
            ->from(RegistrationBooleanValue::class, 'v')
            ->where('v.field = :field')
            ->setParameter('field', $field)
            ->getQuery()
            ->getSingleScalarResult();
        $option = $this->entityManager->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(RegistrationOptionValue::class, 'v')
            ->join('v.option', 'o')
            ->where('o.field = :field')
            ->setParameter('field', $field)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $text + (int) $boolean + (int) $option > 0;
    }

    public function add(Registration $registration): void
    {
        $this->entityManager->persist($registration);
        $this->entityManager->flush();
    }

    public function save(): void
    {
        $this->entityManager->flush();
    }
}
