<?php

declare(strict_types=1);

namespace App\Model\Skautis;

use LogicException;
use Skaut\Skautis\Wsdl\AuthenticationException;
use Skaut\Skautis\Wsdl\PermissionException;
use Skaut\Skautis\Wsdl\WsdlException;

use function str_contains;
use function usleep;

/**
 * Retries transient transport failures from skautIS without hiding authentication, permission or domain errors.
 */
final class RetryingSkautisCall
{
    private const int MAX_ATTEMPTS = 3;
    private const int RETRY_DELAY_MICROSECONDS = 250_000;

    private const array RETRYABLE_MESSAGES = [
        'Could not connect to host',
        'php_network_getaddresses',
        'getaddrinfo',
        'Name or service not known',
        'Temporary failure in name resolution',
        'Connection timed out',
        'Failed to connect',
        'HTTP Error Fetching http headers',
        'SSL: Handshake timed out',
    ];

    /**
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    public function run(callable $call): mixed
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            try {
                return $call();
            } catch (AuthenticationException|PermissionException $exception) {
                throw $exception;
            } catch (WsdlException $exception) {
                if ($attempt === self::MAX_ATTEMPTS || ! $this->isRetryable($exception)) {
                    throw $exception;
                }

                usleep(self::RETRY_DELAY_MICROSECONDS * $attempt);
            }
        }

        throw new LogicException('Retry loop ended unexpectedly.');
    }

    private function isRetryable(WsdlException $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            foreach (self::RETRYABLE_MESSAGES as $message) {
                if (str_contains($current->getMessage(), $message)) {
                    return true;
                }
            }
        }

        return false;
    }
}
