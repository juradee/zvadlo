<?php

declare(strict_types=1);

namespace App\Model\Registration;

use DomainException;

final class TooManyRegistrationsException extends DomainException
{
}
