<?php

declare(strict_types=1);

namespace App\Model\User;

use RuntimeException;

/**
 * skautIS did not confirm the login, or the user has no active role.
 */
final class LoginFailedException extends RuntimeException
{
}
