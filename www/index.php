<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$projectDir = dirname(__DIR__);
App\Environment::load($projectDir);
$maintenance = App\Environment::getConfiguration()['maintenance'];

// Maintenance is decided before the container exists, so a broken deploy or migration still shows the page.
if ($maintenance['enabled'] && ! App\MaintenanceMode::isClientAllowed((string) ($_SERVER['REMOTE_ADDR'] ?? ''), $maintenance['allowedIps'])) {
    require __DIR__.'/maintenance.php';
    exit;
}

App\Bootstrap::boot()
    ->createContainer()
    ->getByType(Nette\Application\Application::class)
    ->run();
