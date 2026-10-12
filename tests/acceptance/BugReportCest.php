<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

/**
 * A user reports a problem from the footer; the administrator (ADMIN_USER_IDS in .env.ci) answers, resolves
 * it and manages system roles. All mail is checked in Mailpit.
 */
final class BugReportCest
{
    public function reportIsFiledAnsweredAndResolved(AcceptanceTester $I): void
    {
        $I->clearMailpit();
        $I->loginAsFixtureUser();
        $I->amOnPage('/prehled');
        $I->seeElement('[data-test="nav-admin"]');
        $I->click('[data-test="footer-bug-report-link"]');
        $I->see('Nahlásit problém', 'h1');
        $I->seeInField('url', 'http://moje-hospodareni.cz/prehled');
        $I->see('jan.novak@example.cz');
        $I->fillField('description', 'Po kliknutí na Uložit se stránka jen znovu načte a nic se neuloží.');
        $I->attachFile('screenshot', 'screenshot.png');
        $I->click('Odeslat hlášení');
        $I->waitForText('Hlášení technické chyby bylo uloženo a odesláno správcům.', 10);
        $I->seeEmailInMailpit('admin@example.cz', 'Hlášení technické chyby');

        $I->click('[data-test="nav-admin"]');
        $I->see('Administrace', 'h1');
        $I->click('[data-test="admin-link-bug-reports"]');
        $I->see('Po kliknutí na Uložit', '[data-test="bug-report-row"]');
        $I->click('Detail', '[data-test="bug-report-row"]');
        $I->see('Po kliknutí na Uložit se stránka jen znovu načte a nic se neuloží.', '[data-test="report-description"]');
        $I->see('Vedoucí oddílu', 'dl');
        $I->seeElement('[data-test="screenshot-link"]');
        $I->see('"userAgent"', '[data-test="report-diagnostics"]');
        $I->see('"activeRole"', '[data-test="report-diagnostics"]');
        $I->seeElement('[data-test="github-not-configured"]');

        $I->clearMailpit();
        $I->fillField('[data-test="reply-form"] textarea[name="message"]', 'Díky za hlášení, podíváme se na to.');
        $I->click('Odeslat odpověď');
        $I->waitForText('Odpověď autorovi hlášení byla odeslána.', 10);
        $I->see('Díky za hlášení, podíváme se na to.', '[data-test="report-replies"]');
        $I->seeEmailInMailpit('jan.novak@example.cz', 'Zvadlo');

        $I->clearMailpit();
        $I->click('Potvrdit opravu');
        $I->waitForText('Hlášení bylo označeno jako opravené a autor informován e-mailem.', 10);
        $I->see('opraveno', '[data-test="bug-reports-resolved"]');
        $I->seeEmailInMailpit('jan.novak@example.cz', 'Zvadlo');
    }

    public function systemRolesAreManagedInAdministration(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->amOnPage('/admin/uzivatele');
        $I->see('1', '[data-test="admin-users-config"]');
        $I->fillField('userId', '4242');
        $I->checkOption('input[name="roles[]"][value="support"]');
        $I->click('Přidat uživatele');
        $I->waitForText('Uživateli byly přiřazeny systémové role.', 10);
        $I->see('4242', '[data-test="admin-user-row"]');
        $I->see('support', '[data-test="admin-user-row"]');

        $I->click('Upravit', '[data-test="admin-user-row"]');
        $I->seeCheckboxIsChecked('input[name="roles[]"][value="support"]');
        $I->checkOption('input[name="roles[]"][value="admin"]');
        $I->click('Uložit role');
        $I->waitForText('Systémové role uživatele byly upraveny.', 10);
        $I->see('admin, support', '[data-test="admin-user-row"]');

        $I->click('Odebrat role', '[data-test="admin-user-row"]');
        $I->acceptPopup();
        $I->waitForText('Všechny systémové role uživatele byly odebrány.', 10);
        $I->dontSee('4242', '[data-test="admin-users-list"]');

        $I->amOnPage('/admin/statistiky');
        $I->see('Statistiky', 'h1');
        $I->seeElement('[data-test="statistics-totals"]');
        $I->seeElement('[data-test="statistics-months"]');
        $I->amOnPage('/admin/jednotky');
        $I->seeElement('[data-test="admin-units"]');
    }
}
