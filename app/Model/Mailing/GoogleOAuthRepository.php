<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use App\Model\Event\Event;
use App\Model\Unit\UnitSettings;
use Doctrine\ORM\EntityManagerInterface;

class GoogleOAuthRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(string $id): ?GoogleOAuth
    {
        return $this->entityManager->find(GoogleOAuth::class, $id);
    }

    /**
     * Accounts owned by the given units, oldest first.
     *
     * @param list<int> $unitIds
     *
     * @return list<GoogleOAuth>
     */
    public function findByUnits(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        /** @var list<GoogleOAuth> $accounts */
        $accounts = $this->entityManager->getRepository(GoogleOAuth::class)->findBy(['unitId' => $unitIds], ['updatedAt' => 'ASC']);

        return $accounts;
    }

    public function findByUnitAndEmail(int $unitId, string $email): ?GoogleOAuth
    {
        return $this->entityManager->getRepository(GoogleOAuth::class)->findOneBy(['unitId' => $unitId, 'email' => mb_strtolower($email)]);
    }

    public function add(GoogleOAuth $account): void
    {
        $this->entityManager->persist($account);
        $this->entityManager->flush();
    }

    public function remove(GoogleOAuth $account): void
    {
        $this->entityManager->remove($account);
        $this->entityManager->flush();
    }

    /**
     * Events and unit defaults of other units lose the account once its unit stops sharing it.
     */
    public function detachFromOtherUnits(GoogleOAuth $account): void
    {
        $queries = [
            'UPDATE '.Event::class.' e SET e.mailAccount = NULL WHERE e.mailAccount = :account AND e.unitId <> :unit',
            'UPDATE '.UnitSettings::class.' s SET s.defaultMailAccount = NULL WHERE s.defaultMailAccount = :account AND s.unitId <> :unit',
        ];
        foreach ($queries as $dql) {
            $this->entityManager->createQuery($dql)
                ->setParameter('account', $account->getId())
                ->setParameter('unit', $account->getUnitId())
                ->execute();
        }
    }

    public function save(): void
    {
        $this->entityManager->flush();
    }
}
