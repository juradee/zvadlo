<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Event;

use App\Model\Event\ScopeLevels;
use Codeception\Test\Unit;

final class ScopeLevelsTest extends Unit
{
    // The skautIS code list as it arrives: numeric ids become int keys.
    private const array SCOPES = [1 => 'Družinová', 2 => 'Oddílová', 3 => 'Středisková', 4 => 'Okresní/oblastní', 5 => 'Krajská', 6 => 'Republiková', 7 => 'Mezinárodní'];

    public function testScopeIsNeverBelowTheOrganisingUnit(): void
    {
        self::assertSame(['3', '4', '5', '6', '7'], ScopeLevels::allowedFor('stredisko', self::SCOPES), 'a středisko has no družinová or oddílová event; Mezinárodní is open to all');
        self::assertSame(['3', '4', '5', '6', '7'], ScopeLevels::allowedFor('pristav', self::SCOPES), 'a přístav is on the level of a středisko');
        self::assertSame(['2', '3', '4', '5', '6', '7'], ScopeLevels::allowedFor('oddil', self::SCOPES));
        self::assertSame(['1', '2', '3', '4', '5', '6', '7'], ScopeLevels::allowedFor('druzina', self::SCOPES), 'a družina may organise for the whole středisko');
        self::assertSame(['1', '2', '3', '4', '5', '6', '7'], ScopeLevels::allowedFor('neznamy-typ', self::SCOPES), 'an unknown unit type is not restricted');
        self::assertSame(['1', '2', '3', '4', '5', '6', '7'], ScopeLevels::allowedFor(null, self::SCOPES));
    }

    public function testDefaultScopeIsTheUnitsOwnLevel(): void
    {
        self::assertSame('3', ScopeLevels::defaultFor('stredisko', self::SCOPES));
        self::assertSame('3', ScopeLevels::defaultFor('pristav', self::SCOPES));
        self::assertSame('2', ScopeLevels::defaultFor('oddil', self::SCOPES));
        self::assertSame('1', ScopeLevels::defaultFor('druzina', self::SCOPES));
        self::assertSame('4', ScopeLevels::defaultFor('okres', self::SCOPES));
        self::assertNull(ScopeLevels::defaultFor('neznamy-typ', self::SCOPES), 'an unknown unit type gets no preset');
        self::assertNull(ScopeLevels::defaultFor(null, self::SCOPES));
    }
}
