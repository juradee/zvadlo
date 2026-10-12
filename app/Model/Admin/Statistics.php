<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\BugReport\TechnicalErrorReport;
use App\Model\Event\Event;
use App\Model\Mailing\EmailMessage;
use App\Model\Mailing\EmailMessageState;
use App\Model\Mailing\GoogleOAuth;
use App\Model\Registration\Registration;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Counts for the administration: what the system carries and what happened per month.
 */
final class Statistics
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array<string, int>
     */
    public function totals(): array
    {
        return [
            'jednotek s akcí' => (int) $this->entityManager->createQuery('SELECT COUNT(DISTINCT e.unitId) FROM '.Event::class.' e')->getSingleScalarResult(),
            'akcí' => (int) $this->entityManager->createQuery('SELECT COUNT(e.id) FROM '.Event::class.' e')->getSingleScalarResult(),
            'přihlášek' => (int) $this->entityManager->createQuery('SELECT COUNT(r.id) FROM '.Registration::class.' r')->getSingleScalarResult(),
            'odeslaných e-mailů' => (int) $this->entityManager->createQuery('SELECT COUNT(m.id) FROM '.EmailMessage::class.' m WHERE m.state = :sent')->setParameter('sent', EmailMessageState::Sent)->getSingleScalarResult(),
            'propojených Gmail účtů' => (int) $this->entityManager->createQuery('SELECT COUNT(g.id) FROM '.GoogleOAuth::class.' g')->getSingleScalarResult(),
            'otevřených hlášení chyb' => (int) $this->entityManager->createQuery('SELECT COUNT(t.id) FROM '.TechnicalErrorReport::class.' t WHERE t.resolvedAt IS NULL')->getSingleScalarResult(),
        ];
    }

    /**
     * @return array<int, array{events: int, registrations: int, emailsSent: int, emailsFailed: int, bugReports: int}>
     */
    public function perMonth(int $year): array
    {
        $events = $this->countByMonth('SELECT e.createdAt AS moment FROM '.Event::class.' e WHERE e.createdAt >= :from AND e.createdAt < :to', $year);
        $registrations = $this->countByMonth('SELECT r.createdAt AS moment FROM '.Registration::class.' r WHERE r.createdAt >= :from AND r.createdAt < :to', $year);
        $bugReports = $this->countByMonth('SELECT t.createdAt AS moment FROM '.TechnicalErrorReport::class.' t WHERE t.createdAt >= :from AND t.createdAt < :to', $year);
        $mails = 'SELECT m.sentAt AS moment FROM '.EmailMessage::class.' m WHERE m.state = :state AND m.sentAt >= :from AND m.sentAt < :to';
        $sent = $this->countByMonth($mails, $year, ['state' => EmailMessageState::Sent]);
        $failed = $this->countByMonth($mails, $year, ['state' => EmailMessageState::Failed]);

        $months = [];
        for ($month = 1; $month <= 12; ++$month) {
            $months[$month] = [
                'events' => $events[$month] ?? 0,
                'registrations' => $registrations[$month] ?? 0,
                'emailsSent' => $sent[$month] ?? 0,
                'emailsFailed' => $failed[$month] ?? 0,
                'bugReports' => $bugReports[$month] ?? 0,
            ];
        }

        return $months;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<int, int> month number => count
     */
    private function countByMonth(string $dql, int $year, array $parameters = []): array
    {
        $query = $this->entityManager->createQuery($dql)
            ->setParameter('from', new DateTimeImmutable(\sprintf('%d-01-01', $year)))
            ->setParameter('to', new DateTimeImmutable(\sprintf('%d-01-01', $year + 1)));
        foreach ($parameters as $name => $value) {
            $query->setParameter($name, $value);
        }

        $counts = [];
        /** @var list<array{moment: DateTimeImmutable}> $rows */
        $rows = $query->getArrayResult();
        foreach ($rows as $row) {
            $month = (int) $row['moment']->format('n');
            $counts[$month] = ($counts[$month] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Units that use the system: events, accounts and the last change.
     *
     * @return list<array{unitId: int, events: int, lastActivity: DateTimeImmutable|null, accounts: list<string>}>
     */
    public function units(): array
    {
        /** @var list<array{unitId: int, events: int|string, lastActivity: string|null}> $rows */
        $rows = $this->entityManager->createQuery('SELECT e.unitId AS unitId, COUNT(e.id) AS events, MAX(e.updatedAt) AS lastActivity FROM '.Event::class.' e GROUP BY e.unitId ORDER BY lastActivity DESC')->getArrayResult();
        $accounts = [];
        foreach ($this->entityManager->getRepository(GoogleOAuth::class)->findAll() as $account) {
            $accounts[$account->getUnitId()][] = $account->getEmail();
        }

        return array_map(static fn (array $row): array => [
            'unitId' => (int) $row['unitId'],
            'events' => (int) $row['events'],
            'lastActivity' => $row['lastActivity'] === null ? null : new DateTimeImmutable($row['lastActivity']),
            'accounts' => $accounts[(int) $row['unitId']] ?? [],
        ], $rows);
    }
}
