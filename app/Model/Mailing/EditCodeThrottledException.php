<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use RuntimeException;

/**
 * A new edit code was asked for too soon or too often; the message is for the participant.
 */
final class EditCodeThrottledException extends RuntimeException
{
}
