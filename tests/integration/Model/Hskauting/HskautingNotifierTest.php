<?php

declare(strict_types=1);

namespace App\Tests\Integration\Model\Hskauting;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventRepository;
use App\Model\Event\EventService;
use App\Model\Event\EventState;
use App\Model\Hskauting\HskautingNotifier;
use App\Model\Hskauting\WebhookSignature;
use App\Model\Registration\RegistrationAnswer;
use App\Model\Registration\RegistrationRepository;
use App\Model\Registration\RegistrationService;
use App\Model\Task\Task;
use App\Tests\Support\IntegrationTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Nette\Application\LinkGenerator;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Tracy\ILogger;

/**
 * Push to h.skauting (spec 9.4): only linked events, only when something changed, one signed batch, backoff after a failure.
 */
final class HskautingNotifierTest extends Unit
{
    protected IntegrationTester $tester;

    private EntityManagerInterface $entityManager;

    private EventService $events;

    private RegistrationService $registrations;

    private MockHandler $http;

    protected function _before(): void
    {
        $this->entityManager = $this->tester->grabService(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM '.Task::class)->execute();
        $this->entityManager->createQuery('DELETE FROM '.Event::class)->execute();
        $this->events = $this->tester->grabService(EventService::class);
        $this->registrations = $this->tester->grabService(RegistrationService::class);
        $this->http = new MockHandler();
        (new Cache($this->tester->grabService(Storage::class), 'hskauting'))->clean([Cache::All => true]);
    }

    public function testChangedLinkedEventsGoOutInOneSignedBatch(): void
    {
        $notifier = $this->notifier('https://h.example.cz/api/zvadlo/events', 'shared-secret');

        $unlinked = $this->openEvent('Bez skautISu');
        $linked = $this->openEvent('Podzimní výprava');
        $linked->linkSkautisEvent(5001, new DateTimeImmutable('-1 hour'));
        $this->entityManager->flush();
        $anna = $this->registrations->inviteMembers($linked, [101 => null], 1)[0];

        // Changes carry the real time with second precision; the push is dated just after them.
        $now = new DateTimeImmutable('+1 second');
        $this->http->append(new Response(200));
        $result = $notifier->push($now);
        self::assertSame('sent', $result->outcome, $result->describe());
        self::assertSame(1, $result->events, 'the unlinked event is not h.skauting\'s business');

        $request = $this->http->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('https://h.example.cz/api/zvadlo/events', (string) $request->getUri());
        $body = (string) $request->getBody();
        self::assertTrue(WebhookSignature::verify('shared-secret', $request->getHeaderLine(WebhookSignature::HEADER), $body, $now->getTimestamp()));
        $payload = json_decode($body, true);
        self::assertIsArray($payload);
        self::assertSame($now->format(\DATE_ATOM), $payload['sentAt']);
        self::assertCount(1, $payload['events']);
        $sent = $payload['events'][0];
        self::assertSame(5001, $sent['skautisEventId']);
        self::assertNull($sent['skautisCampId']);
        self::assertStringEndsWith('/akce/'.$linked->getId(), $sent['url']);
        self::assertSame('open', $sent['state']);
        self::assertSame(['registered' => 0, 'waitlisted' => 0, 'declined' => 0, 'pending' => 1], array_intersect_key($sent, array_flip(['registered', 'waitlisted', 'declined', 'pending'])));
        self::assertStringNotContainsString('Malá', $body, 'never personal data');
        self::assertEquals($now, $linked->getHskautingPushedAt());
        self::assertNull($unlinked->getHskautingPushedAt());

        self::assertSame('nothing', $notifier->push($now->modify('+1 minute'))->outcome, 'no change, no request (the mock queue is empty)');

        sleep(1); // the change must land in a later second than the push above
        $this->registrations->respond($anna, new RegistrationAnswer(attending: true, contactEmail: 'matka.mala@example.cz'));
        $this->http->append(new Response(204));
        self::assertSame(1, $notifier->push(new DateTimeImmutable('+1 second'))->events);
        $payload = json_decode((string) $this->http->getLastRequest()?->getBody(), true);
        self::assertIsArray($payload);
        self::assertSame(1, $payload['events'][0]['registered']);
        self::assertSame(0, $payload['events'][0]['pending']);
    }

    public function testFailureLeavesEventsUnpushedAndBacksOff(): void
    {
        $notifier = $this->notifier('https://h.example.cz/api/zvadlo/events', 'shared-secret');
        $now = new DateTimeImmutable();
        $event = $this->openEvent('Tábor');
        $event->linkSkautisEvent(5002, $now);
        $this->entityManager->flush();

        $this->http->append(new Response(500));
        $failed = $notifier->push($now);
        self::assertSame('failed', $failed->outcome);
        self::assertStringContainsString('HTTP 500', (string) $failed->error);
        self::assertSame($now->getTimestamp() + 120, $failed->retryAt?->getTimestamp(), 'first failure: two minutes');
        self::assertNull($event->getHskautingPushedAt());

        self::assertSame('waiting', $notifier->push($now->modify('+1 minute'))->outcome, 'inside the backoff nothing is sent');

        $this->http->append(new Response(503));
        $second = $notifier->push($now->modify('+3 minutes'));
        self::assertSame('failed', $second->outcome);
        self::assertSame($now->getTimestamp() + 180 + 240, $second->retryAt?->getTimestamp(), 'second failure in a row: four minutes, the counter survived the wait');

        $this->http->append(new Response(200));
        $all = $notifier->push($now, everything: true);
        self::assertSame('sent', $all->outcome, '--all ignores the backoff');
        self::assertNotNull($event->getHskautingPushedAt());

        $this->registrations->inviteMembers($event, [102 => null], 1);
        $this->http->append(new Response(200));
        self::assertSame('sent', $notifier->push(new DateTimeImmutable('+1 second'))->outcome, 'a success cleared the backoff and the new registration goes out');
    }

    public function testDisabledWithoutConfiguration(): void
    {
        self::assertSame('disabled', $this->notifier(null, null)->push(new DateTimeImmutable())->outcome);
        self::assertSame('disabled', $this->notifier('https://h.example.cz/x', '')->push(new DateTimeImmutable())->outcome);
    }

    private function notifier(?string $url, ?string $secret): HskautingNotifier
    {
        $logger = new class implements ILogger {
            public function log(mixed $value, string $level = self::INFO): void
            {
            }
        };

        return new HskautingNotifier(
            new Client(['handler' => HandlerStack::create($this->http)]),
            $this->tester->grabService(EventRepository::class),
            $this->tester->grabService(RegistrationRepository::class),
            $this->tester->grabService(LinkGenerator::class),
            $logger,
            $this->tester->grabService(Storage::class),
            $url,
            $secret,
        );
    }

    private function openEvent(string $name): Event
    {
        $event = $this->events->create(1003, new EventInput($name, '2', '2', new DateTimeImmutable('+30 days'), new DateTimeImmutable('+30 days'), 'Louka', null, null, null, null, 'Jan Novák', 'jan@example.cz', null), 1);
        $this->events->changeState($event, EventState::Open);

        return $event;
    }
}
