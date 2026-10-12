<?php

declare(strict_types=1);

namespace App\Model\Event;

/**
 * Who may register without a personal invitation.
 */
enum PublicRegistration: string
{
    case None = 'none';
    case Open = 'open';
    case Skautis = 'skautis';

    public function label(): string
    {
        return match ($this) {
            self::None => 'vypnuto (jen pozvaní)',
            self::Open => 'veřejný odkaz (kdokoli s odkazem)',
            self::Skautis => 'jen přes skautIS',
        };
    }

    public function isEnabled(): bool
    {
        return $this !== self::None;
    }
}
