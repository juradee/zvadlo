<?php

declare(strict_types=1);

namespace App\Model\User;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * What a user set for their own logins: keep the skautIS login alive while the tab is open and come back
 * to the role (unit) they worked with in Zvadlo, whatever role skautIS hands over at the next login.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_preference')]
class UserPreference
{
    #[ORM\Id]
    #[ORM\Column]
    private int $userId;

    #[ORM\Column]
    private bool $extendLogin = true;

    #[ORM\Column]
    private bool $rememberRole = true;

    #[ORM\Column(nullable: true)]
    private ?int $rememberedRoleId = null;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(int $userId, DateTimeImmutable $now)
    {
        $this->userId = $userId;
        $this->updatedAt = $now;
    }

    public function edit(bool $extendLogin, bool $rememberRole, DateTimeImmutable $now): void
    {
        $this->extendLogin = $extendLogin;
        $this->rememberRole = $rememberRole;
        if (! $rememberRole) {
            $this->rememberedRoleId = null;
        }
        $this->updatedAt = $now;
    }

    public function rememberRole(?int $roleId, DateTimeImmutable $now): void
    {
        $this->rememberedRoleId = $this->rememberRole ? $roleId : null;
        $this->updatedAt = $now;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function shouldExtendLogin(): bool
    {
        return $this->extendLogin;
    }

    public function shouldRememberRole(): bool
    {
        return $this->rememberRole;
    }

    public function getRememberedRoleId(): ?int
    {
        return $this->rememberRole ? $this->rememberedRoleId : null;
    }
}
