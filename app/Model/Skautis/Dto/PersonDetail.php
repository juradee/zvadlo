<?php

declare(strict_types=1);

namespace App\Model\Skautis\Dto;

use DateTimeImmutable;

/**
 * A person as the skautIS account sees it (OrganizationUnit.PersonDetail, PersonParentAllPersonChildren):
 * identity plus the main contacts, without a unit.
 */
final readonly class PersonDetail
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $nickName,
        public ?DateTimeImmutable $birthday,
        public ?string $sex,
        public ?string $email,
        public ?string $phone,
    ) {
    }

    public function getDisplayName(): string
    {
        return trim($this->lastName.' '.$this->firstName);
    }

    public function toPerson(): Person
    {
        return new Person($this->id, $this->firstName, $this->lastName, $this->nickName, $this->birthday, $this->sex, null);
    }
}
