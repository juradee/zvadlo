<?php

declare(strict_types=1);

namespace App\Tests\Support\Helper;

use App\Model\Task\TaskRunner;
use App\Model\User\UserPreference;
use Codeception\Module;
use Codeception\Module\WebDriver;
use Codeception\TestInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\Exception\WebDriverException;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use RuntimeException;

/**
 * Routes the browser to the test PHP container (nginx picks php-test when the SELENIUM cookie is present)
 * and logs in the way skautIS does: a POST with the token to the registered return URL.
 */
final class Acceptance extends Module
{
    public const string FIXTURE_TOKEN = '00000000-0000-4000-8000-000000000001';
    private const string MAILPIT = 'http://mailpit:8025';

    public function _before(TestInterface $test): void
    {
        $webDriver = $this->webDriver();
        $webDriver->amOnPage('/robots.txt');
        $webDriver->setCookie('SELENIUM', 'SELENIUM');

        // The public sign-up limit per IP is kept in the cache, which the database reset does not touch.
        $container = $this->getModule(NetteContainer::class);
        \assert($container instanceof NetteContainer);
        (new Cache($container->grabService(Storage::class), 'registration.public'))->clean([Cache::All => true]);
        (new Cache($container->grabService(Storage::class), 'registration.editCode'))->clean([Cache::All => true]);
        // A role remembered by one scenario must not decide the login of the next one.
        $container->grabService(EntityManagerInterface::class)->createQuery('DELETE FROM '.UserPreference::class)->execute();
    }

    /**
     * Logs in as the fixture user (Jan Novák) with the given user-role id from the fixture dataset.
     */
    public function loginAsFixtureUser(int $roleId = 10, int $unitId = 1003): void
    {
        $this->postSkautisLogin('/sign/skaut-i-s', $roleId, $unitId, '[data-test="current-role"]');
    }

    /**
     * Simulates the skautIS return of a participant from a public event page: the same POST with the
     * backlink of the event (p:{unitId}:{slug}:{webToken}) as ReturnUrl.
     */
    public function loginAsParticipant(string $backlink): void
    {
        $this->postSkautisLogin('/sign/skaut-i-s?ReturnUrl='.rawurlencode($backlink), 10, 1003, '[data-test="skautis-candidates"]');
    }

    private function postSkautisLogin(string $action, int $roleId, int $unitId, string $waitFor): void
    {
        $actionJson = json_encode($action, \JSON_THROW_ON_ERROR);
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
            form.action = {$actionJson};
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
        $webDriver->waitForElement($waitFor, 10);
    }

    /**
     * Types a date into a dd.mm.rrrr field; the test passes it as Y-m-d.
     */
    public function fillDate(string $name, string $isoDate): void
    {
        // Date fields are typed as dd.mm.rrrr, like a person would.
        $this->webDriver()->fillField('input[name="'.$name.'"]', (new DateTimeImmutable($isoDate))->format('d.m.Y'));
    }

    /**
     * Runs the queue worker the way the scheduler would, so a sent e-mail can be checked right away.
     */
    public function runQueuedTasks(): void
    {
        $container = $this->getModule(NetteContainer::class);
        \assert($container instanceof NetteContainer);
        $container->grabService(TaskRunner::class)->run(20);
    }

    /**
     * Clicks a control which replaces the document and waits until that control no longer belongs to it.
     */
    public function clickByJs(string $selector): void
    {
        $json = json_encode($selector, \JSON_THROW_ON_ERROR);
        $click = \sprintf('const target = document.querySelector(%s); if (target === null) return false; target.click(); return true;', $json);
        $exists = \sprintf('return document.querySelector(%s) !== null;', $json);
        $deadline = microtime(true) + 10;
        $clicked = false;
        while (microtime(true) < $deadline) {
            try {
                if (! $clicked) {
                    if ($this->webDriver()->executeJS($click) !== true) {
                        throw new RuntimeException('The click target '.$selector.' is not present.');
                    }
                    $clicked = true;
                }
                if ($this->webDriver()->executeJS($exists) !== true) {
                    return;
                }
            } catch (WebDriverException $exception) {
                if (! $this->isDocumentReplacementError($exception)) {
                    throw $exception;
                }
            }
            usleep(100000);
        }

        throw new RuntimeException('The click on '.$selector.' could not be confirmed within 10 seconds.');
    }

    /**
     * Waits for text without retaining a WebDriver element from the document being replaced.
     */
    public function waitForPageText(string $text, int $timeout = 10): void
    {
        $textJson = json_encode($text, \JSON_THROW_ON_ERROR);
        $containsText = \sprintf('return document.body !== null && document.body.innerText.includes(%s);', $textJson);
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            try {
                if ($this->webDriver()->executeJS($containsText) === true) {
                    return;
                }
            } catch (WebDriverException $exception) {
                if (! $this->isDocumentReplacementError($exception)) {
                    throw $exception;
                }
            }
            usleep(100000);
        }

