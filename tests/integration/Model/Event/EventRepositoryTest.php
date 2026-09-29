<?php

declare(strict_types=1);

namespace App\Tests\Integration\Model\Event;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventRepository;
use App\Model\Event\EventService;
use App\Model\Event\EventState;
use App\Tests\Support\IntegrationTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class EventRepositoryTest extends Unit
{
    protected IntegrationTester $tester;

    private EventService $service;

    private EventRepository $events;

    protected function _before(): void
    {
        $this->tester->grabService(EntityManagerInterface::class)
            ->createQuery('DELETE FROM '.Event::class)
            ->execute();

        $this->service = $this->tester->grabService(EventService::class);
        $this->events = $this->tester->grabService(EventRepository::class);
    }

    public function testCreateListAndFilter(): void
    {
        $trip = $this->service->create(1003, $this->input('Podzimní výprava', '2026-11-20', '2026-11-22'), 1);
        $this->service->create(1003, $this->input('Podzimní výprava', '2026-11-27'), 1);
        $other = $this->service->create(1004, $this->input('Vánoční výlet', '2026-12-12'), 1);
        $lastYear = $this->service->create(1003, $this->input('Loňský tábor', '2025-07-01', '2025-07-14'), 1);
        $this->service->changeState($trip, EventState::Open);

        self::assertSame('podzimni-vyprava', $trip->getSlug());
        self::assertSame($trip, $this->events->findBySlug(1003, 'podzimni-vyprava'));
        self::assertSame('podzimni-vyprava-2', $this->events->findByUnits([1003], 2026)[0]->getSlug());

        self::assertCount(3, $this->events->findByUnits([1003, 1004], 2026));
        self::assertCount(2, $this->events->findByUnits([1003], 2026));
        self::assertSame([$other->getId()], array_map(static fn (Event $e): int => $e->getId(), $this->events->findByUnits([1004])));
        self::assertSame([$trip->getId()], array_map(static fn (Event $e): int => $e->getId(), $this->events->findByUnits([1003], 2026, EventState::Open)));
        self::assertSame([], $this->events->findByUnits([]));

        self::assertSame([2026, 2025], $this->events->findYears([1003, 1004]));

        $upcoming = $this->events->findUpcoming([1003, 1004], new DateTimeImmutable('2026-11-21'), 2);
        self::assertSame(['Podzimní výprava', 'Podzimní výprava'], array_map(static fn (Event $e): string => $e->getName(), $upcoming));
        self::assertNotContains($lastYear->getId(), array_map(static fn (Event $e): int => $e->getId(), $upcoming));
    }

    private function input(string $name, string $from, ?string $to = null): EventInput
    {
        return new EventInput(
            name: $name,
            skautisTypeId: 'vyprava',
            skautisScopeId: 'oddil',
            isCamp: false,
            dateFrom: new DateTimeImmutable($from),
            dateTo: new DateTimeImmutable($to ?? $from),
            place: null,
            description: null,
            registrationDeadline: null,
            editLockDays: 3,
            capacity: null,
            price: null,
            contactName: 'Jan Novák',
            contactEmail: 'jan@example.cz',
            contactPhone: null,
        );
    }
}
