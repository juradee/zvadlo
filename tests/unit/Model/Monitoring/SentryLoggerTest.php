<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Monitoring;

use App\Model\Monitoring\SentryLogger;
use Codeception\Test\Unit;
use RuntimeException;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Tracy\ILogger;

final class SentryLoggerTest extends Unit
{
    private FakeTransport $transport;

    private SpyLogger $inner;

    private SentryLogger $logger;

    protected function _before(): void
    {
        $this->transport = new FakeTransport();
        $client = ClientBuilder::create(['dsn' => 'https://public@sentry.example.invalid/1'])->setTransport($this->transport)->getClient();
        SentrySdk::setCurrentHub(new Hub($client));

        $this->inner = new SpyLogger();
        $this->logger = new SentryLogger($this->inner);
    }

    protected function _after(): void
    {
        SentrySdk::setCurrentHub(new Hub());
    }

    public function testExceptionsAndErrorsReachSentryEverythingReachesTracy(): void
    {
        $exception = new RuntimeException('boom');
        self::assertSame('exception--file.html', $this->logger->log($exception, ILogger::EXCEPTION));
        $this->logger->log('Queue stalled', ILogger::ERROR);
        $this->logger->log('just a note', ILogger::WARNING);
        $this->logger->log(['array' => 'value'], ILogger::CRITICAL);

        self::assertCount(4, $this->inner->logged, 'Tracy still logs everything');
        self::assertSame(ILogger::WARNING, $this->inner->logged[2][1]);
        self::assertCount(3, $this->transport->events);
        self::assertSame('boom', $this->transport->events[0]->getExceptions()[0]->getValue());
        self::assertSame('Queue stalled', $this->transport->events[1]->getMessage());
        self::assertStringContainsString('value', (string) $this->transport->events[2]->getMessage(), 'non-string values are dumped');
    }
}

final class FakeTransport implements TransportInterface
{
    /** @var list<Event> */
    public array $events = [];

    public function send(Event $event): Result
    {
        $this->events[] = $event;

        return new Result(ResultStatus::success(), $event);
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}

final class SpyLogger implements ILogger
{
    /** @var list<array{mixed, string}> */
    public array $logged = [];

    public function log(mixed $value, string $level = self::INFO): string
    {
        $this->logged[] = [$value, $level];

        return 'exception--file.html';
    }
}
