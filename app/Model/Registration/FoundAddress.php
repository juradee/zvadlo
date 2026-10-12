<?php

declare(strict_types=1);

namespace App\Model\Registration;

/**
 * An e-mail skautIS knows for a member, offered when the leader invites them; $selected is the default choice.
 */
final readonly class FoundAddress
{
    public function __construct(
        public string $email,
        public ?string $name,
        public RecipientSource $source,
        public bool $selected,
    ) {
    }
}
