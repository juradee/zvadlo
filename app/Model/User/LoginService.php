<?php

declare(strict_types=1);

namespace App\Model\User;

use App\Model\Skautis\Dto\SkautisRole;
use App\Model\Skautis\SkautisGateway;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;

/**
 * Login through skautIS: the token skautIS posts back is verified, the user's roles are loaded,
 * one role is activated and its unit access is computed into the Nette identity.
 */
final class LoginService
{
    private const string EXPIRATION = '+ 29 minutes';

    public function __construct(
        private readonly SkautisGateway $gateway,
        private readonly UnitAccessResolver $accessResolver,
        private readonly User $user,
    ) {
    }

    /**
     * @param array<string, mixed> $post fields skautIS sent: skautIS_Token, skautIS_IDRole, skautIS_IDUnit, skautIS_DateLogout
     *
     * @throws LoginFailedException
     */
    public function loginFromSkautis(array $post): void
    {
        $this->gateway->login($post);

        if (! $this->gateway->isLoggedIn()) {
            throw new LoginFailedException('skautIS did not confirm the login.');
        }

        $account = $this->gateway->getCurrentUser();
        $roles = $this->gateway->getUserRoles($account->id);
        $role = $this->findRole($roles, $this->gateway->getRoleId()) ?? $roles[0] ?? null;
        if ($role === null) {
            $this->gateway->logout();
            throw new LoginFailedException('The user has no active role in skautIS.');
        }

        if ($role->id !== $this->gateway->getRoleId()) {
            $this->gateway->switchRole($role->id);
        }

        $this->user->login(new SimpleIdentity($account->id, [], [
            'user' => $account,
            'role' => $role,
            'skautisRoles' => $roles,
            'access' => $this->accessResolver->resolve($role),
        ]));
        $this->user->setExpiration(self::EXPIRATION);
    }

    /**
     * @throws LoginFailedException when the role does not belong to the user
     */
    public function switchRole(int $userRoleId): void
    {
        $current = new CurrentUser($this->user);
        $role = $this->findRole($current->getRoles(), $userRoleId);
        if ($role === null) {
            throw new LoginFailedException('The role does not belong to the user.');
        }

        $this->gateway->switchRole($role->id);

        $this->user->login(new SimpleIdentity($current->getUser()->id, [], [
            'user' => $current->getUser(),
            'role' => $role,
            'skautisRoles' => $current->getRoles(),
            'access' => $this->accessResolver->resolve($role),
        ]));
        $this->user->setExpiration(self::EXPIRATION);
    }

    /**
     * Whether the session and the skautIS token are both still valid; logs out otherwise.
     */
    public function isValid(): bool
    {
        if (! $this->user->isLoggedIn()) {
            return false;
        }

        if (! $this->gateway->isLoggedIn()) {
            $this->logout();

            return false;
        }

        return true;
    }

    public function logout(): void
    {
        $this->gateway->logout();
        $this->user->logout(true);
    }

    /**
     * @param list<SkautisRole> $roles
     */
    private function findRole(array $roles, ?int $userRoleId): ?SkautisRole
    {
        foreach ($roles as $role) {
            if ($role->id === $userRoleId) {
                return $role;
            }
        }

        return null;
    }
}
