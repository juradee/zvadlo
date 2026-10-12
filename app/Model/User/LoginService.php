<?php

declare(strict_types=1);

namespace App\Model\User;

use App\Model\Skautis\Dto\SkautisRole;
use App\Model\Skautis\SkautisException;
use App\Model\Skautis\SkautisGateway;
use DateTimeImmutable;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;

/**
 * Login through skautIS: the token skautIS posts back is verified, the user's roles are loaded,
 * one role is activated and its unit access is computed into the Nette identity.
 *
 * The role is Zvadlo's own choice: the one the user worked with here last time (when remembered), not the
 * one skautIS or another application happens to have active. Unit access lives in the identity and changes
 * only when the user switches the role here.
 */
final class LoginService
{
    private const string EXPIRATION = '+ 29 minutes';

    public function __construct(
        private readonly SkautisGateway $gateway,
        private readonly UnitAccessResolver $accessResolver,
        private readonly UserPreferenceRepository $preferences,
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
        $remembered = $this->preferences->get($account->id)->getRememberedRoleId();
        $role = $this->findRole($roles, $remembered)
            ?? $this->findRole($roles, $this->gateway->getRoleId())
            ?? $roles[0]
            ?? null;
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

        $preference = $this->preferences->get($current->getUser()->id);
        $preference->rememberRole($role->id, new DateTimeImmutable());
        $this->preferences->save($preference);

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

    /**
     * Extends both the skautIS login and the Zvadlo session; false when skautIS no longer accepts the token.
     */
    public function refresh(): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        try {
            $this->gateway->refreshLogin();
        } catch (SkautisException) {
            $this->logout();

            return false;
        }
        $this->user->setExpiration(self::EXPIRATION);

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
        if ($userRoleId === null) {
            return null;
        }

        foreach ($roles as $role) {
            if ($role->id === $userRoleId) {
                return $role;
            }
        }

        return null;
    }
}
