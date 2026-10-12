<?php

declare(strict_types=1);

namespace App\Tests\Integration\Model\Registration;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventService;
use App\Model\Event\EventState;
use App\Model\Event\PublicRegistration;
use App\Model\Registration\DuplicateRegistrationException;
use App\Model\Registration\PublicMatch;
use App\Model\Registration\PublicRegistrationService;
use App\Model\Registration\RegistrationAnswer;
use App\Model\Registration\RegistrationNotAllowedException;
use App\Model\Registration\RegistrationOrigin;
use App\Model\Skautis\Dto\Person;
use App\Model\Task\Task;
use App\Model\Task\TaskState;
use App\Tests\Support\IntegrationTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sign-up of a person proven by a skautIS login (spec 4.6) in the "skautIS only" mode.
 */
final class SkautisRegistrationTest extends Unit
{
    protected IntegrationTester $tester;

    public function testPersonFromAnotherUnitIsPairedAndTheFormPathStaysClosed(): void
    {
        $entityManager = $this->tester->grabService(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $entityManager->createQuery('DELETE FROM '.Task::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Event::class)->execute();
        $events = $this->tester->grabService(EventService::class);
        \assert($events instanceof EventService);
        $service = $this->tester->grabService(PublicRegistrationService::class);
        \assert($service instanceof PublicRegistrationService);

        $event = $events->create(1003, new EventInput('Okresní závod', 'zavod', 'okres', new DateTimeImmutable('+30 days'), new DateTimeImmutable('+30 days'), 'Blansko', null, null, null, null, 'Jan Novák', 'jan@example.cz', null, PublicRegistration::Skautis), 1);
        $events->changeState($event, EventState::Open);
        self::assertTrue($event->acceptsSkautisLogin(new DateTimeImmutable()));
        self::assertFalse($event->isPublicRegistrationOpen(new DateTimeImmutable()), 'the plain form is closed in this mode');

        $tomas = new Person(106, 'Tomáš', 'Novák', '', new DateTimeImmutable('2012-04-01'), 'male', null);
        $answer = new RegistrationAnswer(attending: true, contactEmail: 'jan.novak@example.cz', phone: '+420 777 000 106');
        $registration = $service->registerViaSkautis($event, $answer, $tomas, '127.0.0.1');

        self::assertSame(RegistrationOrigin::Skautis, $registration->getOrigin());
        self::assertTrue($registration->isMember());
        self::assertSame(106, $registration->getSkautisPersonId());
        self::assertSame('Novák Tomáš', $registration->getDisplayName());
        self::assertTrue($registration->getState()->isAttending());
        self::assertSame('jan.novak@example.cz', $registration->getContactEmail());

        try {
            $service->registerViaSkautis($event, $answer, $tomas, '127.0.0.1');
            self::fail('the same person cannot sign up twice');
        } catch (DuplicateRegistrationException) {
        }

        $tasks = $this->tester->grabService(\App\Model\Task\TaskRepository::class);
        \assert($tasks instanceof \App\Model\Task\TaskRepository);
        self::assertSame(['confirmation'], array_map(static fn (Task $task): string => $task->getPayloadString('type'), $tasks->findAutomaticByEvent($event, TaskState::Queued)), 'the confirmation with the personal link is queued');

        // A rejected e-mail stays blocked even behind another skautIS account.
        $registrations = $this->tester->grabService(\App\Model\Registration\RegistrationService::class);
        \assert($registrations instanceof \App\Model\Registration\RegistrationService);
        $other = $service->registerViaSkautis($event, new RegistrationAnswer(attending: true, contactEmail: 'cizi@example.cz'), new Person(555, 'Cizí', 'Člověk', '', new DateTimeImmutable('2000-01-01'), 'male', null), '127.0.0.1');
        $registrations->reject($other, 1, 'nepatří k nám');
        try {
            $service->registerViaSkautis($event, new RegistrationAnswer(attending: true, contactEmail: 'cizi@example.cz'), new Person(556, 'Cizí', 'Člověk', '', new DateTimeImmutable('2000-01-01'), 'male', null), '127.0.0.1');
            self::fail('a rejected e-mail must not come back through skautIS');
        } catch (DuplicateRegistrationException) {
        }

        try {
            $service->register($event, new RegistrationAnswer(attending: true, contactEmail: 'x@example.cz', firstName: 'X', lastName: 'Y', birthDate: new DateTimeImmutable('2010-01-01')), new PublicMatch(null, null), '127.0.0.1');
            self::fail('the form path must refuse the skautIS-only mode');
        } catch (RegistrationNotAllowedException) {
        }
    }
}
