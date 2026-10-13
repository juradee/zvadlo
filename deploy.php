<?php

declare(strict_types=1);

/**
 * Deployer 7 recipe: the CI job builds the release (composer --no-dev,
 * Vite assets), rsyncs it to the server, uploads the runtime files built from the GitHub environment
 * and switches the web root. Hosts are configured through DEPLOY_* variables of the environment.
 */

namespace Deployer;

use RuntimeException;
use Throwable;

require_once 'contrib/rsync.php';
require_once 'recipe/common.php';

set('application', 'zvadlo');
set('keep_releases', 3);
set('build_hash', static function (): string {
    $sha = substr(getenv('GITHUB_SHA') ?: '', 0, 8);

    return preg_replace('~[^A-Za-z0-9._-]~', '', date('Ymd-His').($sha === '' ? '' : '-'.$sha)) ?? date('Ymd-His');
});
set('release_name', static fn (): string => get('build_hash'));
set('shared_dirs', ['log', 'uploads', 'temp/sessions']);
set('shared_files', ['.env.local', 'app/config/google-credentials.json']);
set('writable_dirs', []);
set('web_root_symlink', static fn (): string => optionalEnv('DEPLOY_WEB_ROOT') ?? '{{deploy_path}}/www');

set('rsync_src', __DIR__.'/');
set('rsync_dest', '{{release_path}}');
set('rsync', static fn (): array => [
    'exclude' => ['.git', '.github', '.docs', '.agents', '.deployment', '/tests/', '/temp/', '/log/', '/uploads/', 'node_modules/', 'frontend/', 'docker/', '.env.local', '.env.*.local', 'app/config/google-credentials.json'],
    'exclude-file' => false,
    'include' => [],
    'include-file' => false,
    'filter' => [],
    'filter-file' => false,
    'filter-perdir' => false,
    'flags' => 'rz',
    'options' => ['delete'],
    'timeout' => 1800,
]);

function requiredEnv(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException(\sprintf('Missing required deploy variable "%s".', $name));
    }

    return $value;
}

function optionalEnv(string $name): ?string
{
    $value = getenv($name);

    return $value === false || $value === '' ? null : $value;
}

/**
 * @param array<string, string> $values
 */
function dotenv(array $values): string
{
    $lines = [];
    foreach ($values as $name => $value) {
        $lines[] = \sprintf('%s="%s"', $name, str_replace(['\\', "\n", "\r", '"'], ['\\\\', '\\n', '', '\\"'], $value));
    }

    return implode(\PHP_EOL, $lines).\PHP_EOL;
}

desc('Build .env.local and the Google credentials file from the environment of the deploy job');
task('zvadlo:build_runtime_files', static function (): void {
    $dir = __DIR__.'/.deployment';
    if (! is_dir($dir) && ! mkdir($dir, 0o777, true) && ! is_dir($dir)) {
        throw new RuntimeException('Unable to create '.$dir);
    }

    $appEnv = requiredEnv('APP_ENV');
    $baseUrl = rtrim(requiredEnv('APP_BASE_URL'), '/');
    $dbFixToken = requiredEnv('DB_FIX_TOKEN_SHA256');
    if (\strlen($dbFixToken) !== 64 || ! ctype_xdigit($dbFixToken)) {
        throw new RuntimeException('Deploy variable "DB_FIX_TOKEN_SHA256" must be a 64-character hexadecimal token.');
    }

    $values = [
        'APP_ENV' => $appEnv,
        'APP_BASE_URL' => $baseUrl,
        'APP_SECRET' => requiredEnv('APP_SECRET'),
        'APP_RELEASE_HASH' => get('build_hash'),
        'APPLICATION_ID' => requiredEnv('APPLICATION_ID'),
        'SKAUTIS_TEST_MODE' => optionalEnv('SKAUTIS_TEST_MODE') ?? ($appEnv === 'prod' ? 'false' : 'true'),
        'DB_HOST' => requiredEnv('DB_HOST'),
        'DB_NAME' => requiredEnv('DB_NAME'),
        'DB_USER' => requiredEnv('DB_USER'),
        'DB_PASSWORD' => requiredEnv('DB_PASSWORD'),
        'DB_FIX_TOKEN_SHA256' => $dbFixToken,
        'SEND_EMAIL' => optionalEnv('SEND_EMAIL') ?? 'true',
        'GOOGLE_REDIRECT_URI' => optionalEnv('GOOGLE_REDIRECT_URI') ?? $baseUrl.'/google/token',
        'TRACY_SHOW_BAR' => 'false',
    ];
    foreach (['SYSTEM_EMAIL', 'ERROR_EMAILS', 'TEST_BACKGROUND', 'ENVIRONMENT_LABEL', 'ENVIRONMENT_COLOR', 'SENTRY_DSN', 'GITHUB_ISSUES_TOKEN', 'HSKAUTING_WEBHOOK_URL', 'HSKAUTING_WEBHOOK_SECRET', 'ADMIN_USER_IDS', 'RETENTION_MONTHS_SYNCED', 'RETENTION_MONTHS_UNSYNCED', 'MAINTENANCE_MODE', 'MAINTENANCE_ALLOWED_IPS', 'MAINTENANCE_STARTED_AT', 'MAINTENANCE_ENDS_AT'] as $name) {
        $value = optionalEnv($name);
        if ($value !== null) {
            $values[$name] = $value;
        }
    }

    file_put_contents($dir.'/.env.local', dotenv($values));
    $maintenanceValues = $values;
    $maintenanceValues['MAINTENANCE_MODE'] = 'true';
    file_put_contents($dir.'/.env.maintenance.local', dotenv($maintenanceValues));
    file_put_contents($dir.'/google-credentials.json', optionalEnv('GOOGLE_CREDENTIALS') ?? '');
    writeln('<info>Runtime files prepared for '.$appEnv.'.</info>');
});

