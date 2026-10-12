<?php

declare(strict_types=1);

namespace App\Model\Registration;

use DomainException;

/**
 * A field that already holds answers cannot lose its options or be removed; hide it instead.
 */
final class FieldInUseException extends DomainException
{
}
