<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Monitoring;

use App\Model\Monitoring\LogRotation;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Nette\Utils\FileSystem;
use Tracy\ILogger;

final class LogRotationTest extends Unit
{
    private string $dir;

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir().'/zvadlo-logs-'.uniqid();
        mkdir($this->dir);
    }

    protected function _after(): void
    {
        FileSystem::delete($this->dir);
    }

    public function testBigLogsRotateAndOldDumpsDisappear(): void
    {
        $now = new DateTimeImmutable('2026-10-01 03:30:00');
        $old = $now->modify('-40 days')->getTimestamp();
        $recent = $now->modify('-2 days')->getTimestamp();

        $this->file('exception.log', str_repeat('x', 200), $recent);
        $this->file('info.log', 'small', $recent);
        $this->file('exception--2026-08-01--10-00--abc.html', 'dump', $old);
        $this->file('exception--2026-09-29--10-00--def.html', 'dump', $recent);
        $this->file('error.log.20260801-030000', 'rotated', $old);
        $this->file('email-sent', '', $recent);
        $this->file('.gitkeep', '', $old);

        $logger = new class implements ILogger {
            public function log(mixed $value, string $level = self::INFO): void
            {
            }
        };
        $result = (new LogRotation($this->dir, $logger, keepDays: 30, maxBytes: 100))->run($now);

        self::assertSame(['rotated' => 1, 'deleted' => 3], $result);
        self::assertFileExists($this->dir.'/exception.log.20261001-033000');
        self::assertFileDoesNotExist($this->dir.'/exception.log');
        self::assertFileExists($this->dir.'/info.log');
        self::assertFileDoesNotExist($this->dir.'/exception--2026-08-01--10-00--abc.html');
        self::assertFileExists($this->dir.'/exception--2026-09-29--10-00--def.html');
        self::assertFileDoesNotExist($this->dir.'/error.log.20260801-030000');
        self::assertFileDoesNotExist($this->dir.'/email-sent', 'the Tracy e-mail throttle is released daily');
        self::assertFileExists($this->dir.'/.gitkeep');
    }

    private function file(string $name, string $content, int $mtime): void
    {
        file_put_contents($this->dir.'/'.$name, $content);
        touch($this->dir.'/'.$name, $mtime);
    }
}
