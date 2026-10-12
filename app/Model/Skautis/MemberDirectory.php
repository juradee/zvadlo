<?php

declare(strict_types=1);

namespace App\Model\Skautis;

use App\Model\Skautis\Dto\Person;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

/**
 * Members of a unit tree, cached for an hour; the list can be refreshed on demand.
 */
final class MemberDirectory
{
    private readonly Cache $cache;

    public function __construct(
        private readonly SkautisGateway $gateway,
        Storage $storage,
    ) {
        $this->cache = new Cache($storage, 'skautis.members');
    }

    /**
     * @return list<Person>
     */
    public function getMembers(int $unitId, bool $refresh = false): array
    {
        $key = 'unit-'.$unitId;
        if ($refresh) {
            $this->cache->remove($key);
        }

        /** @var list<Person> $members */
        $members = $this->cache->load($key, function (?array &$dependencies) use ($unitId): array {
            $dependencies = [Cache::Expire => '1 hour'];
            $members = $this->withUnits($unitId);
            usort($members, static fn (Person $a, Person $b): int => [$a->lastName, $a->firstName] <=> [$b->lastName, $b->firstName]);

            return $members;
        });

        return $members;
    }

    /**
     * The cached list only, without asking skautIS; null when the cache is cold. Public pages have no
     * skautIS token, so they must never trigger the load.
     *
     * @return list<Person>|null
     */
    public function getMembersIfCached(int $unitId): ?array
    {
        /** @var list<Person>|null $members */
        $members = $this->cache->load('unit-'.$unitId);

        return $members;
    }

    /**
     * PersonAll does not say which unit of the tree a person belongs to, so the direct members of every
     * sub-unit are asked for separately (one call per unit) and the rest belong to the root.
     *
     * @return list<Person>
     */
    private function withUnits(int $unitId): array
    {
        $unitOf = [];
        foreach ($this->childUnits($unitId) as $childId) {
            foreach ($this->gateway->getUnitMembers($childId, includeSubunits: false) as $person) {
                $unitOf[$person->id] ??= $childId;
            }
        }

        $members = [];
        foreach ($this->gateway->getUnitMembers($unitId, includeSubunits: true) as $person) {
            $members[] = new Person(
                $person->id,
                $person->firstName,
                $person->lastName,
                $person->nickName,
                $person->birthday,
                $person->sex,
                $person->unitId ?? $unitOf[$person->id] ?? $unitId,
            );
        }

        return $members;
    }

    /**
     * @return list<int> ids of all units below the given one
     */
    private function childUnits(int $unitId): array
    {
        $ids = [];
        foreach ($this->gateway->getChildUnits($unitId) as $child) {
            $ids[] = $child->id;
            foreach ($this->childUnits($child->id) as $grandChild) {
                $ids[] = $grandChild;
            }
        }

        return $ids;
    }
}
