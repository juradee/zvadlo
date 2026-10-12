<?php

declare(strict_types=1);

namespace App\Model\Skautis\Dto;

use DateTimeImmutable;

/**
 * A camp as listed by Events.EventCampAll; camps are created in skautIS only.
 */
final readonly class Camp
{
    public function __construct(
        public int $id,
        public string $name,
        public int $unitId,
        public string $unitName,
        public DateTimeImmutable $dateFrom,
        public DateTimeImmutable $dateTo,
        public string $location,
        public string $stateId,
        public string $stateName,
    ) {
    }
}
