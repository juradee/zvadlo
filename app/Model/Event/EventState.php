<?php

declare(strict_types=1);

namespace App\Model\Event;

enum EventState: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'koncept',
            self::Open => 'otevřená',
            self::Closed => 'uzavřená',
            self::Archived => 'archivovaná',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::Open => 'text-bg-success',
            self::Closed => 'text-bg-warning',
            self::Archived => 'text-bg-dark',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => $next === self::Open,
            self::Open => $next === self::Closed,
            self::Closed => $next === self::Open || $next === self::Archived,
            self::Archived => false,
        };
    }
}
