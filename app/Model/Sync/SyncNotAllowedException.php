<?php

declare(strict_types=1);

namespace App\Model\Sync;

use DomainException;

final class SyncNotAllowedException extends DomainException
{
}
