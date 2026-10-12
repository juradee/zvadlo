<?php

declare(strict_types=1);

namespace App\Model\Skautis\Dto;

use DateTimeImmutable;

/**
 * A member as listed by OrganizationUnit.PersonAll.
 */
final readonly class Person
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $nickName,
        public ?DateTimeImmutable $birthday,
        public ?string $sex,
        public ?int $unitId,
    ) {
    }

    public function getDisplayName(): string
    {
        $name = trim($this->lastName.' '.$this->firstName);

        return $this->nickName === '' ? $name : $name.' ('.$this->nickName.')';
    }

    public function getAge(DateTimeImmutable $at): ?int
    {
        return $this->birthday?->diff($at)->y;
    }
}
