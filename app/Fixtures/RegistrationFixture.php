<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Model\Event\Event;
use App\Model\Event\EventField;
use App\Model\Event\EventFieldOption;
use App\Model\Registration\RecipientSource;
use App\Model\Registration\Registration;
use App\Model\Registration\RegistrationAnswer;
use App\Model\Registration\RegistrationToken;
use App\Model\Security\Crypto;
use App\Model\Skautis\Dto\Person;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;
use LogicException;
use Nette\DI\Container;
use Nettrine\Fixtures\Fixture\ContainerAwareInterface;

/**
 * A few registrations of the fixture dataset persons for "Podzimní výprava"; addresses are set by hand
 * so nothing is looked up in skautIS on the development machine.
 */
final class RegistrationFixture implements FixtureInterface, DependentFixtureInterface, ContainerAwareInterface
{
    private Crypto $crypto;

    public function setContainer(Container $container): void
    {
        $this->crypto = $container->getByType(Crypto::class);
    }

    public function load(ObjectManager $manager): void
    {
        $event = $manager->getRepository(Event::class)->findOneBy(['slug' => 'podzimni-vyprava']);
        if ($event === null) {
            return;
        }

        $now = new DateTimeImmutable();
        $anna = Registration::inviteMember($event, new Person(101, 'Anna', 'Malá', '', new DateTimeImmutable('2015-03-12'), 'female', $event->getUnitId()), $this->token(), 1, $now);
        $anna->addRecipient('matka.mala@example.cz', 'Matka', RecipientSource::Mother);
        $anna->addRecipient('otec.maly@example.cz', 'Otec', RecipientSource::Father);
        $anna->addRecipient('anna.mala@example.cz', null, RecipientSource::Main, selected: false);
        $anna->markInvited($now);
        $anna->markSeen($now);
        $anna->respond(new RegistrationAnswer(attending: true, contactEmail: 'matka.mala@example.cz', phone: '+420 777 000 201', note: 'Přijedeme až v sobotu ráno.'), true, $now);
        $anna->setTextValue($this->field($event, 'Alergie a léky'), 'ořechy');
        $anna->setOptionValues($this->field($event, 'Doprava'), $this->options($event, 'Doprava', ['autobus']));
        $anna->setOptionValues($this->field($event, 'Přijede na'), $this->options($event, 'Přijede na', ['sobota', 'neděle']));
        $anna->setBooleanValue($this->field($event, 'Souhlasím s focením účastníka na akci'), true);

        $petr = Registration::inviteMember($event, new Person(102, 'Petr', 'Malý', 'Péťa', new DateTimeImmutable('2013-08-20'), 'male', $event->getUnitId()), $this->token(), 1, $now);
        $petr->addRecipient('matka.mala@example.cz', 'Matka', RecipientSource::Mother);
        $petr->markInvited($now);

        $karel = Registration::inviteMember($event, new Person(105, 'Karel', 'Bezmailu', '', new DateTimeImmutable('2014-06-06'), 'male', $event->getUnitId()), $this->token(), 1, $now);

        $guest = Registration::inviteNonMember($event, 'pavel.host@example.cz', 'Pavel', 'Host', $this->token(), 1, $now);
        $guest->markInvited($now);
        $guest->markSeen($now);
        $guest->respond(new RegistrationAnswer(attending: false, declineReason: 'Kolize s turnajem.'), true, $now);

        foreach ([$anna, $petr, $karel, $guest] as $registration) {
            $manager->persist($registration);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [EventFixture::class];
    }

    private function field(Event $event, string $label): EventField
    {
        foreach ($event->getFields() as $field) {
            if ($field->getLabel() === $label) {
                return $field;
            }
        }

        throw new LogicException('EventFixture has no field '.$label);
    }

    /**
     * @param list<string> $labels
     *
     * @return list<EventFieldOption>
     */
    private function options(Event $event, string $fieldLabel, array $labels): array
    {
        return array_values(array_filter(
            $this->field($event, $fieldLabel)->getOptions(),
            static fn (EventFieldOption $option): bool => \in_array($option->getLabel(), $labels, true),
        ));
    }

    private function token(): RegistrationToken
    {
        return RegistrationToken::generate($this->crypto);
    }
}
