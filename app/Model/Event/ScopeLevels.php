<?php

declare(strict_types=1);

namespace App\Model\Event;

/**
 * Which event scopes a unit may organise: a scope is never below the organising unit (a středisko or přístav
 * has no "družinová" event, a družina may organise one for the whole středisko). Unit types (skautIS
 * ID_UnitType) and event scopes (skautIS EventGeneralScope ids) share the levels below; an id not listed
 * is not restricted.
 */
final class ScopeLevels
{
    private const array UNIT_LEVELS = [
        'druzina' => 1,
        'oddil' => 2,
        'klub' => 2,
        'stredisko' => 3,
        'pristav' => 3,
        'zakladni' => 3,
        'okres' => 4,
        'oblast' => 4,
        'kraj' => 5,
        'ustredi' => 6,
        'ustredni' => 6,
    ];

    // 1 Družinová, 2 Oddílová, 3 Středisková, 4 Okresní/oblastní, 5 Krajská, 6 Republiková; 7 Mezinárodní is open to all.
    private const array SCOPE_LEVELS = [
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
        5 => 5,
        6 => 6,
    ];

    /**
     * @param array<int|string, string> $scopes scope id => name
     *
     * @return list<string> the ids of the scopes a unit of this type may choose
     */
    public static function allowedFor(?string $unitType, array $scopes): array
    {
        $ids = array_map(strval(...), array_keys($scopes));
        $unitLevel = self::UNIT_LEVELS[$unitType ?? ''] ?? null;
        if ($unitLevel === null) {
            return $ids;
        }

        return array_values(array_filter($ids, static fn (string $id): bool => (self::SCOPE_LEVELS[$id] ?? $unitLevel) >= $unitLevel));
    }

    /**
     * The scope on the unit's own level (Středisková for a středisko or přístav), null for an unknown unit type.
     *
     * @param array<int|string, string> $scopes scope id => name
     */
    public static function defaultFor(?string $unitType, array $scopes): ?string
    {
        $unitLevel = self::UNIT_LEVELS[$unitType ?? ''] ?? null;
        if ($unitLevel === null) {
            return null;
        }
        foreach (self::allowedFor($unitType, $scopes) as $id) {
            if ((self::SCOPE_LEVELS[$id] ?? null) === $unitLevel) {
                return $id;
            }
        }

        return null;
    }
}
