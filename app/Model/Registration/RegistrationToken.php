<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Event\EventService;
use App\Model\Security\Crypto;

/**
 * A personal token as it is stored: a hash for lookup and the encrypted token for showing the link again.
 */
final readonly class RegistrationToken
{
    private function __construct(
        public string $hash,
        public string $encrypted,
    ) {
    }

    public static function generate(Crypto $crypto): self
    {
        $token = EventService::token();

        return new self(self::hash($token), $crypto->encrypt($token));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
