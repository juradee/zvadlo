<?php

declare(strict_types=1);

namespace App\Model\Event;

use DomainException;

final class InvalidStateTransitionException extends DomainException
{
    public static function between(EventState $from, EventState $to): self
    {
        return new self(\sprintf('Akci ve stavu „%s“ nelze převést do stavu „%s“.', $from->label(), $to->label()));
    }
}
