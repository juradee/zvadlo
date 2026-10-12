<?php

declare(strict_types=1);

namespace App\Model\Mailing;

enum EmailMessageState: string
{
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'odesláno',
            self::Failed => 'selhalo',
        };
    }
}
