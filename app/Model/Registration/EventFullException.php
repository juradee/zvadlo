<?php

declare(strict_types=1);

namespace App\Model\Registration;

use DomainException;

/**
 * No free place and no waiting list (or the leader did not confirm a promotion over capacity).
 */
final class EventFullException extends DomainException
{
}
