<?php

declare(strict_types=1);

namespace App\Model\Event;

use DateTimeImmutable;

/**
 * Values of the event form, validated by the form; the entity applies them.
 */
final readonly class EventInput
{
    public function __construct(
        public string $name,
        public ?string $skautisTypeId,
        public ?string $skautisScopeId,
        public DateTimeImmutable $dateFrom,
        public DateTimeImmutable $dateTo,
        public ?string $place,
        public ?string $description,
        public ?DateTimeImmutable $registrationDeadline,
        public ?int $capacity,
        public ?int $price,
        public string $contactName,
        public string $contactEmail,
        public ?string $contactPhone,
        public PublicRegistration $publicRegistration = PublicRegistration::None,
        public int $lockNoticeDays = 2,
        public bool $waitlistEnabled = true,
    ) {
    }
}
