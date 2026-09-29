<?php

declare(strict_types=1);

namespace App\Model\Skautis;

use RuntimeException;

/**
 * skautIS did not answer or answered with an error other than an expired login.
 */
final class SkautisException extends RuntimeException
{
}
