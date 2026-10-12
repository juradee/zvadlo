<?php

declare(strict_types=1);

namespace App\Model\Hskauting;

use DateTimeImmutable;

final readonly class PushResult
{
    private function __construct(
        public string $outcome,
        public int $events,
        public ?string $error = null,
        public ?DateTimeImmutable $retryAt = null,
    ) {
    }

    public static function disabled(): self
    {
        return new self('disabled', 0);
    }

    public static function waiting(DateTimeImmutable $retryAt): self
    {
        return new self('waiting', 0, null, $retryAt);
    }

    public static function nothing(): self
    {
        return new self('nothing', 0);
    }

    public static function sent(int $events): self
    {
        return new self('sent', $events);
    }

    public static function failed(int $events, string $error, DateTimeImmutable $retryAt): self
    {
        return new self('failed', $events, $error, $retryAt);
    }

    public function describe(): string
    {
        return match ($this->outcome) {
            'disabled' => 'push is disabled (HSKAUTING_WEBHOOK_URL or HSKAUTING_WEBHOOK_SECRET missing)',
            'waiting' => \sprintf('waiting after a failure until %s', $this->retryAt?->format('d.m.Y H:i:s')),
            'nothing' => 'nothing changed',
            'sent' => \sprintf('%d events sent', $this->events),
            default => \sprintf('%d events not sent: %s; next attempt %s', $this->events, $this->error, $this->retryAt?->format('d.m.Y H:i:s')),
        };
    }
}
