<?php

declare(strict_types=1);

namespace App\Model\Monitoring;

use Sentry\State\Scope;
use Tracy\Debugger;

/**
 * Glue between the Sentry SDK and Tracy; active only when SENTRY_DSN is set.
 */
final class Sentry
{
    public static function init(string $dsn, string $release, string $environment): void
    {
        \Sentry\init([
            'dsn' => $dsn,
            'release' => $release,
            'environment' => $environment,
            'send_default_pii' => false,
            'attach_stacktrace' => true,
        ]);
        Debugger::setLogger(new SentryLogger(Debugger::getLogger()));
    }

    /**
     * Who was logged in when it happened; ids only, no personal data (a no-op without a DSN).
     */
    public static function setUser(int $userId, int $unitId, string $roleKey): void
    {
        \Sentry\configureScope(static function (Scope $scope) use ($userId, $unitId, $roleKey): void {
            $scope->setUser(['id' => (string) $userId]);
            $scope->setTag('unit', (string) $unitId);
            $scope->setTag('role', $roleKey);
        });
    }
}
