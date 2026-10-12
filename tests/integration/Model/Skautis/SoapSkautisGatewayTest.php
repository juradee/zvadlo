<?php

declare(strict_types=1);

namespace App\Tests\Integration\Model\Skautis;

use App\Model\Skautis\SkautisGateway;
use App\Tests\Support\IntegrationTester;
use Codeception\Test\Unit;

/**
 * The real gateway over the skautIS fixtures: mapping of responses to DTOs and the login state.
 */
final class SoapSkautisGatewayTest extends Unit
{
    protected IntegrationTester $tester;

    private SkautisGateway $gateway;

    protected function _before(): void
    {
        $this->gateway = $this->tester->grabService(SkautisGateway::class);
        $this->gateway->logout();
    }

    public function testTemporaryLoginRestoresTheLeaderLogin(): void
    {
        $skautis = $this->tester->grabService(\Skaut\Skautis\Skautis::class);
        \assert($skautis instanceof \Skaut\Skautis\Skautis);
        $this->gateway->login(['skautIS_Token' => '00000000-0000-4000-8000-000000000001', 'skautIS_IDRole' => '10', 'skautIS_IDUnit' => '1003', 'skautIS_DateLogout' => '31. 12. 2099 23:59:59']);

        $seen = $this->gateway->withTemporaryLogin(
            ['skautIS_Token' => '00000000-0000-4000-8000-000000000002', 'skautIS_IDRole' => '11', 'skautIS_IDUnit' => '1002', 'skautIS_DateLogout' => '31. 12. 2099 23:59:59'],
            static fn (): ?string => $skautis->getUser()->getLoginId(),
        );

        self::assertSame('00000000-0000-4000-8000-000000000002', $seen, 'the reads ran with the participant token');
        self::assertSame('00000000-0000-4000-8000-000000000001', $skautis->getUser()->getLoginId(), 'the leader login is back');
        self::assertSame(10, $this->gateway->getRoleId());

        $this->gateway->logout();
        $this->gateway->withTemporaryLogin(['skautIS_Token' => '00000000-0000-4000-8000-000000000002'], static fn (): null => null);
        self::assertNull($skautis->getUser()->getLoginId(), 'without a leader nothing stays behind');
    }

    public function testLoginRefreshWithoutLogoutDateEndsTheLogin(): void
    {
        // The fixture answers LoginUpdateRefresh for this token with an empty result, as skautIS may.
        $this->gateway->login(['skautIS_Token' => '00000000-0000-4000-8000-0000000000ff', 'skautIS_IDRole' => '10', 'skautIS_IDUnit' => '1003', 'skautIS_DateLogout' => '31. 12. 2099 23:59:59']);

        self::assertFalse($this->gateway->isLoggedIn(), 'an unconfirmed login is no login, not an error');
        $this->expectException(\App\Model\Skautis\SkautisException::class);
        $this->gateway->refreshLogin();
    }

    public function testLoginRolesAndUnits(): void
    {
        self::assertFalse($this->gateway->isLoggedIn());

        $this->gateway->login([
            'skautIS_Token' => '00000000-0000-4000-8000-000000000001',
            'skautIS_IDRole' => '10',
            'skautIS_IDUnit' => '1003',
            'skautIS_DateLogout' => '31. 12. 2099 23:59:59',
        ]);

        self::assertTrue($this->gateway->isLoggedIn());
        self::assertSame(10, $this->gateway->getRoleId());
        self::assertSame(1003, $this->gateway->getUnitId());

        $account = $this->gateway->getCurrentUser();
        self::assertSame(1, $account->id);
        self::assertSame(100, $account->personId);
        self::assertSame('Novák Jan (Honza)', $account->displayName);

        $roles = $this->gateway->getUserRoles($account->id);
        self::assertCount(3, $roles);
        self::assertSame('vedouciOddil', $roles[0]->key);
        self::assertSame('3. oddíl Vlčata', $roles[0]->unitName);

        self::assertSame(1002, $this->gateway->switchRole(11));
        self::assertSame(11, $this->gateway->getRoleId());
        self::assertSame(1002, $this->gateway->getUnitId());

        $unit = $this->gateway->getUnit(1002);
        self::assertNotNull($unit);
        self::assertSame('Středisko Sluneční hodiny', $unit->displayName);
        self::assertSame('stredisko', $unit->typeId);
        self::assertSame(1001, $unit->parentId);
        self::assertNull($this->gateway->getUnit(9999));

        $children = $this->gateway->getChildUnits(1002);
        self::assertSame([1003, 1004], array_map(static fn ($child) => $child->id, $children));
        self::assertSame([], $this->gateway->getChildUnits(1003));

        $this->gateway->logout();
        self::assertFalse($this->gateway->isLoggedIn());
    }
}
