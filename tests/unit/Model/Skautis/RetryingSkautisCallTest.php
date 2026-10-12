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

        try {
            (new RetryingSkautisCall())->run(static function () use (&$attempts): never {
                ++$attempts;

                throw new WsdlException('Connection timed out');
            });
            self::fail('The third transport error must be propagated.');
        } catch (WsdlException $exception) {
            self::assertSame('Connection timed out', $exception->getMessage());
        }

        self::assertSame(3, $attempts);
    }

    public function testDoesNotRetryAuthenticationOrBusinessErrors(): void
    {
        $authenticationAttempts = 0;
        try {
            (new RetryingSkautisCall())->run(static function () use (&$authenticationAttempts): never {
                ++$authenticationAttempts;

                throw new AuthenticationException('Přihlášení vypršelo.');
            });
            self::fail('Authentication errors must be propagated.');
        } catch (AuthenticationException) {
        }
        self::assertSame(1, $authenticationAttempts);

        $businessAttempts = 0;
        try {
            (new RetryingSkautisCall())->run(static function () use (&$businessAttempts): never {
                ++$businessAttempts;

                throw new WsdlException('Server was unable to process request.', 0, new RuntimeException('invalid input'));
            });
            self::fail('Business errors must be propagated.');
        } catch (WsdlException) {
        }
        self::assertSame(1, $businessAttempts);
    }
}
