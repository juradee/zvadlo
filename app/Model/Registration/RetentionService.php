<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Event\Event;
use App\Model\Event\EventState;
use App\Model\Event\PublicRegistration;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Tracy\ILogger;

/**
 * Retention rules of spec ch. 14: after the shorter period every registration of a finished event loses
 * its answers and note and non-members whose person Zvadlo created in skautIS are anonymised; after the
 * longer period non-members never written to skautIS follow. Members keep their skautIS identity.
 * Fingerprints of units without an event open to public registration are deleted.
 */
final class RetentionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MemberFingerprints $fingerprints,
        private readonly ILogger $logger,
        private readonly int $syncedMonths,
        private readonly int $unsyncedMonths,
    ) {
    }

    public function runScheduled(): void
    {
        $result = $this->run(new DateTimeImmutable());
        $this->logger->log('Retention: '.$result->describe(), 'retention');
    }

    public function run(DateTimeImmutable $now, bool $dryRun = false): RetentionResult
    {
        $syncedBefore = $now->modify(\sprintf('-%d months', $this->syncedMonths));
        $unsyncedBefore = $now->modify(\sprintf('-%d months', $this->unsyncedMonths));

        $events = 0;
        $answers = 0;
        foreach ($this->eventsToForget($syncedBefore) as $event) {
            foreach ($this->registrationsOf($event) as $registration) {
                $registration->forgetAnswers($now);
                ++$answers;
            }
            $event->markAnswersForgotten($now);
            ++$events;
        }

        $synced = $this->anonymize('r.skautisPersonCreatedAt IS NOT NULL', $syncedBefore, $now);
        $unsynced = $this->anonymize('r.skautisPersonId IS NULL', $unsyncedBefore, $now);
        $fingerprintUnits = $this->forgetUnusedFingerprints($dryRun);

        if ($dryRun) {
            $this->entityManager->clear();
        } else {
            $this->entityManager->flush();
        }

        return new RetentionResult($events, $answers, $synced, $unsynced, $fingerprintUnits);
    }

    /**
     * @return list<Event>
     */
    private function eventsToForget(DateTimeImmutable $before): array
    {
        /** @var list<Event> $events */
        $events = $this->entityManager->createQuery('SELECT e FROM '.Event::class.' e WHERE e.dateTo < :before AND e.answersForgottenAt IS NULL')
            ->setParameter('before', $before)
            ->getResult();

        return $events;
    }

    /**
     * @return list<Registration>
     */
    private function registrationsOf(Event $event): array
    {
        /** @var list<Registration> $registrations */
        $registrations = $this->entityManager->createQuery('SELECT r FROM '.Registration::class.' r WHERE r.event = :event')
            ->setParameter('event', $event)
            ->getResult();

        return $registrations;
    }

    private function anonymize(string $condition, DateTimeImmutable $before, DateTimeImmutable $now): int
    {
        /** @var list<Registration> $registrations */
        $registrations = $this->entityManager->createQuery('SELECT r FROM '.Registration::class.' r JOIN r.event e WHERE e.dateTo < :before AND r.anonymizedAt IS NULL AND '.$condition)
            ->setParameter('before', $before)
            ->getResult();
        foreach ($registrations as $registration) {
            $registration->anonymize($now);
            $registration->log(LogActor::System, null, 'Osobní údaje anonymizovány po uplynutí retenční lhůty.', null, $now);
        }

        return \count($registrations);
    }

    private function forgetUnusedFingerprints(bool $dryRun): int
    {
        /** @var list<array{unitId: int|string}> $rows */
        $rows = $this->entityManager->createQuery('SELECT DISTINCT f.unitId AS unitId FROM '.UnitMemberFingerprint::class.' f')->getArrayResult();
        $count = 0;
        foreach ($rows as $row) {
            $unitId = (int) $row['unitId'];
            $active = (int) $this->entityManager->createQuery('SELECT COUNT(e.id) FROM '.Event::class.' e WHERE e.unitId = :unit AND e.publicRegistration <> :none AND e.state IN (:states)')
                ->setParameter('unit', $unitId)
                ->setParameter('none', PublicRegistration::None->value)
                ->setParameter('states', [EventState::Draft->value, EventState::Open->value])
                ->getSingleScalarResult();
            if ($active > 0) {
                continue;
            }
            if (! $dryRun) {
                $this->fingerprints->forget($unitId);
            }
            ++$count;
        }

        return $count;
    }
}
