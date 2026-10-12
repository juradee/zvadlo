<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use RuntimeException;

/**
 * The edit code could not be delivered (Gmail or transport error); the message is for the participant.
 */
final class EditCodeNotSentException extends RuntimeException
{
}
