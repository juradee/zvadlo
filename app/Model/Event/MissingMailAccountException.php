<?php

declare(strict_types=1);

namespace App\Model\Event;

use DomainException;

/**
 * An event cannot be opened before the unit connects a Gmail account the invitations leave from.
 */
final class MissingMailAccountException extends DomainException
{
}
