<?php

declare(strict_types=1);

namespace App\Model\Skautis\Dto;

use DateTimeImmutable;

/**
 * What skautIS needs to create a general event (Events.EventGeneralInsert), the same set h.skauting sends.
 */
final readonly class NewEvent
{
    public function __construct(
        public int $unitId,
        public string $name,
        public DateTimeImmutable $dateFrom,
        public DateTimeImmutable $dateTo,
        public ?string $location,
        public string $typeId,
        public string $scopeId,
    ) {
    }
}
