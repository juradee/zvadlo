<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Event\Event;
use App\Model\Event\EventState;
use App\Model\Event\PublicRegistration;
use App\Model\Mailing\EmailMessage;
use App\Model\Task\Task;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Tracy\ILogger;

/**
 * Retention rules of spec ch. 14, both periods counted from the end of the event. After the synced period every
 * registration loses its answers and note and non-members whose person Zvadlo created in skautIS are anonymised.
 * The unsynced period is the longest one and ends everything personal: non-members never written to skautIS are
 * anonymised, members keep only their name and skautIS ID, the e-mail log loses addresses and texts and the
 * finished tasks (their payload carries addresses and texts) are deleted. Fingerprints of units without an event
 * open to public registration are deleted.
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
        $longestBefore = $now->modify(\sprintf('-%d months', $this->unsyncedMonths));

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
        $unsynced = $this->anonymize('r.skautisPersonId IS NULL', $longestBefore, $now);
        $members = $this->forgetMembers($longestBefore, $now);
        $emails = $this->eraseEmails($longestBefore, $dryRun);
        $tasks = $this->deleteFinishedTasks($longestBefore);
        $fingerprintUnits = $this->forgetUnusedFingerprints($dryRun);

        if ($dryRun) {
            $this->entityManager->clear();
        } else {
            $this->entityManager->flush();
        }

        return new RetentionResult($events, $answers, $synced, $unsynced, $members, $emails, $tasks, $fingerprintUnits);
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

    private function forgetMembers(DateTimeImmutable $before, DateTimeImmutable $now): int
    {
        /** @var list<Registration> $registrations */
        $registrations = $this->entityManager->createQuery('SELECT r FROM '.Registration::class.' r JOIN r.event e WHERE e.dateTo < :before AND r.anonymizedAt IS NULL AND r.skautisPersonId IS NOT NULL AND r.skautisPersonCreatedAt IS NULL')
            ->setParameter('before', $before)
            ->getResult();
        foreach ($registrations as $registration) {
            $registration->forgetPersonalData($now);
            $registration->log(LogActor::System, null, 'Osobní údaje kromě jména a skautIS ID smazány po uplynutí retenční lhůty.', null, $now);
        }

        return \count($registrations);
    }

    /**
     * Keeps type, state and time of each logged e-mail for the statistics. Messages of a deleted event go by the
     * time they were sent. An erased row has an empty address, which also keeps the next run from repeating it.
     */
    private function eraseEmails(DateTimeImmutable $before, bool $dryRun): int
    {
        $where = "m.toEmail <> '' AND (IDENTITY(m.event) IN (SELECT e.id FROM ".Event::class.' e WHERE e.dateTo < :before) OR (m.event IS NULL AND m.sentAt < :before))';
        if ($dryRun) {
            return (int) $this->entityManager->createQuery('SELECT COUNT(m.id) FROM '.EmailMessage::class.' m WHERE '.$where)
                ->setParameter('before', $before)
                ->getSingleScalarResult();
        }

        return (int) $this->entityManager->createQuery('UPDATE '.EmailMessage::class." m SET m.toEmail = '', m.toName = NULL, m.subject = '', m.body = '' WHERE ".$where)
            ->setParameter('before', $before)
            ->execute();
    }

    private function deleteFinishedTasks(DateTimeImmutable $before): int
    {
        /** @var list<Task> $tasks */
        $tasks = $this->entityManager->createQuery('SELECT t FROM '.Task::class.' t JOIN t.event e WHERE e.dateTo < :before AND t.finishedAt IS NOT NULL')
            ->setParameter('before', $before)
            ->getResult();
        foreach ($tasks as $task) {
            $this->entityManager->remove($task);
        }

        return \count($tasks);
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
