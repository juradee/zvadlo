<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use RuntimeException;

/**
 * Connecting a Gmail account was refused for a reason the leader can act on; the message is shown to them.
 */
final class GoogleConnectException extends RuntimeException
{
}
