<?php

declare(strict_types=1);

namespace App\Model\Unit;

use Doctrine\ORM\EntityManagerInterface;

class UnitSettingsRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Settings of the unit, defaults when nothing was saved yet (not persisted until save()).
     */
    public function get(int $unitId): UnitSettings
    {
        return $this->entityManager->find(UnitSettings::class, $unitId) ?? new UnitSettings($unitId);
    }

    public function save(UnitSettings $settings): void
    {
        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }
}
