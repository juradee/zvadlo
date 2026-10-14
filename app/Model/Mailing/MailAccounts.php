<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use App\Model\Skautis\Dto\Unit;
use App\Model\User\UnitHierarchy;

/**
 * Which Gmail accounts a unit may send from: its own, and those of its superior units that allow subordinates.
 */
class MailAccounts
{
    public function __construct(
        private readonly GoogleOAuthRepository $accounts,
        private readonly UnitHierarchy $units,
    ) {
    }

    /**
     * @return list<GoogleOAuth>
     */
    public function availableFor(int $unitId): array
    {
        $available = $this->accounts->findByUnits([$unitId]);
        $ancestorIds = array_map(static fn (Unit $unit): int => $unit->id, $this->units->ancestors($unitId));
        foreach ($this->accounts->findByUnits($ancestorIds) as $account) {
            if ($account->isAllowedForSubunits()) {
                $available[] = $account;
            }
        }

        return $available;
    }

    /**
     * Accounts available to any of the units, each once.
     *
     * @param list<int> $unitIds
     *
     * @return array<string, GoogleOAuth> keyed by account id
     */
    public function availableForAny(array $unitIds): array
    {
        $available = [];
        foreach ($unitIds as $unitId) {
            foreach ($this->availableFor($unitId) as $account) {
                $available[$account->getId()] = $account;
            }
        }

        return $available;
    }

    public function isAvailableFor(GoogleOAuth $account, int $unitId): bool
    {
        foreach ($this->availableFor($unitId) as $available) {
            if ($available->getId() === $account->getId()) {
                return true;
            }
        }

        return false;
    }
}
