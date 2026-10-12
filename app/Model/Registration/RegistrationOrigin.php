<?php

declare(strict_types=1);

namespace App\Model\Registration;

/**
 * How the registration came into being.
 */
enum RegistrationOrigin: string
{
    case Invited = 'invited';
    case Public = 'public';
    case Skautis = 'skautis';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'pozvánka',
            self::Public => 'veřejný odkaz',
            self::Skautis => 'přihlášení přes skautIS',
        };
    }
}
