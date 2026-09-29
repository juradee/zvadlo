<?php

declare(strict_types=1);

namespace App\Model\Skautis\Dto;

/**
 * The logged-in skautIS account (UserManagement.UserDetail).
 */
final readonly class SkautisUser
{
    public function __construct(
        public int $id,
        public int $personId,
        public string $userName,
        public string $displayName,
    ) {
    }
}
