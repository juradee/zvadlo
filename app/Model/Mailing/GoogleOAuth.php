<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nette\Utils\Random;

/**
 * A unit's connected Gmail account; only the encrypted refresh token is kept.
 */
#[ORM\Entity]
#[ORM\Table(name: 'google_oauth')]
#[ORM\UniqueConstraint(name: 'uniq_google_oauth_unit_email', columns: ['unit_id', 'email'])]
class GoogleOAuth
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;

    #[ORM\Column]
    private int $unitId;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 1000)]
    private string $refreshTokenEncrypted;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /** Subordinate units may send from the account only when its unit allows it (like bank accounts in h.skauting). */
    #[ORM\Column(options: ['default' => false])]
    private bool $allowedForSubunits = false;

    public function __construct(int $unitId, string $email, string $refreshTokenEncrypted, DateTimeImmutable $now)
    {
        $this->id = self::uuid();
        $this->unitId = $unitId;
        $this->email = mb_strtolower($email);
        $this->refreshTokenEncrypted = $refreshTokenEncrypted;
        $this->updatedAt = $now;
    }

    public function replaceToken(string $refreshTokenEncrypted, DateTimeImmutable $now): void
    {
        $this->refreshTokenEncrypted = $refreshTokenEncrypted;
        $this->updatedAt = $now;
    }

    public function allowForSubunits(): void
    {
        $this->allowedForSubunits = true;
    }

    public function disallowForSubunits(): void
    {
        $this->allowedForSubunits = false;
    }

    public function isAllowedForSubunits(): bool
    {
        return $this->allowedForSubunits;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUnitId(): int
    {
        return $this->unitId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRefreshTokenEncrypted(): string
    {
        return $this->refreshTokenEncrypted;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function uuid(): string
    {
        $hex = Random::generate(32, '0-9a-f');

        return \sprintf('%s-%s-4%s-%s%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 13, 3), dechex(8 + random_int(0, 3)), substr($hex, 17, 3), substr($hex, 20, 12));
    }
}
