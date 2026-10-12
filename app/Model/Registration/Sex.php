<?php

declare(strict_types=1);

namespace App\Model\Registration;

enum Sex: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'muž',
            self::Female => 'žena',
        };
    }
}
