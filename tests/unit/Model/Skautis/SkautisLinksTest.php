<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Skautis;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Skautis\SkautisLinks;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Skaut\Skautis\Config;

final class SkautisLinksTest extends Unit
{
    public function testEventLinkLeadsToTheSkautisTheApplicationTalksTo(): void
    {
        $event = $this->event();
        self::assertNull((new SkautisLinks(new Config('app', true)))->event($event), 'not propagated yet');

        $event->linkSkautisEvent(14134, new DateTimeImmutable('2026-10-01'));
        self::assertSame('https://test-is.skaut.cz/Junak/Events/General/Detail.aspx?ID=14134', (new SkautisLinks(new Config('app', true)))->event($event));
        self::assertSame('https://is.skaut.cz/Junak/Events/General/Detail.aspx?ID=14134', (new SkautisLinks(new Config('app', false)))->event($event));
    }

    private function event(): Event
    {
        $input = new EventInput(
            name: 'Setkání přístavu',
            skautisTypeId: '9',
            skautisScopeId: '3',
            dateFrom: new DateTimeImmutable('2026-11-20'),
            dateTo: new DateTimeImmutable('2026-11-20'),
            place: null,
            description: null,
            registrationDeadline: null,
            capacity: null,
            price: null,
            contactName: 'Jan Novák',
            contactEmail: 'jan@example.cz',
            contactPhone: null,
        );

        return new Event(1002, 'setkani-pristavu', $input, 'token', 1, new DateTimeImmutable('2026-10-01'));
    }
}
