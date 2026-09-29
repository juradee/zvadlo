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

        $I->fillField('name', 'Jarní výprava');
        $I->selectOption('skautisTypeId', 'Výprava');
        $I->selectOption('skautisScopeId', 'Oddílová');
        $I->executeJS("document.querySelector('[name=dateFrom]').value = '2027-04-10'; document.querySelector('[name=dateTo]').value = '2027-04-11';");
        $I->fillField('place', 'Chata Ostrá');
        $I->fillField('contactEmail', 'jan.novak@example.cz');
        $I->click('Založit akci');

        $I->waitForText('Akce byla založena', 10);
        $I->seeInCurrentUrl('/akce/');
        $I->see('Jarní výprava', '[data-test="event-name"]');
        $I->see('koncept', '[data-test="event-state"]');
        $eventId = $I->grabFromCurrentUrl('~/akce/(\d+)~');

        $I->click('[data-test="edit-fields"]');
        $I->fillField('label', 'Alergie a léky');
        $I->selectOption('type', 'text');
        $I->click('Přidat pole');
        $I->waitForText('Pole bylo přidáno', 10);

        $I->selectOption('type', 'výběr jedné možnosti');
        $I->fillField('label', 'Doprava');
        $I->fillField('options', "autobus\nvlastní");
        $I->checkOption('required');
        $I->click('Přidat pole');
        $I->waitForText('Pole bylo přidáno', 10);
        $I->seeNumberOfElements('[data-test="field-row"]', 2);
        $I->see('autobus, vlastní', '[data-test="fields"]');

        $I->amOnPage('/akce/'.$eventId);
        $I->see('Doprava', '[data-test="field-list"]');
        $I->click('Otevřít přihlašování');
        $I->waitForText('Stav akce byl změněn', 10);
        $I->see('otevřená', '[data-test="event-state"]');

        $I->amOnPage('/akce?rok=2027');
        $I->see('Jarní výprava', '[data-test="event-list"]');
        $I->amOnPage('/akce?rok=2027&stav=draft');
        $I->dontSee('Jarní výprava', '[data-test="event-list"]');
    }

    public function officerOnlyReads(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->amOnPage('/akce/nova');
        $I->fillField('name', 'Střediskový sněm');
        $I->selectOption('skautisTypeId', 'Jiná akce');
        $I->selectOption('skautisScopeId', 'Oddílová');
        $I->executeJS("document.querySelector('[name=dateFrom]').value = '2027-05-01'; document.querySelector('[name=dateTo]').value = '2027-05-01';");
        $I->fillField('contactEmail', 'jan.novak@example.cz');
        $I->click('Založit akci');
        $I->waitForText('Akce byla založena', 10);
        $eventId = $I->grabFromCurrentUrl('~/akce/(\d+)~');

        $I->amOnPage('/prehled');
        $I->selectOption('[data-test="role-select"]', 'Činovník: Středisko Sluneční hodiny');
        $I->click('[data-test="role-switch"]');
        $I->waitForText('Role byla přepnuta.', 10);

        $I->amOnPage('/akce/'.$eventId);
        $I->see('Střediskový sněm', '[data-test="event-name"]');
        $I->dontSeeElement('[data-test="edit-event"]');

        $I->amOnPage('/akce/'.$eventId.'/upravit');
        $I->see('Přístup odepřen', 'h1');

        $I->amOnPage('/akce/nova');
        $I->see('Přístup odepřen', 'h1');
    }
}
