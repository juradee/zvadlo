<?php

declare(strict_types=1);

namespace App\Model\Registration;

enum LogActor: string
{
    case Participant = 'participant';
    case User = 'user';
    case System = 'system';
}
