<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

/**
 * The leader creates the event in skautIS from the sync page and sees the participant diff.
 */
final class SkautisSyncCest
{
    public function eventIsCreatedInSkautisFromTheSyncPage(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->amOnPage('/akce/nova');
        $I->fillField('name', 'Okresní kolo Svojsíkova závodu');
        $I->selectOption('skautisTypeId', '2) Výprava');
        $I->selectOption('skautisScopeId', 'Oddílová');
        $I->fillDate('dateFrom', '2027-05-15');
        $I->fillDate('dateTo', '2027-05-15');
        $I->fillField('contactEmail', 'jan.novak@example.cz');
        $I->clickByJsAndWaitForPageText('[data-test="event-submit"]', 'Akce byla založena', 10);
        $I->see('zatím nepropsaná', '[data-test="skautis-event"]');

        $I->click('[data-test="sync-link"]');
        $I->see('Propsání do skautISu', 'h1');
        $I->clickAndWaitForPageText('Založit akci ve skautISu', 'Akce byla založena ve skautISu (ID 5001)', 10);
        $I->seeElement('[data-test="skautis-event-link"]');
        $I->see('2', '[data-test="count-foreign"]');
        $I->see('0', '[data-test="count-missing"]');
    }
}