desc('Put the application into maintenance and upload its shared runtime files');
task('zvadlo:upload_maintenance_runtime_files', static function (): void {
    run('mkdir -p {{deploy_path}}/shared/app/config');
    upload(__DIR__.'/.deployment/.env.maintenance.local', '{{deploy_path}}/shared/.env.local');
    upload(__DIR__.'/.deployment/google-credentials.json', '{{deploy_path}}/shared/app/config/google-credentials.json');
});

desc('Run database migrations through the temporary HTTP endpoint');
task('zvadlo:db_fix', static function (): void {
    $url = rtrim(requiredEnv('APP_BASE_URL'), '/').'/db_fix.php?token='.rawurlencode(requiredEnv('DB_FIX_TOKEN_SHA256'));
    runLocally('curl --fail --silent --show-error --max-time 300 '.escapeshellarg($url));
});

desc('Restore the configured runtime settings after a successful migration');
task('zvadlo:restore_runtime_files', static function (): void {
    upload(__DIR__.'/.deployment/.env.local', '{{deploy_path}}/shared/.env.local');
});

desc('Remove the temporary HTTP migration endpoint');
task('zvadlo:disable_db_fix', static function (): void {
    run('rm -f {{release_path}}/www/db_fix.php');
});

desc('Compile the DI container so the first request is fast');
task('zvadlo:warmup', static function (): void {
    run('cd {{release_path}} && {{bin/php}} bin/console list --quiet');
});

desc('Verify release symlinks and point the web root of the hosting at the new release');
task('zvadlo:shared_symlinks', static function (): void {
    run('test -L {{release_path}}/log');
    run('test -L {{release_path}}/uploads');
    run('test -L {{release_path}}/temp/sessions');
    run('test "$(readlink -f {{release_path}}/log)" = "$(readlink -f {{deploy_path}}/shared/log)"');
    run('test "$(readlink -f {{release_path}}/uploads)" = "$(readlink -f {{deploy_path}}/shared/uploads)"');
    run('test "$(readlink -f {{release_path}}/temp/sessions)" = "$(readlink -f {{deploy_path}}/shared/temp/sessions)"');
    run('test -w {{deploy_path}}/shared/log');
    run('ln -sfn {{release_path}}/www {{web_root_symlink}}');
    run('test -L {{web_root_symlink}}');
    run('test "$(readlink -f {{web_root_symlink}})" = "$(readlink -f {{release_path}}/www)"');
});

desc('Verify that the public web root already serves the new release');
task('zvadlo:verify_web_root', static function (): void {
    $buildHash = get('build_hash');
    $checkFile = '.deploy-check-'.$buildHash.'.php';
    $expectedResponse = 'deploy-ok:'.$buildHash;
    $remoteCheckFile = '{{release_path}}/www/'.escapeshellarg($checkFile);
    $phpCheck = \sprintf(
        "<?php if (function_exists('opcache_reset')) { @opcache_reset(); } header('Content-Type: text/plain; charset=utf-8'); echo %s;\n",
        var_export($expectedResponse, true),
    );

    run(\sprintf('printf %s > %s', escapeshellarg($phpCheck), $remoteCheckFile));

    try {
        $url = rtrim(requiredEnv('APP_BASE_URL'), '/').'/'.$checkFile;
        $actualResponse = runLocally('curl --fail --silent --show-error --max-time 20 '.escapeshellarg($url));
        if ($actualResponse !== $expectedResponse) {
            throw new RuntimeException(\sprintf('Web root verification returned "%s", expected "%s".', $actualResponse, $expectedResponse));
        }
    } finally {
        run('rm -f '.$remoteCheckFile);
    }

    writeln('<info>Public web root serves release '.$buildHash.'.</info>');
});

desc('Ask /health which release answers');
task('zvadlo:verify', static function (): void {
    $url = rtrim(requiredEnv('APP_BASE_URL'), '/').'/health';
    $expected = get('build_hash');
    try {
        $body = runLocally('curl --fail --silent --show-error --max-time 20 '.escapeshellarg($url));
    } catch (Throwable $exception) {
        warning('Health check failed: '.$exception->getMessage());

        return;
    }
    if (! str_contains($body, '"release":"'.$expected.'"')) {
        warning(\sprintf('Health check answered %s, expected release %s (the web server may still serve the old release).', $body, $expected));

        return;
    }
    writeln('<info>Release '.$expected.' is live.</info>');
});

host(optionalEnv('DEPLOY_TARGET') ?? 'target')
    ->setHostname(requiredEnv('DEPLOY_HOST'))
    ->setPort((int) (optionalEnv('DEPLOY_PORT') ?? '22'))
    ->set('remote_user', requiredEnv('DEPLOY_USER'))
    ->set('deploy_path', requiredEnv('DEPLOY_PATH'))
    ->set('bin/php', optionalEnv('DEPLOY_PHP_BIN') ?? 'php')
    ->setSshArguments(['-o StrictHostKeyChecking=yes']);

task('deploy', [
    'zvadlo:build_runtime_files',
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'rsync',
    'zvadlo:upload_maintenance_runtime_files',
    'deploy:shared',
    'zvadlo:shared_symlinks',
    'zvadlo:verify_web_root',
    'zvadlo:db_fix',
    'zvadlo:restore_runtime_files',
    'zvadlo:warmup',
    'deploy:publish',
    'zvadlo:disable_db_fix',
    'zvadlo:verify',
]);

after('deploy:failed', 'deploy:unlock');
after('deploy:failed', 'zvadlo:disable_db_fix');
