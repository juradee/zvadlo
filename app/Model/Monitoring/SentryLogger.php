<?php

declare(strict_types=1);

namespace App\Model\Monitoring;

use Sentry\Severity;
use Throwable;
use Tracy\Dumper;
use Tracy\ILogger;

/**
 * Tracy keeps writing its log files; errors and exceptions additionally go to Sentry.
 */
final class SentryLogger implements ILogger
{
    private const array FORWARDED_LEVELS = [self::ERROR, self::EXCEPTION, self::CRITICAL];

    public function __construct(private readonly ILogger $inner)
    {
    }

    public function log(mixed $value, string $level = self::INFO): mixed
    {
        $result = $this->inner->log($value, $level);

        if (\in_array($level, self::FORWARDED_LEVELS, true)) {
            if ($value instanceof Throwable) {
                \Sentry\captureException($value);
            } else {
                \Sentry\captureMessage(\is_string($value) ? $value : Dumper::toText($value), Severity::error());
            }
        }

        return $result;
    }
}
