<?php

declare(strict_types=1);

namespace App\Tests\Unit\App;

use App\MaintenanceMode;
use Codeception\Test\Unit;

final class MaintenanceModeTest extends Unit
{
    public function testAddressesAndRangesOfBothFamilies(): void
    {
        $allowed = ['203.0.113.7', ' 10.0.0.0/8 ', '2001:db8::/32', '', 'not-an-ip', '192.168.1.0/33'];

        self::assertTrue(MaintenanceMode::isClientAllowed('203.0.113.7', $allowed));
        self::assertFalse(MaintenanceMode::isClientAllowed('203.0.113.8', $allowed));
        self::assertTrue(MaintenanceMode::isClientAllowed('10.20.30.40', $allowed));
        self::assertFalse(MaintenanceMode::isClientAllowed('11.0.0.1', $allowed));
        self::assertTrue(MaintenanceMode::isClientAllowed('2001:db8:1::5', $allowed));
        self::assertFalse(MaintenanceMode::isClientAllowed('2001:db9::1', $allowed));
        self::assertFalse(MaintenanceMode::isClientAllowed('192.168.1.5', $allowed), 'an invalid prefix never matches');
        self::assertFalse(MaintenanceMode::isClientAllowed('', $allowed));
        self::assertFalse(MaintenanceMode::isClientAllowed('garbage', $allowed));
        self::assertFalse(MaintenanceMode::isClientAllowed('10.0.0.1', []));
    }

    public function testPartialByteOfThePrefix(): void
    {
        self::assertTrue(MaintenanceMode::isClientAllowed('192.168.1.130', ['192.168.1.128/25']));
        self::assertFalse(MaintenanceMode::isClientAllowed('192.168.1.100', ['192.168.1.128/25']));
        self::assertTrue(MaintenanceMode::isClientAllowed('1.2.3.4', ['0.0.0.0/0']));
    }
}
