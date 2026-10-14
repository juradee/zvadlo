<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

final class EventCest
{
    public function leaderCreatesEventWithFieldsAndOpensIt(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->amOnPage('/akce/nova');
        $I->see('Nová akce', 'h1');
        // The contact comes from the leader's main e-mail in skautIS; a troop cannot hold a patrol event.
        $I->seeInField('contactEmail', 'jan.novak@example.cz');
        $I->assertTrue((bool) $I->executeJS("return document.querySelector('select[name=skautisScopeId] option[value=\"1\"]').disabled;"));
        $I->assertFalse((bool) $I->executeJS("return document.querySelector('select[name=skautisScopeId] option[value=\"3\"]').disabled;"));
        // The scope starts on the unit's own level.
        $I->seeOptionIsSelected('skautisScopeId', 'Oddílová');

        $I->fillField('name', 'Jarní výprava');
        $I->selectOption('skautisTypeId', '2) Výprava');
        $I->selectOption('skautisScopeId', 'Oddílová');
        $I->seeElement('input[name="dateFrom"][placeholder="dd.mm.rrrr"]');
        // The calendar button opens the browser's picker; what it picks lands in the field as dd.mm.rrrr.
        $I->seeElement('[data-date-picker]:has(input[name="dateFrom"]) [data-date-picker-open]');
        $I->executeJS("const native = document.querySelector('[data-date-picker]:has(input[name=dateFrom]) [data-date-picker-native]'); native.value = '2027-04-09'; native.dispatchEvent(new Event('change', {bubbles: true}));");
        $I->seeInField('dateFrom', '09.04.2027');
        $I->fillField('dateFrom', '10.4.2027');
        $I->fillField('dateTo', '11.04.2027');
        $I->fillField('place', 'Chata Ostrá');
        $I->fillField('contactEmail', 'jan.novak@example.cz');
        $I->clickByJsAndWaitForPageText('[data-test="event-submit"]', 'Akce byla založena', 10);
        $I->seeInCurrentUrl('/akce/');
        $I->see('Jarní výprava', '[data-test="event-name"]');
        $I->see('koncept', '[data-test="event-state"]');
        $eventId = $I->grabFromCurrentUrl('~/akce/(\d+)~');

        $I->click('[data-test="edit-fields"]');
        $I->fillField('label', 'Alergie a léky');
        $I->selectOption('type', 'text');
        $I->dontSeeElement('textarea[name="options"]');
        $I->clickAndWaitForPageText('Přidat pole', 'Pole bylo přidáno', 10);

        $I->selectOption('type', 'výběr jedné možnosti');
        $I->waitForElementVisible('textarea[name="options"]', 5);
        $I->fillField('label', 'Doprava');
        $I->fillField('options', "autobus\nvlastní");
        $I->checkOption('required');
        $I->clickAndWaitForPageText('Přidat pole', 'Pole bylo přidáno', 10);
        $I->seeNumberOfElements('[data-test="field-row"]', 2);
        $I->see('autobus, vlastní', '[data-test="fields"]');

        $I->amOnPage('/akce/'.$eventId);
        $I->see('Doprava', '[data-test="field-list"]');
        $I->clickAndWaitForPageText('Otevřít přihlašování', 'Stav akce byl změněn', 10);
        $I->see('otevřená', '[data-test="event-state"]');

        // Without a year the overview shows every upcoming event, also those of the next year.
        $I->amOnPage('/akce');
        $I->seeOptionIsSelected('#filter-year', 'nadcházející');
        $I->see('Jarní výprava', '[data-test="event-list"]');
        $I->amOnPage('/akce?rok=2027');
        $I->see('Jarní výprava', '[data-test="event-list"]');
        $I->selectOption('#filter-year', 'nadcházející');
        $I->waitForElement('[data-test="event-list"]', 10);
        $I->dontSeeInCurrentUrl('rok=2027');
        $I->see('Jarní výprava', '[data-test="event-list"]');
        $I->amOnPage('/akce?rok=2027&stav=draft');
        $I->dontSee('Jarní výprava', '[data-test="event-list"]');
    }

    public function scopeFollowsTheChosenUnit(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser(11, 1002);
        $I->amOnPage('/akce/nova');
        $I->seeOptionIsSelected('skautisScopeId', 'Středisková');
        $I->selectOption('unitId', '3. oddíl Vlčata');
        $I->seeOptionIsSelected('skautisScopeId', 'Oddílová');
        $I->selectOption('unitId', 'Středisko Sluneční hodiny');
        $I->seeOptionIsSelected('skautisScopeId', 'Středisková');
    }

    public function officerOnlyReads(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->amOnPage('/akce/nova');
        $I->fillField('name', 'Střediskový sněm');
        $I->selectOption('skautisTypeId', '9) Ostatní');
        $I->selectOption('skautisScopeId', 'Oddílová');
        // No end date: a one-day event.
        $I->fillField('dateFrom', '01.05.2027');
        $I->see('Prázdné = jednodenní akce');
        // No deadline: sign-ups, changes and cancellations run until the end; the deadline notice has no use.
        $I->dontSeeElement('input[name="editLockDays"]');
        $I->dontSeeElement('input[name="lockNoticeDays"]');
        $I->dontSeeElement('input[name="waitlistEnabled"]');
        $I->fillField('capacity', '20');
        $I->waitForElementVisible('input[name="waitlistEnabled"]', 5);
        $I->seeCheckboxIsChecked('waitlistEnabled');
        $I->uncheckOption('waitlistEnabled');
        $I->fillField('contactEmail', 'jan.novak@example.cz');
        $I->clickByJsAndWaitForPageText('[data-test="event-submit"]', 'Akce byla založena', 10);
        $I->see('do konce akce (01.05.2027)', '[data-test="deadline"]');
        $I->see('20, bez čekací listiny', '[data-test="capacity"]');
        $eventId = $I->grabFromCurrentUrl('~/akce/(\d+)~');
        $I->amOnPage('/akce/'.$eventId.'/upravit');
        $I->seeInField('dateFrom', '01.05.2027');
        $I->seeInField('dateTo', '01.05.2027');
        $I->dontSeeCheckboxIsChecked('waitlistEnabled');
        $I->fillDate('registrationDeadline', '2027-04-28');
        $I->waitForElementVisible('input[name="lockNoticeDays"]', 5);

        $I->amOnPage('/prehled');
        $I->switchRoleInNavigation('Činovník: Středisko Sluneční hodiny');

        $I->amOnPage('/akce/'.$eventId);
        $I->see('Střediskový sněm', '[data-test="event-name"]');
        $I->dontSeeElement('[data-test="edit-event"]');

        $I->amOnPage('/akce/'.$eventId.'/upravit');
        $I->see('Přístup odepřen', 'h1');

        $I->amOnPage('/akce/nova');
        $I->see('Přístup odepřen', 'h1');
    }
}
