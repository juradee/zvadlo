<?php

declare(strict_types=1);

namespace App\Model\User;

use App\Model\Skautis\Dto\SkautisRole;
use App\Model\Skautis\Dto\Unit;
use App\Model\Skautis\SkautisGateway;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

/**
 * Turns a skautIS role into the set of units it may read and edit; the rules are those of h.skauting.
 */
final class UnitAccessResolver
{
    private readonly Cache $cache;

    public function __construct(
        private readonly SkautisGateway $gateway,
        Storage $storage,
    ) {
        $this->cache = new Cache($storage, 'skautis.units');
    }

    public function resolve(SkautisRole $role): UnitAccess
    {
        $units = $role->isBasicUnit() || $role->isTroop()
            ? $this->unitTree($role->unitId)
            : $this->singleUnit($role->unitId);

        if ($role->isOfficer()) {
            return new UnitAccess($units, []);
        }

        if ($role->isLeader() || $role->isAccountant() || $role->isEventManager()) {
            return new UnitAccess($units, $units);
        }

        return new UnitAccess([], []);
    }

    /**
     * @return array<int, Unit>
     */
    private function singleUnit(int $unitId): array
    {
        $unit = $this->gateway->getUnit($unitId);

        return $unit === null ? [] : [$unit->id => $unit];
    }

    /**
     * The unit and all its descendants; the tree changes rarely, so it is cached for a day.
     *
     * @return array<int, Unit>
     */
    private function unitTree(int $unitId): array
    {
        /** @var array<int, Unit> $tree */
        $tree = $this->cache->load('tree-'.$unitId, function (?array &$dependencies) use ($unitId): array {
            $dependencies = [Cache::Expire => '1 day'];

            return $this->collect($unitId);
        });

        return $tree;
    }

    /**
     * @return array<int, Unit>
     */
    private function collect(int $unitId): array
    {
        $units = $this->singleUnit($unitId);
        foreach ($this->gateway->getChildUnits($unitId) as $child) {
            $units += $this->collect($child->id);
        }

        return $units;
    }
}
