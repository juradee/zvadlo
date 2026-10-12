<?php

declare(strict_types=1);

namespace App\Model\Admin;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

#[ORM\Entity]
#[ORM\Table(name: 'system_user_role')]
#[ORM\UniqueConstraint(name: 'uniq_system_user_role', columns: ['user_id', 'role'])]
class SystemUserRole
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** skautIS user id */
    #[ORM\Column]
    private int $userId;

    #[ORM\Column(length: 20, enumType: SystemRole::class)]
    private SystemRole $role;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(int $userId, SystemRole $role, DateTimeImmutable $now)
    {
        $this->userId = $userId;
        $this->role = $role;
        $this->createdAt = $now;
    }

    public function getId(): int
    {
        return $this->id ?? throw new LogicException('The role has not been persisted yet.');
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getRole(): SystemRole
    {
        return $this->role;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
