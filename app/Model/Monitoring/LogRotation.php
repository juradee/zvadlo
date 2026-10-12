<?php

declare(strict_types=1);

namespace App\Model\Monitoring;

use DateTimeImmutable;
use Nette\Utils\Finder;
use Tracy\ILogger;

/**
 * Keeps the log directory bounded on shared hosting without logrotate: big Tracy logs are rotated,
 * old exception dumps and rotated files are deleted and the "email-sent" throttle of Tracy is
 * released once a day so administrators keep getting error mails.
 */
final class LogRotation
{
    public function __construct(
        private readonly string $logDir,
        private readonly ILogger $logger,
        private readonly int $keepDays = 30,
        private readonly int $maxBytes = 10_000_000,
    ) {
    }

    public function runScheduled(): void
    {
        $result = $this->run(new DateTimeImmutable());
        $this->logger->log(\sprintf('Log rotation: %d files rotated, %d deleted.', $result['rotated'], $result['deleted']), 'retention');
    }

    /**
     * @return array{rotated: int, deleted: int}
     */
    public function run(DateTimeImmutable $now): array
    {
        $rotated = 0;
        $deleted = 0;
        if (! is_dir($this->logDir)) {
            return ['rotated' => 0, 'deleted' => 0];
        }

        $expired = $now->modify(\sprintf('-%d days', $this->keepDays))->getTimestamp();
        foreach (Finder::findFiles('*')->in($this->logDir) as $file) {
            $name = $file->getFilename();
            $modified = $file->getMTime();

            if (($name === 'email-sent' && $modified < $now->modify('-1 day')->getTimestamp())
                || ((preg_match('~^exception--.*\.(html|md)$~', $name) === 1 || preg_match('~\.log\.\d{8}-\d{6}$~', $name) === 1) && $modified < $expired)) {
                unlink($file->getPathname());
                ++$deleted;
                continue;
            }

            if (str_ends_with($name, '.log') && $file->getSize() > $this->maxBytes) {
                rename($file->getPathname(), $file->getPathname().'.'.$now->format('Ymd-His'));
                ++$rotated;
            }
        }

        return ['rotated' => $rotated, 'deleted' => $deleted];
    }
}
