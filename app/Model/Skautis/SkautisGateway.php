<?php

declare(strict_types=1);

namespace App\Model\Skautis;

use App\Model\Skautis\Dto\SkautisRole;
use App\Model\Skautis\Dto\SkautisUser;
use App\Model\Skautis\Dto\Unit;

/**
 * The only place the application talks to skautIS. One method per purpose, typed DTOs in and out.
 */
interface SkautisGateway
{
    public function getLoginUrl(string $backlink = ''): string;

    public function getLogoutUrl(): string;

    /**
     * Stores the token skautIS posted back after login.
     *
     * @param array<string, mixed> $post
     *
     * @throws SkautisException when the post does not contain a token
     */
    public function login(array $post): void;

    /**
     * Whether the stored token is still valid; extends the login when it is about to expire.
     */
    public function isLoggedIn(): bool;

    public function logout(): void;

    public function getRoleId(): ?int;

    public function getUnitId(): ?int;

    public function getCurrentUser(): SkautisUser;

    /**
     * @return list<SkautisRole>
     */
    public function getUserRoles(int $userId): array;

    /**
     * Switches the active role; returns the unit of the new role.
     */
    public function switchRole(int $userRoleId): int;

    public function getUnit(int $unitId): ?Unit;

    /**
     * @return list<Unit>
     */
    public function getChildUnits(int $unitId): array;

    /**
     * Types of general events (Events.EventGeneralTypeAll).
     *
     * @return array<string, string> id => name
     */
    public function getEventTypes(): array;

    /**
     * Scopes of general events (Events.EventGeneralScopeAll).
     *
     * @return array<string, string> id => name
     */
    public function getEventScopes(): array;
}
