<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Loads .env files and turns them into the configuration array the DI container gets as %envConfig%.
 *
 * Load order: .env, .env.local (skipped in ci), .env.{APP_ENV}, .env.{APP_ENV}.local.
 * Variables already present in the real environment are never overridden.
 */
final class Environment
{
    public const array ENVIRONMENTS = ['dev', 'ci', 'beta', 'test', 'prod'];

    /** @var array<string, mixed>|null */
    private static ?array $configuration = null;

    private static ?string $projectDir = null;

    /**
     * Variables set from the files by a previous load; they are not treated as protected real environment.
     *
     * @var array<string, bool>
     */
    private static array $ownVariables = [];

    public static function load(string $projectDir): void
    {
        if (self::$configuration !== null && self::$projectDir === $projectDir) {
            return;
        }

        $protected = array_diff_key(array_fill_keys(array_keys(getenv()), true), self::$ownVariables);

        self::loadFile($projectDir.'/.env', $protected);
        if (self::string('APP_ENV', 'dev') !== 'ci') {
            self::loadFile($projectDir.'/.env.local', $protected);
        }
        $appEnv = self::string('APP_ENV', 'dev');
        self::loadFile($projectDir.'/.env.'.$appEnv, $protected);
        self::loadFile($projectDir.'/.env.'.$appEnv.'.local', $protected);

        if (! \in_array($appEnv, self::ENVIRONMENTS, true)) {
            throw new RuntimeException("Unknown APP_ENV '$appEnv'.");
        }

        self::$projectDir = $projectDir;
        self::$configuration = self::build($projectDir, $appEnv);
    }

    /** @return array<string, mixed> */
    public static function getConfiguration(): array
    {
        if (self::$configuration === null) {
            throw new RuntimeException('Environment has not been loaded yet.');
        }

        return self::$configuration;
    }

    /** @return array<string, mixed> */
    private static function build(string $projectDir, string $appEnv): array
    {
        $baseUrl = rtrim(self::string('APP_BASE_URL', 'http://moje-zvadlo.cz'), '/');
        $host = parse_url($baseUrl, \PHP_URL_HOST);
        $fixtures = self::nullableString('SKAUTIS_FIXTURES');

        return [
            'appEnv' => $appEnv,
            'appBaseUrl' => $baseUrl,
            'appSecret' => self::string('APP_SECRET'),
            'releaseHash' => self::string('APP_RELEASE_HASH', 'dev'),
            'sendEmail' => self::bool('SEND_EMAIL', $appEnv !== 'dev' && $appEnv !== 'ci'),
            'systemEmail' => self::string('SYSTEM_EMAIL', 'noreply@'.(\is_string($host) ? $host : 'localhost')),
            'errorEmails' => self::list('ERROR_EMAILS'),
            'mailSmtp' => [
                'host' => self::string('MAIL_SMTP_HOST', 'mailpit'),
                'port' => self::int('MAIL_SMTP_PORT', 1025),
            ],
            'testBackground' => self::bool('TEST_BACKGROUND', $appEnv !== 'prod'),
            'environmentLabel' => self::string('ENVIRONMENT_LABEL', 'Testovací server'),
            'environmentColor' => \in_array(self::string('ENVIRONMENT_COLOR', 'test'), ['test', 'beta'], true)
                ? self::string('ENVIRONMENT_COLOR', 'test')
                : 'test',
            'tracyShowBar' => self::bool('TRACY_SHOW_BAR', $appEnv === 'dev'),
            'database' => [
                'host' => self::string('DB_HOST', 'mariadb'),
                'name' => self::string('DB_NAME', 'zvadlo'),
                'user' => self::string('DB_USER', 'zvadlo'),
                'password' => self::string('DB_PASSWORD', 'zvadlo'),
                'serverVersion' => self::nullableString('DB_SERVER_VERSION'),
            ],
            'skautis' => [
                'applicationId' => self::string('APPLICATION_ID'),
                'testMode' => self::bool('SKAUTIS_TEST_MODE', $appEnv !== 'prod'),
                'fixtures' => $fixtures === null ? null : self::path($projectDir, $fixtures),
            ],
            'google' => [
                'credentialsFile' => self::path($projectDir, self::string('GOOGLE_CREDENTIALS_FILE', 'app/config/google-credentials.json')),
                'redirectUri' => self::string('GOOGLE_REDIRECT_URI', $baseUrl.'/google/token'),
            ],
            'github' => [
                'token' => self::nullableString('GITHUB_ISSUES_TOKEN'),
                'owner' => self::nullableString('GITHUB_ISSUES_OWNER'),
                'repository' => self::nullableString('GITHUB_ISSUES_REPOSITORY'),
                'labels' => self::list('GITHUB_ISSUES_LABELS'),
            ],
            'hskauting' => [
                'webhookUrl' => self::nullableString('HSKAUTING_WEBHOOK_URL'),
                'webhookSecret' => self::nullableString('HSKAUTING_WEBHOOK_SECRET'),
            ],
            'retentionMonths' => [
                'synced' => self::int('RETENTION_MONTHS_SYNCED', 12),
                'unsynced' => self::int('RETENTION_MONTHS_UNSYNCED', 24),
            ],
            'sentryDsn' => self::nullableString('SENTRY_DSN'),
            'maintenance' => [
                'enabled' => self::bool('MAINTENANCE_MODE', false),
                'allowedIps' => self::list('MAINTENANCE_ALLOWED_IPS'),
                'startedAt' => self::nullableString('MAINTENANCE_STARTED_AT'),
                'endsAt' => self::nullableString('MAINTENANCE_ENDS_AT'),
            ],
            'adminUserIds' => array_map(intval(...), array_filter(self::list('ADMIN_USER_IDS'), is_numeric(...))),
        ];
    }

    /** @param array<string, bool> $protected */
    private static function loadFile(string $path, array $protected): void
    {
        if (! is_file($path)) {
            return;
        }

        foreach (explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($path))) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            if ($name === '' || isset($protected[$name])) {
                continue;
            }

            $value = self::unquote(trim($value));
            putenv($name.'='.$value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            self::$ownVariables[$name] = true;
        }
    }

    private static function unquote(string $value): string
    {
        if (\strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
            $inner = substr($value, 1, -1);

            return $value[0] === '"'
                ? str_replace(['\\"', '\\n', '\\\\'], ['"', "\n", '\\'], $inner)
                : str_replace("\\'", "'", $inner);
        }

        return trim((string) strtok($value, '#'));
    }

    private static function path(string $projectDir, string $path): string
    {
        return str_starts_with($path, '/') ? $path : $projectDir.'/'.$path;
    }

    private static function nullableString(string $name): ?string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? null : $value;
    }

    private static function string(string $name, string $default = ''): string
    {
        return self::nullableString($name) ?? $default;
    }

    private static function int(string $name, int $default): int
    {
        $value = self::nullableString($name);

        return $value !== null && is_numeric($value) ? (int) $value : $default;
    }

    private static function bool(string $name, bool $default): bool
    {
        $value = self::nullableString($name);
        if ($value === null) {
            return $default;
        }

        return match (strtolower($value)) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }

    /** @return list<string> */
    private static function list(string $name): array
    {
        $value = self::nullableString($name);

        return $value === null ? [] : array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn (string $item): bool => $item !== ''));
    }
}
