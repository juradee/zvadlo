<?php

declare(strict_types=1);

namespace App\Model\BugReport;

use Doctrine\ORM\EntityManagerInterface;

class TechnicalErrorReportRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(int $id): ?TechnicalErrorReport
    {
        return $this->entityManager->find(TechnicalErrorReport::class, $id);
    }

    public function findUnresolved(int $id): ?TechnicalErrorReport
    {
        $report = $this->find($id);

        return $report === null || $report->isResolved() ? null : $report;
    }

    /**
     * @return list<TechnicalErrorReport>
     */
    public function findOpen(): array
    {
        /** @var list<TechnicalErrorReport> $reports */
        $reports = $this->entityManager->getRepository(TechnicalErrorReport::class)->findBy(['resolvedAt' => null], ['createdAt' => 'DESC', 'id' => 'DESC']);

        return $reports;
    }

    /**
     * @return list<TechnicalErrorReport>
     */
    public function findResolved(int $limit = 50): array
    {
        /** @var list<TechnicalErrorReport> $reports */
        $reports = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(TechnicalErrorReport::class, 'r')
            ->where('r.resolvedAt IS NOT NULL')
            ->orderBy('r.resolvedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $reports;
    }

    public function add(TechnicalErrorReport $report): void
    {
        $this->entityManager->persist($report);
        $this->entityManager->flush();
    }

    public function save(): void
    {
        $this->entityManager->flush();
    }
}
