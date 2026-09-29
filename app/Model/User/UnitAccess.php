<?php

declare(strict_types=1);

namespace App\Model\User;

use App\Model\Skautis\Dto\Unit;

/**
 * Units the active role may read and edit, keyed by unit id.
 */
final readonly class UnitAccess
{
    /**
     * @param array<int, Unit> $read
     * @param array<int, Unit> $edit
     */
    public function __construct(
        public array $read,
        public array $edit,
    ) {
    }

    public function canRead(int $unitId): bool
    {
        return isset($this->read[$unitId]);
    }

    public function canEdit(int $unitId): bool
    {
        return isset($this->edit[$unitId]);
    }
}
