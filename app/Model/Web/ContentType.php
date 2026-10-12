<?php

declare(strict_types=1);

namespace App\Model\Web;

enum ContentType: string
{
    case Text = 'text';
    case File = 'file';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'text',
            self::File => 'soubor',
            self::Link => 'odkaz',
        };
    }
}
