<?php

declare(strict_types=1);

namespace Webwings\SkautisNette\Tests;

use PHPUnit\Framework\TestCase;
use stdClass;
use Webwings\SkautisNette\EventDispatcher;

final class EventDispatcherTest extends TestCase
{
    public function testEveryListenerReceivesTheEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $received = [];
        $dispatcher->addListener(static function (object $event) use (&$received): void {
            $received[] = 'a';
        });
        $dispatcher->addListener(static function (object $event) use (&$received): void {
            $received[] = 'b';
        });

        $event = new stdClass();

        self::assertSame($event, $dispatcher->dispatch($event));
        self::assertSame(['a', 'b'], $received);
    }
}
