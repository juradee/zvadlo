<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

/**
 * Sending invitations from the leader's list, the e-mail editor and the unit settings page.
 */
final class MailingCest
{
    public function leaderSendsInvitationsAndSeesTheLog(AcceptanceTester $I): void
    {
        $I->clearMailpit();
        $I->loginAsFixtureUser();
        $I->amOnPage('/akce/nova');
        $I->fillField('name', 'Podzimní výprava');
        $I->selectOption('skautisTypeId', '2) Výprava');
        $I->selectOption('skautisScopeId', 'Oddílová');
        $I->fillDate('dateFrom', '2027-10-15');
        $I->fillDate('dateTo', '2027-10-17');
        $I->fillField('contactEmail', 'jan.novak@example.cz');
        $I->clickByJsAndWaitForPageText('[data-test="event-submit"]', 'Akce byla založena', 10);
        $eventId = $I->grabFromCurrentUrl('~/akce/(\d+)~');

        $I->click('[data-test="edit-emails"]');
        $I->see('E-maily akce', 'h1');
        $I->see('Zvadlo: Podzimní výprava', '[data-test="email-preview"]');
        $I->fillField('subject', 'Zvadlo na %akce%');
        $I->clickAndWaitForPageText('Uložit', 'Text e-mailu byl uložen', 10);
        $I->see('Zvadlo na Podzimní výprava', '[data-test="email-preview"]');

        $I->amOnPage('/akce/'.$eventId);
        $I->clickAndWaitForPageText('Otevřít přihlašování', 'Stav akce byl změněn', 10);
        $I->click('[data-test="invite-link"]');
        $I->checkOption('Malá Anna');
        $I->clickByJsAndWaitForPageText('[data-test="invite-submit"]', 'Přidáno 1 členů', 10);
        $I->see('pozvánka neodeslána', '[data-test="registration-list"]');

        $I->clickAndWaitForPageText('[data-test="send-invitations"]', 'Pozvánky se odesílají (1)', 10);
        $I->seeElement('[data-test="task-queued"]');
        $I->runQueuedTasks();
        $I->amOnPage('/akce/'.$eventId.'/prihlasky');
        $I->seeElement('[data-test="task-done"]');
        $I->dontSee('pozvánka neodeslána', '[data-test="registration-list"]');
        $I->seeEmailInMailpit('matka.mala@example.cz', 'Zvadlo na Podzimní výprava');
        $I->seeEmailInMailpit('otec.maly@example.cz', 'Zvadlo na Podzimní výprava');

        $I->click('Malá Anna', '[data-test="registration-list"]');
        $I->see('otec.maly@example.cz', '[data-test="mail-log"]');
        $I->see('odesláno', '[data-test="mail-log"]');

        $I->amOnPage('/akce/'.$eventId.'/prihlasky');
        $I->clickAndWaitForPageText('[data-test="send-reminder"]', 'Připomínka se odesílá (1)', 10);
        $I->runQueuedTasks();
        $I->seeEmailInMailpit('matka.mala@example.cz', 'Zvadlo na Podzimní výprava - připomínka');
        $I->amOnPage('/akce/'.$eventId.'/prihlasky');
        $I->seeElement('[data-test="send-reminder"][disabled]');
    }

    public function unitSettingsHoldDefaultsAndAccounts(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->click('[data-test="nav-unit"]');
        $I->see('Nastavení jednotky', 'h1');
        $I->seeElement('[data-test="google-not-configured"]');

        // Account ids are UUIDs with dashes, which a component name must not contain.
        $I->haveInDatabase('google_oauth', [
            'id' => '0f0f0f0f-1111-4222-8333-444444444444',
            'unit_id' => 1003,
            'email' => 'odebrat@example.cz',
            'refresh_token_encrypted' => 'x',
            'updated_at' => '2026-10-14 10:00:00',
        ]);
        $I->reloadPage();
        $I->see('odebrat@example.cz', '[data-test="mail-accounts"]');
        $I->clickAndAcceptPopupAndWaitForPageText('//li[contains(., "odebrat@example.cz")]//*[@data-test="remove-account"]', 'Účet byl odebrán', 10);
        $I->dontSee('odebrat@example.cz', '[data-test="mail-accounts"]');
        $I->fillField('agreementText', 'Souhlasím s účastí a pravidly oddílu.');
        $I->clickAndWaitForPageText('Uložit nastavení', 'Nastavení jednotky bylo uloženo', 10);
        $I->seeInField('agreementText', 'Souhlasím s účastí a pravidly oddílu.');

        $I->click('[data-test="template-rejected-toggle"]');
        $I->waitForElementVisible('[data-test="template-rejected-panel"].show input[name="rejected[subject]"]', 5);
        $I->fillField('rejected[subject]', 'Bohužel: %akce%');
        $I->clickAndWaitForPageText('Uložit výchozí texty', 'Výchozí texty e-mailů byly uloženy', 10);
        // Only the edited text is the unit's own; the others stayed the system default.
        $I->seeNumberOfElements('[data-test="template-form"] .badge', 1);
        $I->seeInField('rejected[subject]', 'Bohužel: %akce%');

        $I->click('[data-test="template-rejected-toggle"]');
        $I->waitForElementVisible('[data-test="template-rejected-panel"].show input[name="rejected[subject]"]', 5);
        $I->waitForElementVisible('[data-test="reset-template-rejected"]', 5);
        $I->clickAndAcceptPopupAndWaitForPageText('[data-test="reset-template-rejected"]', 'je zase výchozí', 10);
        $I->dontSee('vlastní text', '[data-test="template-form"]');
        $I->dontSeeInField('rejected[subject]', 'Bohužel: %akce%');
    }
}
