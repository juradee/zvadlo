<?php

declare(strict_types=1);

namespace App\Tests\Support\Helper;

use Codeception\Module;
use Codeception\Module\WebDriver;
use Codeception\TestInterface;

/**
 * Routes the browser to the test PHP container (nginx picks php-test when the SELENIUM cookie is present)
 * and logs in the way skautIS does: a POST with the token to the registered return URL.
 */
final class Acceptance extends Module
{
    public const string FIXTURE_TOKEN = '00000000-0000-4000-8000-000000000001';

    public function _before(TestInterface $test): void
    {
        $webDriver = $this->webDriver();
        $webDriver->amOnPage('/robots.txt');
        $webDriver->setCookie('SELENIUM', 'SELENIUM');
    }

    /**
     * Logs in as the fixture user (Jan Novák) with the given user-role id from the fixture dataset.
     */
    public function loginAsFixtureUser(int $roleId = 10, int $unitId = 1003): void
    {
        $fields = json_encode([
            'skautIS_Token' => self::FIXTURE_TOKEN,
            'skautIS_IDRole' => (string) $roleId,
            'skautIS_IDUnit' => (string) $unitId,
            'skautIS_DateLogout' => '31. 12. 2099 23:59:59',
        ], \JSON_THROW_ON_ERROR);

        $webDriver = $this->webDriver();
        $webDriver->amOnPage('/robots.txt');
        $webDriver->executeJS(<<<JS
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/sign/skaut-i-s';
            for (const [name, value] of Object.entries($fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }
            document.body.appendChild(form);
            form.submit();
            JS);
        $webDriver->waitForElement('[data-test="current-role"]', 10);
    }

    private function webDriver(): WebDriver
    {
        $webDriver = $this->getModule('WebDriver');
        \assert($webDriver instanceof WebDriver);

        return $webDriver;
    }
}
