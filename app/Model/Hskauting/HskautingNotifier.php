<?php

declare(strict_types=1);

namespace App\Model\Hskauting;

use App\Model\Event\Event;
use App\Model\Event\EventRepository;
use App\Model\Registration\RegistrationRepository;
use App\Model\Registration\RegistrationState;
use DateTimeImmutable;
use GuzzleHttp\ClientInterface;
use Nette\Application\LinkGenerator;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Utils\Json;
use RuntimeException;
use Throwable;
use Tracy\ILogger;

/**
 * Push to h.skauting (spec 9.4): once a minute every event written to skautIS whose data changed since the
 * last push goes out in one signed POST with its link and counts by state, never personal data. h.skauting
 * keeps a copy and draws its badge from it, so its pages never call Zvadlo. A failed delivery leaves the
 * events unpushed and the next attempts back off up to an hour.
 */
final class HskautingNotifier
{
    private const int TIMEOUT_SECONDS = 10;
    private const int MAX_BACKOFF_MINUTES = 60;

    private readonly Cache $cache;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly EventRepository $events,
        private readonly RegistrationRepository $registrations,
        private readonly LinkGenerator $links,
        private readonly ILogger $logger,
        Storage $storage,
        private readonly ?string $webhookUrl,
        private readonly ?string $secret,
    ) {
        $this->cache = new Cache($storage, 'hskauting');
    }

    public function isEnabled(): bool
    {
        return $this->webhookUrl !== null && $this->webhookUrl !== '' && $this->secret !== null && $this->secret !== '';
    }

    public function runScheduled(): void
    {
        $result = $this->push(new DateTimeImmutable());
        if ($result->events > 0 || $result->error !== null) {
            $this->logger->log('h.skauting: '.$result->describe(), 'hskauting');
        }
    }

    /**
     * @param bool $everything resend every linked event regardless of changes and backoff (first deploy, recovery)
     */
    public function push(DateTimeImmutable $now, bool $everything = false): PushResult
    {
        if (! $this->isEnabled()) {
            return PushResult::disabled();
        }

        $backoff = $this->cache->load('backoff');
        $backoff = \is_array($backoff) ? $backoff : null;
        if (! $everything && $backoff !== null && (int) ($backoff['until'] ?? 0) > $now->getTimestamp()) {
            return PushResult::waiting((new DateTimeImmutable())->setTimestamp((int) $backoff['until']));
        }

        $events = $everything ? $this->events->findLinked() : $this->events->findLinkedChanged();
        if ($events === []) {
            return PushResult::nothing();
        }

        $body = Json::encode([
            'sentAt' => $now->format(\DATE_ATOM),
            'events' => array_map($this->describe(...), $events),
        ]);

        try {
            $response = $this->http->request('POST', (string) $this->webhookUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'zvadlo',
                    WebhookSignature::HEADER => WebhookSignature::sign((string) $this->secret, $now->getTimestamp(), $body),
                ],
                'body' => $body,
                'timeout' => self::TIMEOUT_SECONDS,
                'http_errors' => false,
            ]);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('h.skauting answered HTTP '.$status);
            }
        } catch (Throwable $exception) {
            $failures = ($backoff === null ? 0 : (int) ($backoff['failures'] ?? 0)) + 1;
            $minutes = min(2 ** min($failures, 6), self::MAX_BACKOFF_MINUTES);
            $until = $now->getTimestamp() + $minutes * 60;
            // The counter must outlive the wait, otherwise every failure would look like the first one.
            $this->cache->save('backoff', ['failures' => $failures, 'until' => $until], [Cache::Expire => '1 day']);

            return PushResult::failed(\count($events), $exception->getMessage(), (new DateTimeImmutable())->setTimestamp($until));
        }

        foreach ($events as $event) {
            $event->markPushedToHskauting($now);
        }
        $this->events->save();
        $this->cache->remove('backoff');

        return PushResult::sent(\count($events));
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Event $event): array
    {
        $counts = $this->registrations->countByState($event);

        return [
            'skautisEventId' => $event->getSkautisEventId(),
            'skautisCampId' => $event->getSkautisCampId(),
            'url' => $this->links->link('Events:Event:default', ['id' => $event->getId()]),
            'state' => $event->getState()->value,
            'registered' => $counts[RegistrationState::Registered->value],
            'waitlisted' => $counts[RegistrationState::Waitlisted->value],
            'declined' => $counts[RegistrationState::Declined->value],
            'pending' => $counts[RegistrationState::Invited->value] + $counts[RegistrationState::Seen->value],
        ];
    }
}
