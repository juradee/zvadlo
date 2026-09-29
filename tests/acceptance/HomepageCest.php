<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

final class HomepageCest
{
    public function homepageShowsTheEnvironmentBadge(AcceptanceTester $I): void
    {
        $I->amOnPage('/');
        $I->see('Zvadlo', 'h1');
        $I->seeElement('[data-test="environment-badge"]');
        $I->see('CI', '[data-test="environment-badge"]');
        $I->seeElement('[data-test="login-button"]');
    }
}
