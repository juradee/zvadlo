<?php

declare(strict_types=1);

namespace App\Model\Registration;

use DomainException;

/**
 * The registration cannot be changed now: the event is not open, edits are locked or the link is blocked.
 */
final class RegistrationNotAllowedException extends DomainException
{
}
