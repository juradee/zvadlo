<?php

declare(strict_types=1);

namespace App\Model\Mailing;

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
     * Accounts of the given units (the event's unit and its parent), oldest first.
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

    public function save(): void
    {
        $this->entityManager->flush();
    }
}
