<?php

declare(strict_types=1);

namespace App\Model\Registration;

use DomainException;

/**
 * The event already has a registration for this person (or this person was rejected).
 */
final class DuplicateRegistrationException extends DomainException
{
}
