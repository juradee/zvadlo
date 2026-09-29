<?php

declare(strict_types=1);

namespace App\Model\User;

use App\Model\Skautis\Dto\SkautisRole;
use App\Model\Skautis\Dto\SkautisUser;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;

/**
 * Typed view of the logged-in user's identity (account, active role, all roles, unit access).
 */
final class CurrentUser
{
    public function __construct(private readonly User $user)
    {
    }

    public function isLoggedIn(): bool
    {
        return $this->user->isLoggedIn() && $this->user->getIdentity() instanceof SimpleIdentity;
    }

    public function getUser(): SkautisUser
    {
        $user = $this->identity()->user;
        \assert($user instanceof SkautisUser);

        return $user;
    }

    public function getRole(): SkautisRole
    {
        $role = $this->identity()->role;
        \assert($role instanceof SkautisRole);

        return $role;
    }

    /**
     * @return list<SkautisRole>
     */
    public function getRoles(): array
    {
        // "roles" is the Nette identity's own property, so the skautIS roles live under another key
        $roles = $this->identity()->getData()['skautisRoles'] ?? [];
        \assert(\is_array($roles));

        return array_values(array_filter($roles, static fn (mixed $role): bool => $role instanceof SkautisRole));
    }

    public function getAccess(): UnitAccess
    {
        $access = $this->identity()->access;
        \assert($access instanceof UnitAccess);

        return $access;
    }

    private function identity(): SimpleIdentity
    {
        $identity = $this->user->getIdentity();
        if (! $identity instanceof SimpleIdentity) {
            throw new NotLoggedInException();
        }

        return $identity;
    }
}
