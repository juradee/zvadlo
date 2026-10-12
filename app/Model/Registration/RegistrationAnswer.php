<?php

declare(strict_types=1);

namespace App\Model\Registration;

use DateTimeImmutable;

/**
 * What the participant (or parent) filled in; personal data are used for non-members only.
 */
final readonly class RegistrationAnswer
{
    /**
     * @param array<int, string|bool|int|list<int>|null> $fieldValues custom field values keyed by field id
     */
    public function __construct(
        public bool $attending,
        public ?string $declineReason = null,
        public ?string $contactEmail = null,
        public ?string $phone = null,
        public ?string $note = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $nickName = null,
        public ?Sex $sex = null,
        public ?DateTimeImmutable $birthDate = null,
        public ?string $street = null,
        public ?string $city = null,
        public ?string $postcode = null,
        public array $fieldValues = [],
    ) {
    }
}
