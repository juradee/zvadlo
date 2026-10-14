<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Skautis;

use App\Model\Skautis\Dto\SkautisRole;
use Codeception\Test\Unit;

final class SkautisRoleTest extends Unit
{
    public function testClassificationByKey(): void
    {
        // key => [leader, accountant, officer, eventManager, basicUnit, troop]
        $cases = [
            'vedouciOddil' => [true, false, false, false, false, true],
            'vedouciStredisko' => [true, false, false, false, true, false],
            'hospodarStredisko' => [false, true, false, false, true, false],
            'cinovnikOddil' => [false, false, true, false, false, true],
            'spravceAkciStredisko' => [false, false, false, true, true, false],
            'clenOddil' => [false, false, false, false, false, true],
        ];

        foreach ($cases as $key => [$leader, $accountant, $officer, $eventManager, $basicUnit, $troop]) {
            $role = new SkautisRole(1, $key, 'name', 1002, 'unit', 'display', '622.01');

            self::assertSame($leader, $role->isLeader(), $key);
            self::assertSame($accountant, $role->isAccountant(), $key);
            self::assertSame($officer, $role->isOfficer(), $key);
            self::assertSame($eventManager, $role->isEventManager(), $key);
            self::assertSame($basicUnit, $role->isBasicUnit(), $key);
            self::assertSame($troop, $role->isTroop(), $key);
        }
    }
}
