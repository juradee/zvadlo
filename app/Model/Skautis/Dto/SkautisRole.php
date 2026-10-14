<?php

declare(strict_types=1);

namespace App\Model\Skautis\Dto;

/**
 * One role of the user in skautIS (UserManagement.UserRoleAll). The role key decides what the user may do,
 * the same way h.skauting reads it.
 */
final readonly class SkautisRole
{
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public int $unitId,
        public string $unitName,
        public string $displayName,
        public string $unitRegistrationNumber,
    ) {
    }

    public function isLeader(): bool
    {
        return str_starts_with($this->key, 'vedouci');
    }

    public function isAccountant(): bool
    {
        return str_starts_with($this->key, 'hospodar');
    }

    public function isOfficer(): bool
    {
        return str_starts_with($this->key, 'cinovnik');
    }

    public function isEventManager(): bool
    {
        return str_starts_with($this->key, 'spravceAkci');
    }

    /** Středisko or a higher organisational unit. */
    public function isBasicUnit(): bool
    {
        return str_ends_with($this->key, 'Stredisko');
    }

    public function isTroop(): bool
    {
        return str_ends_with($this->key, 'Oddil');
    }
}
