<?php

declare(strict_types=1);

namespace App\Model\Skautis\Dto;

/**
 * A domain of the scout Google Workspace assigned to a unit, optionally with its subordinate units.
 */
final readonly class DomainAssignment
{
    public function __construct(
        public string $domain,
        public bool $includeChildUnits,
    ) {
    }
}
