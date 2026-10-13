<?php

declare(strict_types=1);

namespace App\Deployment;

final class EndpointToken
{
    public static function matches(mixed $configuredToken, mixed $providedToken): bool
    {
        if (! \is_string($configuredToken) || ! \is_string($providedToken)) {
            return false;
        }

        $configuredToken = trim($configuredToken);
        $providedToken = trim($providedToken);

        return \strlen($configuredToken) === 64
            && ctype_xdigit($configuredToken)
            && \strlen($providedToken) === 64
            && ctype_xdigit($providedToken)
            && hash_equals(strtolower($configuredToken), strtolower($providedToken));
    }
}
