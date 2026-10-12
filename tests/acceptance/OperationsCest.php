<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

/**
 * The health endpoint answers without login and the help page is in the menu.
 */
final class OperationsCest
{
    public function healthAndHelp(AcceptanceTester $I): void
    {
        $health = (string) $I->executeAsyncJS("const done = arguments[0]; fetch('/health').then(r => r.text().then(t => done(r.status + ' ' + t)));");
        $I->assertStringStartsWith('200 {"status":"ok","release":"', $health);

        $I->loginAsFixtureUser();
        $I->amOnPage('/prehled');
        $I->click('[data-test="nav-help"]');
        $I->see('Nápověda pro vedoucí', 'h1');
        $I->seeElement('[data-test="help-page"]');
        $I->see('Propište účastníky do skautISu');
        $I->dontSeeElement('[data-test="maintenance-badge"]');
    }
}
