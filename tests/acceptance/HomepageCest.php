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

    public function privacyPolicyIsPublicAndLinkedFromTheFooter(AcceptanceTester $I): void
    {
        $I->amOnPage('/');
        $I->click('[data-test="footer-privacy-link"]');
        $I->seeInCurrentUrl('/ochrana-osobnich-udaju');
        $I->see('Ochrana osobních údajů', 'h1');
        $I->see('gmail.send', '[data-test="privacy-page"]');
    }
}
