<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deployment;

use App\Deployment\EndpointToken;
use Codeception\Test\Unit;

final class EndpointTokenTest extends Unit
{
    public function testMatchesOnlyTheConfiguredHexToken(): void
    {
        $token = str_repeat('a', 64);

        self::assertTrue(EndpointToken::matches($token, strtoupper($token)));
        self::assertFalse(EndpointToken::matches($token, str_repeat('b', 64)));
        self::assertFalse(EndpointToken::matches($token, 'not-a-token'));
        self::assertFalse(EndpointToken::matches('not-a-token', $token));
        self::assertFalse(EndpointToken::matches($token, ['token']));
    }
}
