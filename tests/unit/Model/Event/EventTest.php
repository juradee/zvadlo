<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Event;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventState;
use App\Model\Event\InvalidStateTransitionException;
use App\Model\Event\PublicRegistration;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class EventTest extends Unit
{
    public function testStateTransitions(): void
    {
        $now = new DateTimeImmutable('2026-10-01');
        $event = $this->event();

        self::assertSame(EventState::Draft, $event->getState());

        $event->changeState(EventState::Open, $now);
        $event->changeState(EventState::Closed, $now);
        $event->changeState(EventState::Open, $now);
        $event->changeState(EventState::Closed, $now);
        $event->changeState(EventState::Archived, $now);

        self::assertSame(EventState::Archived, $event->getState());
    }

    public function testInvalidTransitionsAreRejected(): void
    {
        $now = new DateTimeImmutable('2026-10-01');
        $event = $this->event();

        $this->expectException(InvalidStateTransitionException::class);
        $event->changeState(EventState::Closed, $now);
    }

    public function testArchivedEventIsFinal(): void
    {
        $now = new DateTimeImmutable('2026-10-01');
        $event = $this->event();
        $event->changeState(EventState::Open, $now);
        $event->changeState(EventState::Closed, $now);
        $event->changeState(EventState::Archived, $now);

        $this->expectException(InvalidStateTransitionException::class);
        $event->changeState(EventState::Open, $now);
    }

    public function testRegistrationIsOpenOnlyInOpenStateUntilDeadline(): void
    {
        $event = $this->event(dateFrom: '2026-11-20', deadline: '2026-11-10');
        $before = new DateTimeImmutable('2026-11-10 18:00');
        $after = new DateTimeImmutable('2026-11-11 08:00');

        self::assertFalse($event->isRegistrationOpen($before));

        $event->changeState(EventState::Open, $before);
        self::assertTrue($event->isRegistrationOpen($before));
        self::assertFalse($event->isRegistrationOpen($after));

        $noDeadline = $this->event(dateFrom: '2026-11-20');
        $noDeadline->changeState(EventState::Open, $before);
        self::assertTrue($noDeadline->isRegistrationOpen($after));
    }

    public function testOverlaps(): void
    {
        $event = $this->event(dateFrom: '2026-11-20', dateTo: '2026-11-22');

        self::assertTrue($event->overlaps($this->event(dateFrom: '2026-11-22', dateTo: '2026-11-23')));
        self::assertTrue($event->overlaps($this->event(dateFrom: '2026-11-18', dateTo: '2026-11-20')));
        self::assertFalse($event->overlaps($this->event(dateFrom: '2026-11-23', dateTo: '2026-11-24')));
    }

    public function testOneLimitForSigningUpChangingAndCancelling(): void
    {
        $event = $this->event(dateFrom: '2026-11-20', dateTo: '2026-11-22');
        $event->changeState(EventState::Open, new DateTimeImmutable('2026-10-01'));
        $event->update(new EventInput('Výprava', '2', '2', new DateTimeImmutable('2026-11-20'), new DateTimeImmutable('2026-11-22'), null, null, null, null, null, 'Jan Novák', 'jan@example.cz', null, PublicRegistration::Open), new DateTimeImmutable('2026-10-01'));

        self::assertSame('2026-11-22', $event->getLastChangeDay()->format('Y-m-d'), 'no deadline: until the end of the event');
        self::assertTrue($event->isPublicRegistrationOpen(new DateTimeImmutable('2026-11-22 23:59')), 'the last day still counts');
        self::assertFalse($event->isPublicRegistrationOpen(new DateTimeImmutable('2026-11-23')));
        self::assertFalse($event->acceptsSkautisLogin(new DateTimeImmutable('2026-11-25')), 'an event in the past takes nobody');
        self::assertNull($event->getLockNoticeDate(), 'no deadline, no notice');

        $event->changeState(EventState::Closed, new DateTimeImmutable('2026-11-01'));
        self::assertFalse($event->isRegistrationOpen(new DateTimeImmutable('2026-11-01')), 'closing the event ends it early');
    }

    private function event(string $dateFrom = '2026-11-20', ?string $dateTo = null, ?string $deadline = null): Event
    {
        $input = new EventInput(
            name: 'Výprava',
            skautisTypeId: '2',
            skautisScopeId: '2',
            dateFrom: new DateTimeImmutable($dateFrom),
            dateTo: new DateTimeImmutable($dateTo ?? $dateFrom),
            place: null,
            description: null,
            registrationDeadline: $deadline === null ? null : new DateTimeImmutable($deadline),
            capacity: null,
            price: null,
            contactName: 'Jan Novák',
            contactEmail: 'jan@example.cz',
            contactPhone: null,
        );

        return new Event(1003, 'vyprava', $input, 'token', 1, new DateTimeImmutable('2026-10-01'));
    }
}
