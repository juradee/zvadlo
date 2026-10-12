<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Hskauting;

use App\Model\Hskauting\WebhookSignature;
use Codeception\Test\Unit;

final class WebhookSignatureTest extends Unit
{
    public function testRoundTripAndRejections(): void
    {
        $body = '{"sentAt":"2026-09-30T12:00:00+02:00","events":[]}';
        $header = WebhookSignature::sign('secret', 1_790_000_000, $body);

        self::assertSame(1, preg_match('~^t=1790000000,v1=[0-9a-f]{64}$~', $header));
        self::assertTrue(WebhookSignature::verify('secret', $header, $body, 1_790_000_100));
        self::assertFalse(WebhookSignature::verify('other', $header, $body, 1_790_000_100), 'wrong secret');
        self::assertFalse(WebhookSignature::verify('secret', $header, $body.' ', 1_790_000_100), 'changed body');
        self::assertFalse(WebhookSignature::verify('secret', $header, $body, 1_790_000_000 + 301), 'too old');
        self::assertFalse(WebhookSignature::verify('secret', $header, $body, 1_790_000_000 - 301), 'from the future');
        self::assertFalse(WebhookSignature::verify('secret', 'v1=abc', $body, 1_790_000_100), 'malformed');
        self::assertFalse(WebhookSignature::verify('secret', '', $body, 1_790_000_100));
    }
}
