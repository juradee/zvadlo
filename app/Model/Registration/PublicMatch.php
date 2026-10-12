<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Skautis\Dto\Person;

/**
 * What the fingerprints said about a public sign-up: a matched member, or a hint for the leader.
 */
final readonly class PublicMatch
{
    public function __construct(
        public ?Person $member,
        public ?PairingHint $hint,
    ) {
    }

    public function isMember(): bool
    {
        return $this->member !== null;
    }
}
