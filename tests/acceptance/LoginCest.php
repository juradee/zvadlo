<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

final class LoginCest
{
    public function dashboardRequiresLogin(AcceptanceTester $I): void
    {
        $I->amOnPage('/prehled');
        $I->seeCurrentUrlMatches('~/Login/~');
    }

    public function loginShowsRoleAndUnits(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();

        $I->seeInCurrentUrl('/prehled');
        $I->see('Novák Jan (Honza)', '[data-test="account-name"]');
        $I->see('Vedoucí oddílu: 3. oddíl Vlčata', '[data-test="dashboard-role"]');
        $I->see('3. oddíl Vlčata', '[data-test="edit-units"]');
        $I->dontSee('4. oddíl Skauti', '[data-test="edit-units"]');
    }

    public function switchingRoleChangesUnits(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();

        $I->selectOption('[data-test="role-select"]', 'Vedoucí střediska: Středisko Sluneční hodiny');
        $I->click('[data-test="role-switch"]');
        $I->waitForPageText('Role byla přepnuta.', 10);

        $I->see('Vedoucí střediska', '[data-test="current-role"]');
        $I->see('Středisko Sluneční hodiny', '[data-test="edit-units"]');
        $I->see('3. oddíl Vlčata', '[data-test="edit-units"]');
        $I->see('4. oddíl Skauti', '[data-test="edit-units"]');

        $I->selectOption('[data-test="role-select"]', 'Činovník: Středisko Sluneční hodiny');
        $I->click('[data-test="role-switch"]');
        $I->waitForPageText('Role byla přepnuta.', 10);

        $I->see('Vaše role neumožňuje spravovat akce', '[data-test="edit-units"]');
        $I->see('4. oddíl Skauti', '[data-test="read-units"]');
    }

    public function logoutReturnFromSkautisEndsTheSession(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();

        $I->amOnPage('/sign/skautis-logout');
        $I->see('Byli jste odhlášeni.');
        $I->dontSeeElement('[data-test="current-role"]');

        $I->amOnPage('/prehled');
        $I->seeCurrentUrlMatches('~/Login/~');
    }
}
