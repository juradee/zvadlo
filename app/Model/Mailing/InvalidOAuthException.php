<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use RuntimeException;

/**
 * The Gmail account cannot be used any more; the leader has to remove and reconnect it.
 */
final class InvalidOAuthException extends RuntimeException
{
}
