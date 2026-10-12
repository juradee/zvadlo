<?php

declare(strict_types=1);

namespace App\Model\Admin;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class SystemUserRoleRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function hasRole(int $userId, SystemRole $role): bool
    {
        return $this->entityManager->getRepository(SystemUserRole::class)->count(['userId' => $userId, 'role' => $role]) > 0;
    }

    /**
     * @return array<int, list<SystemRole>> roles per skautIS user id
     */
    public function findAllGroupedByUserId(): array
    {
        $grouped = [];
        foreach ($this->entityManager->getRepository(SystemUserRole::class)->findBy([], ['userId' => 'ASC', 'role' => 'ASC']) as $row) {
            $grouped[$row->getUserId()][] = $row->getRole();
        }

        return $grouped;
    }

    /**
     * @param list<SystemRole> $roles
     */
    public function replaceRoles(int $userId, array $roles): void
    {
        $this->removeAllForUser($userId);
        $now = new DateTimeImmutable();
        foreach (array_unique($roles, \SORT_REGULAR) as $role) {
            $this->entityManager->persist(new SystemUserRole($userId, $role, $now));
        }
        $this->entityManager->flush();
    }

    public function removeAllForUser(int $userId): void
    {
        $this->entityManager->createQuery('DELETE FROM '.SystemUserRole::class.' r WHERE r.userId = :user')
            ->setParameter('user', $userId)
            ->execute();
    }
}
