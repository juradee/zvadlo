<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Event;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventState;
use App\Model\Event\InvalidStateTransitionException;
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

    public function testEditLock(): void
    {
        $event = $this->event(dateFrom: '2026-11-20', editLockDays: 3);

        self::assertSame('2026-11-17', $event->getEditLockDate()->format('Y-m-d'));
        self::assertFalse($event->isEditLocked(new DateTimeImmutable('2026-11-16 23:59')));
        self::assertTrue($event->isEditLocked(new DateTimeImmutable('2026-11-17 00:00')));
        self::assertTrue($event->isEditLocked(new DateTimeImmutable('2026-11-25')));
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

    private function event(string $dateFrom = '2026-11-20', ?string $dateTo = null, ?string $deadline = null, int $editLockDays = 3): Event
    {
        $input = new EventInput(
            name: 'Výprava',
            skautisTypeId: 'vyprava',
            skautisScopeId: 'oddil',
            isCamp: false,
            dateFrom: new DateTimeImmutable($dateFrom),
            dateTo: new DateTimeImmutable($dateTo ?? $dateFrom),
            place: null,
            description: null,
            registrationDeadline: $deadline === null ? null : new DateTimeImmutable($deadline),
            editLockDays: $editLockDays,
            capacity: null,
            price: null,
            contactName: 'Jan Novák',
            contactEmail: 'jan@example.cz',
            contactPhone: null,
        );

        return new Event(1003, 'vyprava', $input, 'token', 1, new DateTimeImmutable('2026-10-01'));
    }
}
