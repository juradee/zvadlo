<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\User\CurrentUser;

/**
 * Who may enter the administration: users with a stored system role, plus the admin ids from the
 * configuration as the emergency way in (ADMIN_USER_IDS), exactly like h.skauting.
 */
final class AdminAccess
{
    /** @var list<int> */
    private readonly array $configuredAdminIds;

    /**
     * @param list<int|string> $configuredAdminIds
     */
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly SystemUserRoleRepository $roles,
        array $configuredAdminIds,
    ) {
        $ids = array_values(array_unique(array_map(intval(...), $configuredAdminIds)));
        sort($ids);
        $this->configuredAdminIds = $ids;
    }

    /**
     * @return list<int>
     */
    public function getConfiguredAdminIds(): array
    {
        return $this->configuredAdminIds;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(SystemRole::Admin);
    }

    public function canAccessAdministration(): bool
    {
        return $this->hasRole(SystemRole::Admin) || $this->hasRole(SystemRole::Support);
    }

    public function canAccessBugReports(): bool
    {
        return $this->canAccessAdministration();
    }

    public function hasRole(SystemRole $role): bool
    {
        if (! $this->currentUser->isLoggedIn()) {
            return false;
        }
        $userId = $this->currentUser->getUser()->id;
        if ($role === SystemRole::Admin && \in_array($userId, $this->configuredAdminIds, true)) {
            return true;
        }

        return $this->roles->hasRole($userId, $role);
    }
}
