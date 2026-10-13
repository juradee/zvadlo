<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

/**
 * Sign-up through a skautIS login (spec 4.6): the fixture account Jan Novák (person 100) is the parent of
 * Tomáš Novák (106), who is not a member of the unit. The skautIS return is simulated by the same POST.
 */
final class SkautisParticipantCest
{
    public function parentSignsUpHisChildAndReopensTheRegistration(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->amOnPage('/akce/nova');
        $I->fillField('name', 'Okresní závod');
        $I->selectOption('skautisTypeId', '9) Ostatní');
        $I->selectOption('skautisScopeId', 'Oddílová');
        $I->fillDate('dateFrom', '2027-06-12');
        $I->fillDate('dateTo', '2027-06-12');
        $I->selectOption('publicRegistration', 'jen přes skautIS');
        $I->fillField('contactEmail', 'jan.novak@example.cz');
        $I->clickByJs('[data-test="event-submit"]');
        $I->waitForPageText('Akce byla založena', 10);
        $eventId = $I->grabFromCurrentUrl('~/akce/(\d+)~');
        $I->click('Otevřít přihlašování');
        $I->waitForPageText('Stav akce byl změněn', 10);
        $publicLink = (string) $I->grabValueFrom('[data-test="public-link"]');
        $I->assertSame(1, preg_match('~/(\d+)/([a-z0-9-]+)/prihlasit\?t=([A-Za-z0-9_-]+)~', $publicLink, $match), $publicLink);
        $backlink = 'p:'.($match[1] ?? '').':'.($match[2] ?? '').':'.($match[3] ?? '');

        $I->amOnPage('/sign/skautis-logout');
        $I->amOnUrl($publicLink);
        $I->see('Okresní závod', '[data-test="event-name"]');
        $I->seeElement('[data-test="skautis-only"]');
        $I->seeElement('[data-test="skautis-login"]');
        $I->dontSeeElement('input[name="firstName"]');

        $I->loginAsParticipant($backlink);
        $I->see('Koho přihlašujete?');
        $I->see('Novák Jan', '[data-test="candidate-100"]');
        $I->see('váš účet', '[data-test="candidate-100"]');
        $I->see('Novák Tomáš', '[data-test="candidate-106"]');
        $I->see('01.04.2012', '[data-test="candidate-106"]');
        $I->click('Přihlásit', '[data-test="candidate-106"]');
        $I->waitForElement('[data-test="member-recognised"]', 10);
        $I->see('Novák Tomáš', '[data-test="participant-name"]');
        $I->see('Osobu ověřil skautIS', '[data-test="member-recognised"]');
        $I->dontSeeElement('input[name="street"]');
        $I->seeInField('contactEmail', 'jan.novak@example.cz');
        $I->seeInField('phone', '+420 777 000 106');
        $I->checkOption('agreement');
        $I->click('Odeslat přihlášku');
        $I->waitForElement('[data-test="public-done"]', 10);
        $I->see('Novák Tomáš', '[data-test="participant-name"]');
        $I->see('přihlášen', '[data-test="registration-state"]');

        // Logged in again: the existing registration opens without any further verification.
        $I->loginAsParticipant($backlink);
        $I->see('už má přihlášku', '[data-test="candidate-106"]');
        $I->click('Otevřít přihlášku', '[data-test="candidate-106"]');
        $I->waitForElement('[data-test="participant-name"]', 10);
        $I->seeInCurrentUrl('/prihlaska/');
        $I->see('Novák Tomáš', '[data-test="participant-name"]');
        $I->seeElement('input[name="contactEmail"]');

        // The leader sees where the registration came from.
        $I->loginAsFixtureUser();
        $I->amOnPage('/akce/'.$eventId.'/prihlasky');
        $I->see('Novák Tomáš', '[data-test="registration-list"]');
        $I->see('přihlášení přes skautIS', '[data-test="registration-list"]');
    }
}
