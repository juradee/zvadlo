<?php

declare(strict_types=1);

namespace App\Model\Hskauting;

/**
 * Signature of a webhook body: `t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<body>">` with the shared secret.
 * The receiver rejects an old timestamp, so a captured request cannot be replayed later.
 */
final class WebhookSignature
{
    public const string HEADER = 'X-Zvadlo-Signature';
    public const int TOLERANCE_SECONDS = 300;

    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return \sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', $timestamp.'.'.$body, $secret));
    }

    public static function verify(string $secret, string $header, string $body, int $now, int $tolerance = self::TOLERANCE_SECONDS): bool
    {
        if (preg_match('~^t=(\d{1,12}),v1=([0-9a-f]{64})$~', $header, $match) !== 1) {
            return false;
        }
        $timestamp = (int) $match[1];
        if (abs($now - $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$body, $secret), $match[2]);
    }
}
