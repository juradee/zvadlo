<?php

declare(strict_types=1);

namespace App\Model\Skautis\Dto;

use DateTimeImmutable;

/**
 * A non-member to be created in skautIS together with the participation; everything the registration collected.
 */
final readonly class NewPerson
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $nickName,
        public ?string $sexId,
        public ?DateTimeImmutable $birthday,
        public ?string $street,
        public ?string $city,
        public ?string $postcode,
    ) {
    }
}
