<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Skautis;

use App\Model\Skautis\RetryingSkautisCall;
use Codeception\Test\Unit;
use RuntimeException;
use Skaut\Skautis\Wsdl\AuthenticationException;
use Skaut\Skautis\Wsdl\WsdlException;

final class RetryingSkautisCallTest extends Unit
{
    public function testRetriesATransientConnectionFailure(): void
    {
        $attempts = 0;

        $result = (new RetryingSkautisCall())->run(static function () use (&$attempts): string {
            ++$attempts;
            if ($attempts === 1) {
                throw new WsdlException('Could not connect to host');
            }

            return 'answer';
        });

        self::assertSame('answer', $result);
        self::assertSame(2, $attempts);
    }

    public function testStopsAfterThreeTransientFailures(): void
    {
        $attempts = 0;

        $this->expectException(WsdlException::class);
        $this->expectExceptionMessage('Connection timed out');
        try {
            (new RetryingSkautisCall())->run(static function () use (&$attempts): never {
                ++$attempts;

                throw new WsdlException('Connection timed out');
            });
        } finally {
            self::assertSame(3, $attempts);
        }
    }

    public function testDoesNotRetryAuthenticationErrors(): void
    {
        $attempts = 0;

        $this->expectException(AuthenticationException::class);
        try {
            (new RetryingSkautisCall())->run(static function () use (&$attempts): never {
                ++$attempts;

                throw new AuthenticationException('Přihlášení vypršelo.');
            });
        } finally {
            self::assertSame(1, $attempts);
        }
    }

    public function testDoesNotRetryBusinessErrors(): void
    {
        $attempts = 0;

        $this->expectException(WsdlException::class);
        try {
            (new RetryingSkautisCall())->run(static function () use (&$attempts): never {
                ++$attempts;

                throw new WsdlException('Server was unable to process request.', 0, new RuntimeException('invalid input'));
            });
        } finally {
            self::assertSame(1, $attempts);
        }
    }
}
