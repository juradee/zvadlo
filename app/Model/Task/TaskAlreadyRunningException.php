<?php

declare(strict_types=1);

namespace App\Model\Task;

use DomainException;

final class TaskAlreadyRunningException extends DomainException
{
}
