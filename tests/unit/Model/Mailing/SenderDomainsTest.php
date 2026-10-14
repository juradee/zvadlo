<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Mailing;

use App\Model\Mailing\SenderDomains;
use App\Model\Skautis\Dto\DomainAssignment;
use App\Model\Skautis\Dto\Unit;
use App\Model\Skautis\SkautisGateway;
use App\Model\User\UnitHierarchy;
use Codeception\Test\Unit as UnitTestCase;
use Nette\Caching\Storages\MemoryStorage;
use PHPUnit\Framework\MockObject\MockObject;

final class SenderDomainsTest extends UnitTestCase
{
    /** Okres 1001 > středisko 1002 > oddíl 1003 > družina 1005. */
    private const array PARENTS = [1001 => null, 1002 => 1001, 1003 => 1002, 1005 => 1003];

    private SkautisGateway&MockObject $gateway;

    private SenderDomains $domains;

    protected function _before(): void
    {
        $this->gateway = $this->createMock(SkautisGateway::class);
        $this->gateway->method('getUnit')->willReturnCallback(static fn (int $id): ?Unit => \array_key_exists($id, self::PARENTS) ? new Unit($id, 'Jednotka '.$id, '', 'oddil', self::PARENTS[$id]) : null);
        $this->gateway->method('getUnitDomains')->willReturnCallback(static fn (int $id): array => match ($id) {
            1002 => ['stredisko.cz'],
            1003 => ['vlcata.cz'],
            default => [],
        });
        $this->gateway->method('getAssignedDomains')->willReturnCallback(static fn (int $id): array => match ($id) {
            1001 => [new DomainAssignment('okres.cz', true)],
            1002 => [new DomainAssignment('slunecnihodiny.cz', true), new DomainAssignment('jenstredisko.cz', false)],
            1003 => [new DomainAssignment('tabor-vlcata.cz', false)],
            default => [],
        });
        $this->gateway->method('getPersonDomains')->willReturnCallback(static fn (int $personId): array => $personId === 100 ? ['osobni-skautska.cz'] : []);

        $this->domains = new SenderDomains($this->gateway, new UnitHierarchy($this->gateway, new MemoryStorage()));
    }

    public function testTroopGetsScoutOwnAssignedInheritedAndLeaderDomains(): void
    {
        $allowed = $this->domains->allowedFor(1003, 100);

        sort($allowed);
        self::assertSame(['okres.cz', 'osobni-skautska.cz', 'skaut.cz', 'skautka.cz', 'slunecnihodiny.cz', 'tabor-vlcata.cz', 'vlcata.cz'], $allowed);
    }

    public function testSuperiorUnitsGiveOnlyWhatTheyAssignedWithSubordinates(): void
    {
        $allowed = $this->domains->allowedFor(1003, 999);

        // The středisko owns stredisko.cz and has jenstredisko.cz only for itself: neither flows down.
        self::assertNotContains('stredisko.cz', $allowed);
        self::assertNotContains('jenstredisko.cz', $allowed);
        self::assertNotContains('osobni-skautska.cz', $allowed);
        self::assertContains('slunecnihodiny.cz', $allowed);
    }

    public function testUnitWithoutDomainsKeepsTheScoutDomains(): void
    {
        $allowed = $this->domains->allowedFor(1001, 999);

        self::assertContains('skaut.cz', $allowed);
        self::assertContains('skautka.cz', $allowed);
    }

    public function testDomainsAreReadFromSkautisEveryTime(): void
    {
        $gateway = $this->createMock(SkautisGateway::class);
        $gateway->method('getUnit')->willReturn(null);
        $gateway->expects(self::exactly(2))->method('getUnitDomains')->willReturn([]);
        $gateway->method('getAssignedDomains')->willReturn([]);
        $gateway->method('getPersonDomains')->willReturn([]);
        $domains = new SenderDomains($gateway, new UnitHierarchy($gateway, new MemoryStorage()));

        $domains->allowedFor(1003, 100);
        $domains->allowedFor(1003, 100);
    }

    public function testEmailMustBeInAnAllowedDomain(): void
    {
        $allowed = ['skaut.cz', 'vlcata.cz'];

        self::assertTrue(SenderDomains::isAllowed('vedouci@vlcata.cz', $allowed));
        self::assertTrue(SenderDomains::isAllowed('Alex@SKAUT.cz', $allowed));
        self::assertFalse(SenderDomains::isAllowed('vlcata@gmail.com', $allowed));
        self::assertFalse(SenderDomains::isAllowed('x@mail.vlcata.cz', $allowed));
        self::assertFalse(SenderDomains::isAllowed('skaut.cz', $allowed));
    }

    public function testPatrolsCannotOwnAccounts(): void
    {
        self::assertFalse(SenderDomains::canOwnAccounts('druzina'));
        self::assertTrue(SenderDomains::canOwnAccounts('oddil'));
        self::assertTrue(SenderDomains::canOwnAccounts('stredisko'));
    }
}
