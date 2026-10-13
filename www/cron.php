<?php

declare(strict_types=1);

// The hosting cron calls this every minute over HTTP: its PHP CLI cannot reach the database.
// Does what `bin/console scheduler:run` does in docker.

use App\Bootstrap;
use App\Deployment\EndpointToken;
use App\Environment;
use Contributte\Scheduler\IScheduler;

require_once __DIR__.'/../vendor/autoload.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

Environment::load(dirname(__DIR__));

$providedToken = $_GET['token'] ?? $_POST['token'] ?? null;
if (! EndpointToken::matches(getenv('CRON_TOKEN'), $providedToken)) {
    http_response_code(403);
    echo "403 Forbidden\n";

    exit;
}

// A deploy runs in maintenance until the migrations are done; jobs must not see the old schema.
if (Environment::getConfiguration()['maintenance']['enabled']) {
    http_response_code(503);
    echo "Maintenance, skipped.\n";

    exit;
}

// The task runner works for up to TaskRunner::TIME_LIMIT_SECONDS; a client timeout must not cut a batch short.
ignore_user_abort(true);
set_time_limit(120);

Bootstrap::boot()->createContainer()->getByType(IScheduler::class)->run();

echo "OK\n";
