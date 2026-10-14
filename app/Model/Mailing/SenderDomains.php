<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use App\Model\Skautis\SkautisGateway;
use App\Model\User\UnitHierarchy;

/**
 * Which domains a unit may connect a sending Gmail account from. Everything comes from skautIS on every call,
 * nothing is cached: the scout domains, the domains the unit owns, domains its superior units assigned to it
 * including subordinates, and the domains of the logged-in leader's Google account.
 */
class SenderDomains
{
    /** @var list<string> */
    public const array SCOUT_DOMAINS = ['skaut.cz', 'skautka.cz'];

    /** Units below a troop (patrols) cannot own an account; they send from what their troop shares. */
    private const array UNIT_TYPES_WITHOUT_ACCOUNTS = ['druzina'];

    public function __construct(
        private readonly SkautisGateway $gateway,
        private readonly UnitHierarchy $units,
    ) {
    }

    public static function canOwnAccounts(string $unitTypeId): bool
    {
        return ! \in_array($unitTypeId, self::UNIT_TYPES_WITHOUT_ACCOUNTS, true);
    }

    /**
     * @return list<string>
     */
    public function allowedFor(int $unitId, int $personId): array
    {
        $domains = [...self::SCOUT_DOMAINS, ...$this->gateway->getUnitDomains($unitId), ...$this->gateway->getPersonDomains($personId)];
        foreach ($this->gateway->getAssignedDomains($unitId) as $assignment) {
            $domains[] = $assignment->domain;
        }
        // Rights flow down: a superior unit's domain counts only where it was assigned with its subordinates.
        foreach ($this->units->ancestors($unitId) as $ancestor) {
            foreach ($this->gateway->getAssignedDomains($ancestor->id) as $assignment) {
                if ($assignment->includeChildUnits) {
                    $domains[] = $assignment->domain;
                }
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * @param list<string> $domains
     */
    public static function isAllowed(string $email, array $domains): bool
    {
        $at = strrpos($email, '@');

        return $at !== false && \in_array(mb_strtolower(substr($email, $at + 1)), $domains, true);
    }
}
