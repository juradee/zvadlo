<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Registration;

use App\Model\Registration\FoundAddress;
use App\Model\Registration\MemberAddresses;
use App\Model\Registration\RecipientSource;
use App\Model\Skautis\Dto\Contact;
use App\Model\Skautis\Dto\ParentContact;
use App\Model\Skautis\SkautisGateway;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use Nette\Caching\Storages\MemoryStorage;

final class MemberAddressesTest extends Unit
{
    private DateTimeImmutable $now;

    protected function _before(): void
    {
        $this->now = new DateTimeImmutable('2026-10-05');
    }

    protected function _after(): void
    {
        Mockery::close();
    }

    public function testChildIsOfferedEveryAddressWithTheParentsChosen(): void
    {
        $gateway = $this->gateway();
        $gateway->shouldReceive('getPersonParents')->with(101)->once()->andReturn([
            new ParentContact(ParentContact::MOTHER, 'Matka', 'Matka@example.cz'),
            new ParentContact(ParentContact::FATHER, 'Otec', 'otec@example.cz'),
        ]);
        $gateway->shouldReceive('getPersonContacts')->with(101)->once()->andReturn([
            new Contact(Contact::EMAIL, 'anna@example.cz'),
            new Contact('email_dalsi', 'anna2@example.cz'),
            new Contact('email_dalsi', 'matka@example.cz'),
            new Contact(Contact::PHONE, '+420 777 000 101'),
        ]);
        $addresses = new MemberAddresses($gateway, new MemoryStorage());

        $contacts = $addresses->find(101, new DateTimeImmutable('2015-03-12'), $this->now);
        self::assertSame(
            [['matka@example.cz', RecipientSource::Mother, true], ['otec@example.cz', RecipientSource::Father, true], ['anna@example.cz', RecipientSource::Main, false], ['anna2@example.cz', RecipientSource::Other, false]],
            array_map(static fn (FoundAddress $address): array => [$address->email, $address->source, $address->selected], $contacts->addresses),
            'parents are chosen for a child, an address is offered once',
        );
        self::assertSame('+420 777 000 101', $contacts->phone);
        self::assertSame('anna@example.cz', $contacts->find(' Anna@example.cz')?->email);
        $addresses->find(101, new DateTimeImmutable('2015-03-12'), $this->now);
    }

    public function testChildWithoutParentEmailGetsTheOwnMainAddress(): void
    {
        $gateway = $this->gateway();
        $gateway->shouldReceive('getPersonParents')->with(102)->andReturn([new ParentContact(ParentContact::MOTHER, 'Matka', '')]);
        $gateway->shouldReceive('getPersonContacts')->with(102)->andReturn([new Contact(Contact::EMAIL, 'petr@example.cz')]);

        $contacts = (new MemberAddresses($gateway, new MemoryStorage()))->find(102, new DateTimeImmutable('2013-08-20'), $this->now);
        self::assertSame([['petr@example.cz', true]], array_map(static fn (FoundAddress $address): array => [$address->email, $address->selected], $contacts->addresses));
    }

    public function testAdultIsNeverAskedForParents(): void
    {
        $gateway = $this->gateway();
        $gateway->shouldReceive('getPersonParents')->never();
        $gateway->shouldReceive('getPersonContacts')->with(104)->andReturn([new Contact(Contact::EMAIL, 'tomas@example.cz')]);

        $contacts = (new MemberAddresses($gateway, new MemoryStorage()))->find(104, new DateTimeImmutable('2004-11-11'), $this->now);
        self::assertSame([['tomas@example.cz', true]], array_map(static fn (FoundAddress $address): array => [$address->email, $address->selected], $contacts->addresses));
        self::assertNull($contacts->phone);
    }

    /** @return SkautisGateway&MockInterface */
    private function gateway(): SkautisGateway
    {
        /** @var SkautisGateway&MockInterface $gateway */
        $gateway = Mockery::mock(SkautisGateway::class);

        return $gateway;
    }
}
