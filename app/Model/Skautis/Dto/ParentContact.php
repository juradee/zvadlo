<?php

declare(strict_types=1);

namespace App\Model\Skautis\Dto;

/**
 * E-mail of a parent of a member (OrganizationUnit.PersonParentAll).
 */
final readonly class ParentContact
{
    public const string MOTHER = 'mother';
    public const string FATHER = 'father';

    public function __construct(
        public string $parentTypeId,
        public string $parentType,
        public string $email,
    ) {
    }
}
