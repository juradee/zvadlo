<?php

declare(strict_types=1);

namespace App\Model\Registration;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Keyed fingerprints of one member of a unit tree so a public sign-up can be paired without storing names.
 */
#[ORM\Entity]
#[ORM\Table(name: 'unit_member_fingerprint')]
#[ORM\Index(name: 'idx_fingerprint_full', columns: ['unit_id', 'fingerprint_full'])]
#[ORM\Index(name: 'idx_fingerprint_surname', columns: ['unit_id', 'fingerprint_surname'])]
class UnitMemberFingerprint
{
    #[ORM\Id]
    #[ORM\Column]
    private int $unitId;

    #[ORM\Id]
    #[ORM\Column]
    private int $personId;

    #[ORM\Column(length: 64)]
    private string $fingerprintFull;

    #[ORM\Column(length: 64)]
    private string $fingerprintSurname;

    #[ORM\Column]
    private DateTimeImmutable $refreshedAt;

    public function __construct(int $unitId, int $personId, string $fingerprintFull, string $fingerprintSurname, DateTimeImmutable $refreshedAt)
    {
        $this->unitId = $unitId;
        $this->personId = $personId;
        $this->fingerprintFull = $fingerprintFull;
        $this->fingerprintSurname = $fingerprintSurname;
        $this->refreshedAt = $refreshedAt;
    }

    public function getPersonId(): int
    {
        return $this->personId;
    }

    public function getFingerprintFull(): string
    {
        return $this->fingerprintFull;
    }

    public function getFingerprintSurname(): string
    {
        return $this->fingerprintSurname;
    }
}
