<?php

declare(strict_types=1);

namespace App\Model\Auth;

use App\Model\User\CurrentUser;

/**
 * Every permission check of the application goes through here; presenters never ask skautIS themselves.
 */
final class Authorizator
{
    public function __construct(private readonly CurrentUser $currentUser)
    {
    }

    public function canReadUnit(int $unitId): bool
    {
        return $this->currentUser->isLoggedIn() && $this->currentUser->getAccess()->canRead($unitId);
    }

    public function canEditUnit(int $unitId): bool
    {
        return $this->currentUser->isLoggedIn() && $this->currentUser->getAccess()->canEdit($unitId);
    }
}
