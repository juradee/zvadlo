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

        $I->switchRoleInNavigation('Vedoucí střediska: Středisko Sluneční hodiny');

        $I->see('Vedoucí střediska', '[data-test="current-role"]');
        $I->see('Středisko Sluneční hodiny', '[data-test="edit-units"]');
        $I->see('3. oddíl Vlčata', '[data-test="edit-units"]');
        $I->see('4. oddíl Skauti', '[data-test="edit-units"]');

        $I->switchRoleInNavigation('Činovník: Středisko Sluneční hodiny');

        $I->see('Vaše role neumožňuje spravovat akce', '[data-test="edit-units"]');
        $I->see('4. oddíl Skauti', '[data-test="read-units"]');
    }

    public function navigationRoleSwitchReloadsTheSamePage(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->amOnPage('/akce/nova');

        $I->click('[data-test="role-switch-open"]');
        $I->waitForElementVisible('[data-test="role-switch-modal"]', 5);
        $I->see('Neuložené změny na stránce se ztratí.', '[data-test="role-switch-modal"]');
        $I->selectOption('[data-test="role-switch-select"]', 'Činovník: Středisko Sluneční hodiny');
        $I->clickAndWaitForPageText('[data-test="role-switch-confirm"]', 'Role byla přepnuta.', 10);

        // The same page in the new role: an officer may not create events.
        $I->seeInCurrentUrl('/akce/nova');
        $I->see('Činovník', '[data-test="current-role"]');
        $I->see('Přístup odepřen', 'h1');

        // The refused page still offers the switch back.
        $I->switchRoleInNavigation('Vedoucí oddílu: 3. oddíl Vlčata');

        $I->seeInCurrentUrl('/akce/nova');
        $I->see('Vedoucí oddílu', '[data-test="current-role"]');
        $I->dontSee('Přístup odepřen', 'h1');
    }

    public function mediumScreensShortenTheRoleToTheUnitNumber(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->resizeWindow(1000, 900);

        $I->see('622.01.003', '[data-test="current-role-short"]');
        $I->dontSee('Vedoucí oddílu: 3. oddíl Vlčata', '[data-test="current-role"]');
        $I->dontSeeElement('[data-test="logout-timer"]');
        $I->moveMouseOver('[data-test="current-role-short"]');
        $I->waitForElementVisible('.tooltip', 5);
        $I->see('Vedoucí oddílu: 3. oddíl Vlčata', '.tooltip');

        $I->resizeWindow(1280, 900);
        $I->see('Vedoucí oddílu: 3. oddíl Vlčata', '[data-test="current-role"]');
        $I->dontSeeElement('[data-test="current-role-short"]');
        $I->seeElement('[data-test="logout-timer"]');
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
