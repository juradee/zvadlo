<?php

declare(strict_types=1);

namespace App\Model\Skautis\Dto;

use DateTimeImmutable;

/**
 * A participant of an event as skautIS lists it (Events.ParticipantGeneralAll / ParticipantCampAll).
 */
final readonly class Participant
{
    public function __construct(
        public int $id,
        public ?int $personId,
        public string $name,
        public ?DateTimeImmutable $birthday,
        public ?int $unitId,
        public string $unitName,
    ) {
    }
}
