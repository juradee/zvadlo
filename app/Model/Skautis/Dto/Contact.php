<?php

declare(strict_types=1);

namespace App\Model\Skautis\Dto;

/**
 * One contact of a person (OrganizationUnit.PersonContactAll), e.g. email_hlavni or telefon_hlavni.
 */
final readonly class Contact
{
    public const string EMAIL = 'email_hlavni';
    public const string PHONE = 'telefon_hlavni';

    public function __construct(
        public string $typeId,
        public string $value,
    ) {
    }
}
