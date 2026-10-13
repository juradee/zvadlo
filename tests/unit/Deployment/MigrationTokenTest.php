<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deployment;

use App\Deployment\MigrationToken;
use Codeception\Test\Unit;

final class MigrationTokenTest extends Unit
{
    public function testMatchesOnlyTheConfiguredHexToken(): void
    {
        $token = str_repeat('a', 64);

        self::assertTrue(MigrationToken::matches($token, strtoupper($token)));
        self::assertFalse(MigrationToken::matches($token, str_repeat('b', 64)));
        self::assertFalse(MigrationToken::matches($token, 'not-a-token'));
        self::assertFalse(MigrationToken::matches('not-a-token', $token));
        self::assertFalse(MigrationToken::matches($token, ['token']));
    }
}