        throw new RuntimeException('The text '.$text.' did not appear within '.$timeout.' seconds.');
    }

    /**
     * Clicks a control that redirects and waits for the expected text in the replacement document.
     *
     * @param mixed $locator Codeception link, CSS or XPath locator
     * @param mixed $context Codeception context locator
     */
    public function clickAndWaitForPageText(mixed $locator, string $text, int $timeout = 10, mixed $context = null): void
    {
        $marker = $this->pageMarker();
        $this->webDriver()->click($locator, $context);
        $this->waitForReplacementPageText($marker, $text, $timeout);
    }

    /**
     * Clicks a redirecting control protected by a browser confirmation dialog.
     *
     * @param mixed $locator Codeception link, CSS or XPath locator
     * @param mixed $context Codeception context locator
     */
    public function clickAndAcceptPopupAndWaitForPageText(mixed $locator, string $text, int $timeout = 10, mixed $context = null): void
    {
        $marker = $this->pageMarker();
        $webDriver = $this->webDriver();
        $webDriver->click($locator, $context);
        $webDriver->acceptPopup();
        $this->waitForReplacementPageText($marker, $text, $timeout);
    }

    /**
     * JavaScript variant of clickAndWaitForPageText() for controls WebDriver cannot interact with directly.
     */
    public function clickByJsAndWaitForPageText(string $selector, string $text, int $timeout = 10): void
    {
        $marker = $this->pageMarker();
        $selectorJson = json_encode($selector, \JSON_THROW_ON_ERROR);
        $clicked = $this->webDriver()->executeJS(\sprintf(
            'const target = document.querySelector(%s); if (target === null) return false; target.click(); return true;',
            $selectorJson,
        ));
        if ($clicked !== true) {
            throw new RuntimeException('The click target '.$selector.' is not present.');
        }

        $this->waitForReplacementPageText($marker, $text, $timeout);
    }

    private function pageMarker(): string
    {
        $marker = $this->webDriver()->executeJS(<<<'JS'
            if (!document.documentElement.dataset.acceptancePageMarker) {
                document.documentElement.dataset.acceptancePageMarker = Date.now().toString(36) + Math.random().toString(36).slice(2);
            }
            return document.documentElement.dataset.acceptancePageMarker;
            JS);

        return (string) $marker;
    }

    private function waitForReplacementPageText(string $previousMarker, string $text, int $timeout): void
    {
        $textJson = json_encode($text, \JSON_THROW_ON_ERROR);
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            try {
                $page = $this->webDriver()->executeJS(<<<JS
                    if (!document.documentElement.dataset.acceptancePageMarker) {
                        document.documentElement.dataset.acceptancePageMarker = Date.now().toString(36) + Math.random().toString(36).slice(2);
                    }
                    return [
                        document.documentElement.dataset.acceptancePageMarker,
                        document.body !== null && document.body.innerText.includes({$textJson}),
                    ];
                    JS);
                if (\is_array($page) && $page[0] !== $previousMarker && $page[1] === true) {
                    return;
                }
            } catch (WebDriverException $exception) {
                if (! $this->isDocumentReplacementError($exception)) {
                    throw $exception;
                }
            }
            usleep(100000);
        }

        throw new RuntimeException('The replacement page with text '.$text.' did not appear within '.$timeout.' seconds.');
    }

    private function isDocumentReplacementError(WebDriverException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'does not belong to the document')
            || str_contains($message, 'Node with given id does not belong to the document');
    }

    public function clearMailpit(): void
    {
        $context = stream_context_create(['http' => ['method' => 'DELETE', 'timeout' => 5]]);
        @file_get_contents(self::MAILPIT.'/api/v1/messages', false, $context);
    }

    /**
     * Asserts through the Mailpit REST API that a message with the subject reached the address.
     */
    public function seeEmailInMailpit(string $to, string $subjectContains): void
    {
        $json = @file_get_contents(self::MAILPIT.'/api/v1/search?query='.urlencode('to:'.$to.' subject:"'.$subjectContains.'"'));
        $data = \is_string($json) ? json_decode($json, true) : null;
        $count = \is_array($data) ? (int) ($data['messages_count'] ?? 0) : 0;
        $this->assertGreaterThan(0, $count, \sprintf('No e-mail to %s with subject containing "%s" in Mailpit.', $to, $subjectContains));
    }

    private function webDriver(): WebDriver
    {
        $webDriver = $this->getModule('WebDriver');
        \assert($webDriver instanceof WebDriver);

        return $webDriver;
    }
}
