<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Model\Event\Event;
use App\Model\Event\EventField;
use App\Model\Event\EventFieldType;
use App\Model\Event\EventInput;
use App\Model\Event\EventService;
use App\Model\Event\EventState;
use App\Model\Event\PublicRegistration;
use App\Model\Skautis\Dto\Camp;
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
            skautisTypeId: '2',
            skautisScopeId: '2',
            dateFrom: $nextMonth->modify('+4 days'),
            dateTo: $nextMonth->modify('+6 days'),
            place: 'Základna Jelení louka',
            description: "Sraz v pátek v 16:30 na nádraží.\n\nS sebou spacák, karimatku a dobrou náladu.",
            registrationDeadline: $nextMonth,
            capacity: 24,
            price: 450,
            contactName: 'Jan Novák',
            contactEmail: 'jan.novak@example.cz',
            contactPhone: '+420 777 000 100',
        ), EventService::token(), 1, $now);
        $trip->changeState(EventState::Open, $now);
        // One field of every type, so the forms, the detail and the export show them.
        $trip->addField(new EventField($trip, EventFieldType::Text, 'Alergie a léky', 'Léky s dávkováním, alergie včetně potravinových.', false, 1));
        $trip->addField(new EventField($trip, EventFieldType::Choice, 'Doprava', null, true, 2, ['autobus', 'vlastní']));
        $trip->addField(new EventField($trip, EventFieldType::MultiChoice, 'Přijede na', 'Lze vybrat více dnů.', false, 3, ['pátek', 'sobota', 'neděle']));
        $trip->addField(new EventField($trip, EventFieldType::Boolean, 'Souhlasím s focením účastníka na akci', 'Fotky jen pro kroniku a web oddílu.', false, 4));

        $party = new Event($unitId, 'vanocni-besidka', new EventInput(
            name: 'Vánoční besídka střediska',
            skautisTypeId: '9',
            skautisScopeId: '3',
            dateFrom: $now->modify('20 december')->setTime(0, 0),
            dateTo: $now->modify('20 december')->setTime(0, 0),
            place: 'Klubovna',
            description: null,
            registrationDeadline: null,
            capacity: null,
            price: null,
            contactName: 'Jan Novák',
            contactEmail: 'jan.novak@example.cz',
            contactPhone: null,
            publicRegistration: PublicRegistration::Open,
        ), EventService::token(), 1, $now);
        $party->changeState(EventState::Open, $now);
        $party->addField(new EventField($party, EventFieldType::Boolean, 'Potvrzuji, že účastník přijde až po skončení školní družiny', null, true, 1));
        $party->addField(new EventField($party, EventFieldType::Text, 'Co přinese na stůl', 'Nepovinné: cukroví, chlebíčky…', false, 2));

        $campYear = (int) $now->format('Y') - 1;
        $camp = new Event($troopId, Strings::webalize('Letní tábor '.$campYear), new EventInput(
            name: 'Letní tábor '.$campYear,
            skautisTypeId: null,
            skautisScopeId: null,
            dateFrom: $now->modify('-1 year')->modify('1 july')->setTime(0, 0),
            dateTo: $now->modify('-1 year')->modify('14 july')->setTime(0, 0),
            place: 'Tábořiště U Tří dubů',
            description: null,
            registrationDeadline: null,
            capacity: null,
            price: 3200,
            contactName: 'Jan Novák',
            contactEmail: 'jan.novak@example.cz',
            contactPhone: null,
        ), EventService::token(), 1, $now);
        $camp->linkSkautisCamp(new Camp(
            5000,
            'Letní tábor '.$campYear,
            $troopId,
            'Oddíl',
            $now->modify('-1 year')->modify('1 july')->setTime(0, 0),
            $now->modify('-1 year')->modify('14 july')->setTime(0, 0),
            'Tábořiště U Tří dubů',
            'approvedParent',
            'Schválený',
        ), $now);
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
    public static function unitIds(): array
    {
        $ids = array_map(intval(...), explode(',', (string) (getenv('FIXTURE_UNIT_IDS') ?: '1002,1003')));

        return [$ids[0] ?? 1002, $ids[1] ?? $ids[0] ?? 1003];
    }
}
