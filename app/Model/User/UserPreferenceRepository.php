<?php

declare(strict_types=1);

namespace App\Model\User;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class UserPreferenceRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * The user's preferences, defaults when nothing was saved yet (not persisted until save()).
     */
    public function get(int $userId): UserPreference
    {
        return $this->entityManager->find(UserPreference::class, $userId) ?? new UserPreference($userId, new DateTimeImmutable());
    }

    public function save(UserPreference $preference): void
    {
        $this->entityManager->persist($preference);
        $this->entityManager->flush();
    }
}
