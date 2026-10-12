<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

/**
 * Public sign-up link, pairing with a member (Anna Malá, 101, 2015-03-12) and rejection by the leader.
 */
final class PublicRegistrationCest
{
    public function publicSignUpPairsMembersAndLeaderRejectsStrangers(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->amOnPage('/akce/nova');
        $I->fillField('name', 'Den otevřených dveří');
        $I->selectOption('skautisTypeId', '9) Ostatní');
        $I->selectOption('skautisScopeId', 'Oddílová');
        $I->fillDate('dateFrom', '2027-06-05');
        $I->fillDate('dateTo', '2027-06-05');
        $I->selectOption('publicRegistration', 'veřejný odkaz (kdokoli s odkazem)');
        $I->fillField('contactEmail', 'jan.novak@example.cz');
        $I->click('Založit akci');
        $I->waitForText('Akce byla založena', 10);
        $eventId = $I->grabFromCurrentUrl('~/akce/(\d+)~');
        $I->click('Otevřít přihlašování');
        $I->waitForText('Stav akce byl změněn', 10);
        $I->seeElement('[data-test="public-link-box"]');
        // Plain http has no navigator.clipboard; the button must still copy through the legacy command.
        $I->executeJS("Object.defineProperty(navigator, 'clipboard', {value: undefined, configurable: true});");
        $I->click('Kopírovat', '[data-test="public-link-box"]');
        $I->waitForText('Zkopírováno', 5, '[data-test="public-link-box"]');
        $publicLink = (string) $I->grabValueFrom('[data-test="public-link"]');

        $I->amOnUrl($publicLink);
        $I->see('Den otevřených dveří', '[data-test="event-name"]');
        $I->see('krok 1 ze 2');
        $I->fillField('firstName', 'anna');
        $I->fillField('lastName', 'mala');
        $I->fillDate('birthDate', '2015-03-12');
        $I->click('Pokračovat');
        $I->waitForElement('[data-test="member-recognised"]', 10);
        $I->dontSeeElement('input[name="street"]');
        $I->fillField('contactEmail', 'matka.mala@example.cz');
        $I->fillField('phone', '+420 777 000 201');
        $I->checkOption('agreement');
        $I->click('Odeslat přihlášku');
        $I->waitForElement('[data-test="public-done"]', 10);
        $I->see('Malá Anna', '[data-test="participant-name"]');
        $I->see('přihlášen', '[data-test="registration-state"]');

        // The next child of the family: surname and contact are remembered.
        $I->click('[data-test="register-another"]');
        $I->seeInField('lastName', 'mala');
        $I->fillField('firstName', 'Cizí');
        $I->fillField('lastName', 'Člověk');
        $I->fillDate('birthDate', '2010-01-01');
        $I->dontSeeElement('input[name="member"]');
        $I->click('Pokračovat');
        $I->waitForElement('[data-test="non-member-details"]', 10);
        $I->seeInField('contactEmail', 'matka.mala@example.cz');
        $I->seeInField('phone', '+420 777 000 201');
        $I->checkOption('muž');
        $I->fillField('street', 'Kdesi 1');
        $I->fillField('city', 'Brno');
        $I->fillField('postcode', '60200');
        $I->fillField('contactEmail', 'cizi@example.cz');
        $I->checkOption('agreement');
        $I->click('Odeslat přihlášku');
        $I->waitForElement('[data-test="public-done"]', 10);
        $I->see('Člověk Cizí', '[data-test="participant-name"]');

        $I->amOnPage('/akce/'.$eventId.'/prihlasky');
        $I->see('2', '[data-test="count-registered"]');
        $I->dontSeeElement('[data-test="pairing-hint"]');
        $I->see('veřejný odkaz', '[data-test="registration-list"]');
        $I->click('Malá Anna');
        $I->dontSeeElement('[data-test="pair-form"]');
        $I->see('osoba 101', '[data-test="registration-data"]');

        $I->amOnPage('/akce/'.$eventId.'/prihlasky');
        $I->click('Člověk Cizí');
        $I->seeElement('[data-test="pair-form"]');
        $I->fillField('reason', 'nepatří k nám');
        $I->executeJS('window.confirm = () => true;');
        $I->click('Zamítnout přihlášku');
        $I->waitForText('Přihláška byla zamítnuta', 10);
        $I->see('zamítnuto', '[data-test="registration-state"]');
        $I->see('nepatří k nám', '[data-test="registration-log"]');
        $I->dontSee('Kdesi 1');

        $I->amOnPage('/akce/'.$eventId.'/prihlasky');
        $I->see('1', '[data-test="count-registered"]');
        $I->dontSee('Člověk Cizí', '[data-test="registration-list"]');
        $I->amOnPage('/akce/'.$eventId.'/prihlasky?stav=rejected');
        $I->see('zamítnutá přihláška', '[data-test="registration-list"]');

        $I->amOnUrl($publicLink);
        $I->fillField('firstName', 'Cizí');
        $I->fillField('lastName', 'Člověk');
        $I->fillDate('birthDate', '2010-01-01');
        $I->click('Pokračovat');
        $I->waitForElement('[data-test="non-member-details"]', 10);
        $I->checkOption('muž');
        $I->fillField('street', 'Kdesi 1');
        $I->fillField('city', 'Brno');
        $I->fillField('postcode', '60200');
        $I->fillField('contactEmail', 'cizi@example.cz');
        $I->checkOption('agreement');
        $I->click('Odeslat přihlášku');
        $I->waitForText('Přihlášku pro tuto osobu už evidujeme', 10);
    }

    public function campIsCreatedFromSkautis(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->amOnPage('/akce');
        $I->click('[data-test="create-camp"]');
        $I->see('Nový tábor ze skautISu', 'h1');
        $I->see('Letní tábor Vlčat', '[data-test="camp-list"]');
        $I->dontSee('Tábor cizí jednotky', '[data-test="camp-list"]');
        $I->click('[data-test="camp-create"]');
        $I->seeElement('[data-test="camp-info"]');
        $I->seeElement('input[name="name"][disabled]');
        $I->fillField('contactEmail', 'jan.novak@example.cz');
        $I->click('Založit tábor');
        $I->waitForText('Tábor byl založen podle skautISu', 10);
        $I->see('Letní tábor Vlčat', '[data-test="event-name"]');
        $I->see('tábor', '[data-test="event-name"] ~ p .badge');
        $I->see('03.07.2027 – 17.07.2027');
        $I->see('Tábořiště U Tří dubů');

        $I->amOnPage('/akce/novy-tabor');
        $I->see('Už ve Zvadle', '[data-test="camp-list"]');
    }
}
