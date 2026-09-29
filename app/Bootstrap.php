<?php

declare(strict_types=1);

namespace App;

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
        $configurator->setTempDirectory($projectDir.'/temp');
        FileSystem::createDir($projectDir.'/temp/sessions');
        $configurator->addStaticParameters([
            'envConfig' => $environment,
            'projectDir' => $projectDir,
        ]);
        $configurator->addConfig(__DIR__.'/config/config.neon');

        $environmentConfig = __DIR__.'/config/config.'.$appEnv.'.neon';
        if (is_file($environmentConfig)) {
            $configurator->addConfig($environmentConfig);
        }

        return $configurator;
    }
}
