<?php

declare(strict_types=1);

namespace App\Model\Event;

enum EventFieldType: string
{
    case Text = 'text';
    case Boolean = 'boolean';
    case Choice = 'choice';
    case MultiChoice = 'multichoice';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'text',
            self::Boolean => 'ano / ne',
            self::Choice => 'výběr jedné možnosti',
            self::MultiChoice => 'výběr více možností',
        };
    }

    public function hasOptions(): bool
    {
        return $this === self::Choice || $this === self::MultiChoice;
    }
}
