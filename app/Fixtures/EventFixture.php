<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventService;
use App\Model\Event\EventState;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Nette\Utils\Strings;

/**
 * A few events for local development. Unit ids come from FIXTURE_UNIT_IDS (two ids: středisko, oddíl),
 * by default the units of the skautIS fixture dataset.
 */
final class EventFixture implements FixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        [$unitId, $troopId] = self::unitIds();
        $now = new DateTimeImmutable();
        $nextMonth = $now->modify('first day of next month')->setTime(0, 0);

        $trip = new Event($troopId, 'podzimni-vyprava', new EventInput(
            name: 'Podzimní výprava',
            skautisTypeId: 'vyprava',
            skautisScopeId: 'oddil',
            isCamp: false,
            dateFrom: $nextMonth->modify('+4 days'),
            dateTo: $nextMonth->modify('+6 days'),
            place: 'Základna Jelení louka',
            description: "Sraz v pátek v 16:30 na nádraží.\n\nS sebou spacák, karimatku a dobrou náladu.",
            registrationDeadline: $nextMonth,
            editLockDays: 3,
            capacity: 24,
            price: 450,
            contactName: 'Jan Novák',
            contactEmail: 'jan.novak@example.cz',
            contactPhone: '+420 777 000 100',
        ), EventService::token(), 1, $now);
        $trip->changeState(EventState::Open, $now);

        $party = new Event($unitId, 'vanocni-besidka', new EventInput(
            name: 'Vánoční besídka střediska',
            skautisTypeId: 'jina',
            skautisScopeId: 'stredisko',
            isCamp: false,
            dateFrom: $now->modify('20 december')->setTime(0, 0),
            dateTo: $now->modify('20 december')->setTime(0, 0),
            place: 'Klubovna',
            description: null,
            registrationDeadline: null,
            editLockDays: 1,
            capacity: null,
            price: null,
            contactName: 'Jan Novák',
            contactEmail: 'jan.novak@example.cz',
            contactPhone: null,
        ), EventService::token(), 1, $now);

        $camp = new Event($troopId, Strings::webalize('Letní tábor '.($now->format('Y') - 1)), new EventInput(
            name: 'Letní tábor '.($now->format('Y') - 1),
            skautisTypeId: 'vyprava',
            skautisScopeId: 'oddil',
            isCamp: true,
            dateFrom: $now->modify('-1 year')->modify('1 july')->setTime(0, 0),
            dateTo: $now->modify('-1 year')->modify('14 july')->setTime(0, 0),
            place: 'Tábořiště U Tří dubů',
            description: null,
            registrationDeadline: null,
            editLockDays: 7,
            capacity: null,
            price: 3200,
            contactName: 'Jan Novák',
            contactEmail: 'jan.novak@example.cz',
            contactPhone: null,
        ), EventService::token(), 1, $now);
        $camp->changeState(EventState::Open, $now);
        $camp->changeState(EventState::Closed, $now);
        $camp->changeState(EventState::Archived, $now);

        foreach ([$trip, $party, $camp] as $event) {
            $manager->persist($event);
        }
        $manager->flush();
    }

    /**
     * @return array{int, int}
     */
    private static function unitIds(): array
    {
        $ids = array_map(intval(...), explode(',', (string) (getenv('FIXTURE_UNIT_IDS') ?: '1002,1003')));

        return [$ids[0] ?? 1002, $ids[1] ?? $ids[0] ?? 1003];
    }
}
