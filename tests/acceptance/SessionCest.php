<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

/**
 * The remembered role comes back at the next login and the keep-alive endpoint extends the session.
 */
final class SessionCest
{
    public function rememberedRoleSurvivesLogoutAndKeepAliveWorks(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->amOnPage('/prehled');
        $I->see('Vedoucí oddílu', '[data-test="current-role"]');
        $I->seeElement('[data-test="logout-timer"]');
        $I->seeElement('body[data-session-keep-alive-url]');

        $response = (string) $I->executeAsyncJS("const done = arguments[0]; fetch('/session/keep-alive', {method: 'POST', credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(r => r.text().then(t => done(r.status + ' ' + t)));");
        $I->assertSame('200 {"ok":true}', $response);

        $I->seeCheckboxIsChecked('[data-test="remember-role"]');
        $I->selectOption('[data-test="role-select"]', 'Činovník: Středisko Sluneční hodiny');
        $I->click('[data-test="role-switch"]');
        $I->waitForPageText('Role byla přepnuta.', 10);
        $I->see('Činovník', '[data-test="current-role"]');

        // skautIS hands over the troop leader role again; Zvadlo restores the remembered one.
        $I->amOnPage('/sign/skautis-logout');
        $I->loginAsFixtureUser(10, 1003);
        $I->amOnPage('/prehled');
        $I->see('Činovník', '[data-test="current-role"]');

        $I->uncheckOption('[data-test="remember-role"]');
        $I->uncheckOption('[data-test="extend-login"]');
        $I->selectOption('[data-test="role-select"]', 'Vedoucí oddílu: 3. oddíl Vlčata');
        $I->click('[data-test="role-switch"]');
        $I->waitForPageText('Role byla přepnuta.', 10);
        $I->dontSeeElement('body[data-session-keep-alive-url]');

        $I->amOnPage('/sign/skautis-logout');
        $I->loginAsFixtureUser(12, 1002);
        $I->amOnPage('/prehled');
        $I->see('Činovník', '[data-test="current-role"]', 'without remembering, the role skautIS sent is used');

        $denied = (string) $I->executeAsyncJS("const done = arguments[0]; fetch('/sign/skautis-logout').then(() => fetch('/session/keep-alive', {method: 'POST'})).then(r => done(String(r.status)));");
        $I->assertSame('401', $denied);

        // Polling and AJAX batches never keep the login alive; with the login gone they get 401, not the login page.
        $polled = (string) $I->executeAsyncJS("const done = arguments[0]; fetch('/prehled', {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(r => r.text().then(t => done(r.status + ' ' + t)));");
        $I->assertStringStartsWith('401 {"ok":false', $polled);
    }
}
