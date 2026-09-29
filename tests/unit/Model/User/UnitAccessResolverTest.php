<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\User;

use App\Model\Skautis\Dto\SkautisRole;
use App\Model\Skautis\Dto\Unit;
use App\Model\Skautis\SkautisGateway;
use App\Model\User\UnitAccessResolver;
use Codeception\Test\Unit as UnitTestCase;
use Nette\Caching\Storages\MemoryStorage;
use PHPUnit\Framework\MockObject\MockObject;

final class UnitAccessResolverTest extends UnitTestCase
{
    private SkautisGateway&MockObject $gateway;

    private UnitAccessResolver $resolver;

    protected function _before(): void
    {
        $this->gateway = $this->createMock(SkautisGateway::class);
        $this->gateway->method('getUnit')->willReturnCallback(fn (int $id): Unit => $this->unit($id));
        $this->gateway->method('getChildUnits')->willReturnCallback(fn (int $id): array => $id === 1002 ? [$this->unit(1003), $this->unit(1004)] : []);

        $this->resolver = new UnitAccessResolver($this->gateway, new MemoryStorage());
    }

    public function testUnitLeaderEditsTheWholeTree(): void
    {
        $access = $this->resolver->resolve(new SkautisRole(11, 'vedouciStredisko', 'Vedoucí střediska', 1002, 'Středisko', 'display'));

        self::assertSame([1002, 1003, 1004], array_keys($access->read));
        self::assertSame([1002, 1003, 1004], array_keys($access->edit));
        self::assertTrue($access->canEdit(1004));
    }

    public function testTroopLeaderEditsOnlyTheTroop(): void
    {
        $access = $this->resolver->resolve(new SkautisRole(10, 'vedouciOddil', 'Vedoucí oddílu', 1003, 'Oddíl', 'display'));

        self::assertSame([1003], array_keys($access->edit));
        self::assertFalse($access->canEdit(1002));
    }

    public function testOfficerOnlyReads(): void
    {
        $access = $this->resolver->resolve(new SkautisRole(12, 'cinovnikStredisko', 'Činovník', 1002, 'Středisko', 'display'));

        self::assertSame([1002, 1003, 1004], array_keys($access->read));
        self::assertSame([], $access->edit);
    }

    public function testOtherRolesGetNothing(): void
    {
        $access = $this->resolver->resolve(new SkautisRole(13, 'clenOddil', 'Člen', 1003, 'Oddíl', 'display'));

        self::assertSame([], $access->read);
        self::assertSame([], $access->edit);
    }

    public function testTreeIsCached(): void
    {
        $gateway = $this->createMock(SkautisGateway::class);
        $gateway->expects(self::exactly(3))->method('getUnit')->willReturnCallback(fn (int $id): Unit => $this->unit($id));
        $gateway->expects(self::exactly(3))->method('getChildUnits')->willReturnCallback(fn (int $id): array => $id === 1002 ? [$this->unit(1003), $this->unit(1004)] : []);
        $resolver = new UnitAccessResolver($gateway, new MemoryStorage());
        $role = new SkautisRole(11, 'vedouciStredisko', 'Vedoucí střediska', 1002, 'Středisko', 'display');

        $resolver->resolve($role);
        $resolver->resolve($role);
    }

    private function unit(int $id): Unit
    {
        return new Unit($id, "Unit $id", '622.01', $id === 1002 ? 'stredisko' : 'oddil', $id === 1002 ? 1001 : 1002);
    }
}
