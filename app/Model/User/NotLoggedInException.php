<?php

declare(strict_types=1);

namespace App\Model\User;

use RuntimeException;

final class NotLoggedInException extends RuntimeException
{
}
