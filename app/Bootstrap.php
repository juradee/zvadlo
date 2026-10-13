<?php

declare(strict_types=1);

namespace App;

use App\Model\Monitoring\Sentry;
use Nette\Bootstrap\Configurator;
use Nette\Utils\FileSystem;

final class Bootstrap
{
    public static function boot(): Configurator
    {
        $projectDir = \dirname(__DIR__);

        Environment::load($projectDir);
        $environment = Environment::getConfiguration();
        $appEnv = $environment['appEnv'];

        $configurator = new Configurator();
        $configurator->setDebugMode($appEnv === 'dev' || $appEnv === 'ci');
        $configurator->enableTracy($projectDir.'/log');
        if ($environment['sentryDsn'] !== null) {
            Sentry::init($environment['sentryDsn'], $environment['releaseHash'], $appEnv);
        }
        // Tests run with skautIS fixtures in the same checkout; their own temp keeps fixture code lists out of the dev cache.
        $tempDir = $projectDir.($appEnv === 'ci' ? '/temp/ci' : '/temp');
        $configurator->setTempDirectory($tempDir);
        FileSystem::createDir($tempDir.'/sessions');
        $configurator->addStaticParameters([
            'envConfig' => $environment,
            'projectDir' => $projectDir,
        ]);
        $configurator->addConfig(__DIR__.'/config/config.neon');

        $environmentConfig = __DIR__.'/config/config.'.$appEnv.'.neon';
        if (is_file($environmentConfig)) {
            $configurator->addConfig($environmentConfig);
        }
        // Without it DBAL connects on boot to ask the server; the hosting CLI cannot always reach the database.
        if ($environment['database']['serverVersion'] !== null) {
            $configurator->addConfig(['dbal' => ['connections' => ['default' => ['serverVersion' => $environment['database']['serverVersion']]]]]);
        }

        return $configurator;
    }
}
