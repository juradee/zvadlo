<?php

declare(strict_types=1);

namespace App\Tests\Unit\App;

use App\Environment;
use Codeception\Test\Unit;

final class EnvironmentTest extends Unit
{
    private string $dir;

    /** APP_ENV of the real environment wins over the files, so the test follows it. */
    private string $appEnv;

    /** APP_BASE_URL of the real environment wins over the files, so the test follows it. */
    private string $appBaseUrl;

    private string $systemEmail;

    protected function _before(): void
    {
        $env = getenv('APP_ENV');
        $this->appEnv = \is_string($env) && $env !== '' ? $env : 'test';
        $baseUrl = getenv('APP_BASE_URL');
        $this->appBaseUrl = rtrim(\is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : 'https://example.cz/', '/');
        $host = parse_url($this->appBaseUrl, \PHP_URL_HOST);
        $this->systemEmail = 'noreply@'.(\is_string($host) ? $host : 'localhost');
        $this->dir = sys_get_temp_dir().'/zvadlo-env-'.uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir.'/.env', implode("\n", [
            'APP_ENV='.$this->appEnv,
            'APP_BASE_URL=https://example.cz/',
            'ERROR_EMAILS=a@example.cz, b@example.cz',
            'SEND_EMAIL="true"',
            '# comment',
            'RETENTION_MONTHS_SYNCED=6',
            'ENVIRONMENT_LABEL=base label',
        ]));
        file_put_contents($this->dir.'/.env.'.$this->appEnv, "ENVIRONMENT_LABEL='Testovací server'\nTRACY_SHOW_BAR=off\n");
    }

    public function testFilesAreLayeredAndTyped(): void
    {
        Environment::load($this->dir);
        $config = Environment::getConfiguration();

        self::assertSame($this->appEnv, $config['appEnv']);
        self::assertSame($this->appBaseUrl, $config['appBaseUrl']);
        self::assertSame(['a@example.cz', 'b@example.cz'], $config['errorEmails']);
        self::assertTrue($config['sendEmail']);
        self::assertSame('Testovací server', $config['environmentLabel']);
        self::assertFalse($config['tracyShowBar']);
        self::assertSame(6, $config['retentionMonths']['synced']);
        self::assertSame($this->systemEmail, $config['systemEmail']);
    }

    public function testSecondLoadCanOverrideValuesSetByTheFirst(): void
    {
        Environment::load($this->dir);

        $other = $this->dir.'-other';
        mkdir($other);
        file_put_contents($other.'/.env', "APP_ENV={$this->appEnv}\nENVIRONMENT_LABEL=Other\n");

        Environment::load($other);

        self::assertSame('Other', Environment::getConfiguration()['environmentLabel']);
    }
}
