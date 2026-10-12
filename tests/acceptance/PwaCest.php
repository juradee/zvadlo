<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

/**
 * The leaders' PWA: manifest and worker are served, the last shown list of registrations survives offline
 * as a saved copy and disappears at logout. Chrome treats the dev domain as secure (acceptance.suite.yml).
 */
final class PwaCest
{
    public function manifestAndServiceWorkerAreServed(AcceptanceTester $I): void
    {
        $fetch = static fn (string $path): string => 'const done = arguments[0]; fetch('.json_encode($path).").then(r => r.text().then(t => done(r.status + ' ' + r.headers.get('content-type') + ' ' + t)));";
        $I->amOnPage('/');

        $manifest = (string) $I->executeAsyncJS($fetch('/manifest.webmanifest'));
        $I->assertStringStartsWith('200 application/manifest+json', $manifest);
        $I->assertStringContainsString('"name":"Zvadlo"', $manifest);
        $I->assertStringContainsString('"display":"standalone"', $manifest);
        $I->assertStringContainsString('/pwa/icon-512-maskable.png', $manifest);

        $worker = (string) $I->executeAsyncJS($fetch('/sw.js'));
        $I->assertStringStartsWith('200 text/javascript', $worker);
        $I->assertStringContainsString('zvadlo-registrations-', $worker);
        $I->assertStringNotContainsString('__ZVADLO_RELEASE__', $worker, 'the release hash is filled in');

        $icon = (string) $I->executeAsyncJS("const done = arguments[0]; fetch('/pwa/icon-192.png').then(r => done(r.status + ' ' + r.headers.get('content-type')));");
        $I->assertSame('200 image/png', $icon);
    }

    public function registrationListIsSavedForOfflineAndForgottenAtLogout(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $I->amOnPage('/akce/nova');
        $I->fillField('name', 'Sraz bez signálu');
        $I->selectOption('skautisTypeId', '2) Výprava');
        $I->selectOption('skautisScopeId', 'Oddílová');
        $I->fillDate('dateFrom', '2027-06-05');
        $I->fillDate('dateTo', '2027-06-05');
        $I->fillField('contactEmail', 'jan.novak@example.cz');
        $I->clickByJs('[data-test="event-submit"]');
        $I->waitForText('Akce byla založena', 10);
        $eventId = $I->grabFromCurrentUrl('~/akce/(\d+)~');
        $listPath = '/akce/'.$eventId.'/prihlasky';

        $I->amOnPage($listPath);
        $ready = (string) $I->executeAsyncJS("const done = arguments[0]; navigator.serviceWorker.register('/sw.js').then(() => navigator.serviceWorker.ready).then(() => done('ready')).catch(e => done('error ' + e));");
        $I->assertSame('ready', $ready);

        // The first load ran before the worker controlled the page; this one is saved.
        $I->amOnPage($listPath);
        $I->seeElement('[data-test="state-counts"]');
        $I->dontSeeElement('[data-test="offline-banner"]');
        $saved = (string) $I->executeAsyncJS('const done = arguments[0]; const path = '.json_encode($listPath)."; (async () => { for (let i = 0; i < 50; i++) { for (const name of await caches.keys()) { if (!name.startsWith('zvadlo-registrations-')) continue; const keys = await (await caches.open(name)).keys(); if (keys.some(k => new URL(k.url).pathname === path)) return done('saved'); } await new Promise(r => setTimeout(r, 100)); } done('not saved'); })();");
        $I->assertSame('saved', $saved);

        $I->amOnPage('/offline');
        $I->see('Jste offline', 'h1');
        $I->waitForElement('[data-test="offline-cached-page"]', 10);
        $I->see('Sraz bez signálu', '[data-test="offline-cached-page"]');
        $I->see('uloženo', '[data-test="offline-cached-pages"]');
        $I->click('[data-test="offline-cached-page"]');
        $I->waitForElementVisible('[data-test="offline-banner"]', 10);
        $I->seeInCurrentUrl('offline=1');
        $I->see('uložená kopie', '[data-test="offline-banner"]');
        $I->seeElement('[data-test="state-counts"]');
        $I->see('Sraz bez signálu');

        $I->amOnPage('/sign/skautis-logout');
        $I->see('Přihlásit');
        $forgotten = (string) $I->executeAsyncJS("const done = arguments[0]; caches.keys().then(names => done(names.filter(n => n.startsWith('zvadlo-registrations-')).length === 0 ? 'forgotten' : 'kept: ' + names.join(',')));");
        $I->assertSame('forgotten', $forgotten);
        $I->amOnPage('/offline');
        $I->waitForElementVisible('[data-test="offline-empty"]', 10);
    }
}
