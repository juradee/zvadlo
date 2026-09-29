<?php

declare(strict_types=1);

namespace Webwings\SkautisNette;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The one dispatcher the library accepts; fan-out to any number of listeners (Tracy panel, logging).
 */
final class EventDispatcher implements EventDispatcherInterface
{
    /** @var list<callable(object): void> */
    private array $listeners = [];

    /**
     * @param callable(object): void $listener
     */
    public function addListener(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function dispatch(object $event): object
    {
        foreach ($this->listeners as $listener) {
            $listener($event);
        }

        return $event;
    }
}
