<?php

declare(strict_types=1);

namespace App;

/**
 * Who may still use the application while MAINTENANCE_MODE is on: addresses or CIDR ranges (IPv4 and IPv6).
 * Runs before the DI container exists, so it stays a plain static helper.
 */
final class MaintenanceMode
{
    /**
     * @param list<string> $allowedIps
     */
    public static function isClientAllowed(string $clientIp, array $allowedIps): bool
    {
        $client = @inet_pton(trim($clientIp));
        if ($client === false) {
            return false;
        }

        foreach ($allowedIps as $rule) {
            if (self::matches($client, trim($rule))) {
                return true;
            }
        }

        return false;
    }

    private static function matches(string $client, string $rule): bool
    {
        if ($rule === '') {
            return false;
        }
        $parts = explode('/', $rule, 2);
        $prefix = $parts[1] ?? null;
        $net = @inet_pton($parts[0]);
        if ($net === false || \strlen($net) !== \strlen($client)) {
            return false;
        }

        $totalBits = \strlen($net) * 8;
        if ($prefix === null) {
            return $client === $net;
        }
        if (! ctype_digit($prefix) || (int) $prefix > $totalBits) {
            return false;
        }

        $bits = (int) $prefix;
        $bytes = intdiv($bits, 8);
        if (substr($client, 0, $bytes) !== substr($net, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = 0xFF & (0xFF << (8 - $rest));

        return (\ord($client[$bytes]) & $mask) === (\ord($net[$bytes]) & $mask);
    }
}
