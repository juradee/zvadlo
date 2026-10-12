<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

/**
 * Every page fits a phone (390 px wide, as in the PWA): nothing makes the page scroll sideways.
 * Wide content (tables, code) has to scroll inside its own box.
 */
final class ResponsiveCest
{
    // Lists elements sticking out of the viewport that are not inside a box scrolling on its own (clipped is wrong too).
    private const string OVERFLOW_JS = <<<'JS'
        const width = document.documentElement.clientWidth;
        if (document.documentElement.scrollWidth <= width) {
            return [];
        }
        const scrolls = (el) => {
            for (let p = el.parentElement; p; p = p.parentElement) {
                const o = getComputedStyle(p).overflowX;
                if (o === 'auto' || o === 'scroll') {
                    return true;
                }
            }
            return false;
        };
        const found = [];
        for (const el of document.body.querySelectorAll('*')) {
            const r = el.getBoundingClientRect();
            if (r.width > 0 && r.right > width + 1 && !scrolls(el)) {
                found.push(el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).join('.') : '') + ' right=' + Math.round(r.right));
            }
        }
        return found.slice(0, 8);
        JS;

    // The browser is shared by the whole suite: the next test expects the default desktop window again.
    public function _after(AcceptanceTester $I): void
    {
        $I->resizeWindow(1280, 900);
    }

    public function everyPageFitsAPhone(AcceptanceTester $I): void
    {
        $I->resizeWindow(390, 844);
        $I->loginAsFixtureUser();

        $I->amOnPage('/akce/nova');
        $I->fillField('name', 'Jarní výprava na hrad s dlouhým názvem, aby se ukázalo zalamování');
        $I->selectOption('skautisTypeId', '2) Výprava');
        $I->selectOption('skautisScopeId', 'Oddílová');
        $I->fillDate('dateFrom', '2027-04-10');
        $I->fillDate('dateTo', '2027-04-11');
        $I->selectOption('publicRegistration', 'open');
        $I->fillField('contactEmail', 'jan.novak@example.cz');
        $I->clickByJs('[data-test="event-submit"]');
        $I->waitForText('Akce byla založena', 10);
        $eventId = $I->grabFromCurrentUrl('~/akce/(\d+)~');
        $I->amOnPage('/akce/'.$eventId.'/pole');
        $I->selectOption('type', 'výběr jedné možnosti');
        $I->fillField('label', 'Doprava');
        $I->fillField('options', "autobus\nvlastní");
        $I->click('Přidat pole');
        $I->waitForText('Pole bylo přidáno', 10);
        $I->amOnPage('/akce/'.$eventId);
        $I->click('Otevřít přihlašování');
        $I->waitForText('Stav akce byl změněn', 10);

        $I->amOnPage('/akce/'.$eventId.'/pozvani');
        $I->waitForElementNotVisible('[data-test="address-loader"]', 15);
        $I->checkOption('Malá Anna');
        $I->clickByJs('[data-test="invite-submit"]');
        $I->waitForText('Přidáno 1 členů', 10);
        $I->click('Malá Anna', '[data-test="registration-list"]');
        $I->waitForElement('[data-test="personal-link"]', 10);
        $registrationPage = $I->grabFromCurrentUrl();
        $personalLink = (string) $I->grabValueFrom('[data-test="personal-link"]');
        $I->amOnPage('/akce/'.$eventId);
        $publicLink = (string) $I->grabValueFrom('[data-test="public-link"]');
        $I->amOnPage('/akce/'.$eventId.'/web');
        $webLink = (string) $I->grabValueFrom('[data-test="web-link"]');

        $leaderPages = [
            '/prehled', '/akce', '/akce/nova', '/akce/'.$eventId, '/akce/'.$eventId.'/upravit', '/akce/'.$eventId.'/pole',
            '/akce/'.$eventId.'/pozvani', '/akce/'.$eventId.'/emaily', '/akce/'.$eventId.'/web', '/akce/'.$eventId.'/skautis',
            '/akce/'.$eventId.'/prihlasky', $registrationPage, '/jednotka', '/napoveda', '/nahlasit-problem',
            '/admin', '/admin/uzivatele', '/admin/hlaseni-chyb', '/admin/statistiky', '/admin/jednotky', '/offline', '/neexistuje',
        ];
        $problems = [];
        foreach ($leaderPages as $page) {
            $I->amOnPage($page);
            $problems += $this->check($I, $page);
        }

        foreach ([$webLink, $publicLink, $personalLink] as $link) {
            $I->amOnUrl($link);
            $problems += $this->check($I, (string) parse_url($link, \PHP_URL_PATH));
        }
        $I->fillDate('birthDate', '2015-03-12');
        $I->click('Pokračovat');
        $I->waitForElement('input[name="attending"]', 10);
        $problems += $this->check($I, 'osobní přihláška – formulář');

        $I->assertSame([], $problems, 'pages scroll sideways on a phone');
    }

    /**
     * @return array<string, list<string>>
     */
    private function check(AcceptanceTester $I, string $page): array
    {
        /** @var list<string> $found */
        $found = $I->executeJS(self::OVERFLOW_JS);

        return $found === [] ? [] : [$page => $found];
    }
}
