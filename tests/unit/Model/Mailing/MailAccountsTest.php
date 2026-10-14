<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Mailing;

use App\Model\Mailing\GoogleOAuth;
use App\Model\Mailing\GoogleOAuthRepository;
use App\Model\Mailing\MailAccounts;
use App\Model\Skautis\Dto\Unit;
use App\Model\Skautis\SkautisGateway;
use App\Model\User\UnitHierarchy;
use Codeception\Test\Unit as UnitTestCase;
use DateTimeImmutable;
use Nette\Caching\Storages\MemoryStorage;

final class MailAccountsTest extends UnitTestCase
{
    /** Okres 1001 > středisko 1002 > oddíly 1003 a 1004. */
    private const array PARENTS = [1001 => null, 1002 => 1001, 1003 => 1002, 1004 => 1002];

    /** @var array<int, list<GoogleOAuth>> */
    private array $owned = [];

    private MailAccounts $accounts;

    protected function _before(): void
    {
        $now = new DateTimeImmutable();
        $district = new GoogleOAuth(1001, 'okres@skaut.cz', 'x', $now);
        $district->allowForSubunits();
        $this->owned = [
            1001 => [$district],
            1002 => [new GoogleOAuth(1002, 'stredisko@skaut.cz', 'x', $now)],
            1003 => [new GoogleOAuth(1003, 'vlcata@skaut.cz', 'x', $now)],
            1004 => [new GoogleOAuth(1004, 'skauti@skaut.cz', 'x', $now)],
        ];

        $repository = $this->createMock(GoogleOAuthRepository::class);
        $repository->method('findByUnits')->willReturnCallback(fn (array $ids): array => array_merge(...array_map(fn (int $id): array => $this->owned[$id] ?? [], $ids)));
        $gateway = $this->createMock(SkautisGateway::class);
        $gateway->method('getUnit')->willReturnCallback(static fn (int $id): ?Unit => \array_key_exists($id, self::PARENTS) ? new Unit($id, 'Jednotka '.$id, '', 'oddil', self::PARENTS[$id]) : null);

        $this->accounts = new MailAccounts($repository, new UnitHierarchy($gateway, new MemoryStorage()));
    }

    public function testUnitUsesItsOwnAccountsAndSharedOnesOfSuperiorUnits(): void
    {
        $emails = array_map(static fn (GoogleOAuth $account): string => $account->getEmail(), $this->accounts->availableFor(1003));

        self::assertSame(['vlcata@skaut.cz', 'okres@skaut.cz'], $emails);
    }

    public function testSuperiorAccountIsAvailableOnlyWhenShared(): void
    {
        $stredisko = $this->owned[1002][0];

        self::assertFalse($this->accounts->isAvailableFor($stredisko, 1003));
        $stredisko->allowForSubunits();
        self::assertTrue($this->accounts->isAvailableFor($stredisko, 1003));
        self::assertTrue($this->accounts->isAvailableFor($stredisko, 1002));
    }

    public function testSharingNeverReachesSiblingsOrSuperiors(): void
    {
        $troop = $this->owned[1003][0];
        $troop->allowForSubunits();

        self::assertFalse($this->accounts->isAvailableFor($troop, 1004));
        self::assertFalse($this->accounts->isAvailableFor($troop, 1002));
    }

    public function testAccountsOfSeveralUnitsAreListedOnce(): void
    {
        $available = $this->accounts->availableForAny([1003, 1004]);

        self::assertCount(3, $available);
    }
}
