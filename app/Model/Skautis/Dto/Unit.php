<?php

declare(strict_types=1);

namespace App\Model\Skautis\Dto;

final readonly class Unit
{
    public function __construct(
        public int $id,
        public string $displayName,
        public string $registrationNumber,
        public string $typeId,
        public ?int $parentId,
    ) {
    }
}
