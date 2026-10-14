<?php

declare(strict_types=1);

namespace App\Model\User;

use App\Model\Skautis\Dto\Unit;
use App\Model\Skautis\SkautisGateway;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

/**
 * Units and their superior units from skautIS, cached for a day like the unit tree of a role.
 */
class UnitHierarchy
{
    private readonly Cache $cache;

    public function __construct(
        private readonly SkautisGateway $gateway,
        Storage $storage,
    ) {
        $this->cache = new Cache($storage, 'skautis.units');
    }

    public function unit(int $unitId): ?Unit
    {
        /** @var Unit|false $unit */
        $unit = $this->cache->load('unit-'.$unitId, function (?array &$dependencies) use ($unitId): Unit|false {
            $dependencies = [Cache::Expire => '1 day'];

            return $this->gateway->getUnit($unitId) ?? false;
        });

        return $unit === false ? null : $unit;
    }

    /**
     * Superior units, the nearest first.
     *
     * @return list<Unit>
     */
    public function ancestors(int $unitId): array
    {
        $ancestors = [];
        $parentId = $this->unit($unitId)?->parentId;
        // The tree has about six levels; the cap only stops a cycle in bad data.
        while ($parentId !== null && \count($ancestors) < 10) {
            $parent = $this->unit($parentId);
            if ($parent === null) {
                break;
            }
            $ancestors[] = $parent;
            $parentId = $parent->parentId;
        }

        return $ancestors;
    }
}
