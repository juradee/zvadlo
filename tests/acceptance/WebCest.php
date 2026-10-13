<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

/**
 * The leader builds the mini-web; the public page needs the web token and serves the attachment.
 */
final class WebCest
{
    public function leaderBuildsTheMiniWebAndParentsOpenIt(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->amOnPage('/akce/nova');
        $I->fillField('name', 'Jarní výprava');
        $I->selectOption('skautisTypeId', '2) Výprava');
        $I->selectOption('skautisScopeId', 'Oddílová');
        $I->fillDate('dateFrom', '2027-04-10');
        $I->fillDate('dateTo', '2027-04-11');
        $I->fillField('place', 'Chata Ostrá');
        $I->fillField('description', "Sraz **v pátek** v 16:30.\n\nNávrat v neděli.");
        $I->fillField('contactEmail', 'jan.novak@example.cz');
        $I->clickByJsAndWaitForPageText('[data-test="event-submit"]', 'Akce byla založena', 10);
        $eventId = $I->grabFromCurrentUrl('~/akce/(\d+)~');
        $I->clickAndWaitForPageText('Otevřít přihlašování', 'Stav akce byl změněn', 10);

        $I->click('[data-test="edit-web"]');
        $I->see('Miniweb akce', 'h1');
        $webLink = (string) $I->grabValueFrom('[data-test="web-link"]');
        $I->seeElement('[data-test="no-blocks"]');

        $I->fillField('[data-test="text-form"] input[name="title"]', 'Co s sebou');
        $I->fillField('[data-test="text-form"] textarea[name="body"]', "- spacák\n- **baterka**");
        $I->clickAndWaitForPageText('Přidat text', 'Blok byl uložen', 10);

        $I->fillField('[data-test="link-form"] input[name="title"]', 'Fotky z loňska');
        $I->fillField('[data-test="link-form"] input[name="url"]', 'https://photos.example.cz/album');
        $I->clickAndWaitForPageText('Přidat odkaz', 'Blok byl uložen', 10);

        $I->attachFile('[data-test="file-form"] input[name="file"]', 'zvadlo.pdf');
        $I->clickAndWaitForPageText('Nahrát soubor', 'Soubor byl uložen', 10);
        $I->seeNumberOfElements('[data-test="block-row"]', 3);
        $I->see('zvadlo.pdf', '[data-test="blocks"]');

        $I->amOnUrl($webLink);
        $I->see('Jarní výprava', '[data-test="event-name"]');
        $I->see('10.04.2027 – 11.04.2027');
        $I->see('Chata Ostrá');
        $I->seeElement('[data-test="description"] strong');
        $I->see('Co s sebou', '[data-test="block-text"]');
        $I->see('baterka', '[data-test="block-text"] strong');
        $I->seeElement('[data-test="block-link"] a[href="https://photos.example.cz/album"]');
        $I->see('Otevřít PDF', '[data-test="file-link"]');
        $I->see('osobní odkaz na přihlášku e-mailem', '[data-test="registration-box"]');
        $fileUrl = (string) $I->grabAttributeFrom('[data-test="file-link"]', 'href');
        $type = $I->executeAsyncJS('const done = arguments[0]; fetch('.json_encode($fileUrl).").then(r => done(r.headers.get('content-type') + ' ' + r.status));");
        $I->assertStringContainsString('application/pdf', (string) $type);
        $I->assertStringContainsString('200', (string) $type);

        $I->amOnUrl(preg_replace('~t=.*$~', 't=spatny-token', $webLink) ?? '');
        $I->see('Stránka nenalezena', 'h1');
        $status = $I->executeAsyncJS('const done = arguments[0]; fetch('.json_encode(preg_replace('~\?t=.*$~', '', $fileUrl)).').then(r => done(r.status));');
        $I->assertSame('404', (string) $status, 'a file without the token is not served');

        $I->amOnPage('/akce/'.$eventId.'/web');
        $I->executeJS('window.confirm = () => true;');
        $I->clickAndWaitForPageText('Obnovit odkaz miniwebu', 'Odkaz miniwebu byl obnoven', 10);
        $I->amOnUrl($webLink);
        $I->see('Stránka nenalezena', 'h1');
    }
}
