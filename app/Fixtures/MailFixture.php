<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Model\Event\Event;
use App\Model\Mailing\EmailType;
use App\Model\Mailing\GoogleOAuth;
use App\Model\Mailing\UnitEmailTemplate;
use App\Model\Security\Crypto;
use App\Model\Unit\UnitSettings;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Nette\DI\Container;
use Nettrine\Fixtures\Fixture\ContainerAwareInterface;

/**
 * Mail set-up for local development: a "connected" Gmail account per fixture unit with a fake refresh token
 * (dev sends through Mailpit, so the token is never used), unit settings pointing at it, every fixture event
 * sending from it, and one unit-level template so the "vlastní text" badge shows.
 */
final class MailFixture implements FixtureInterface, DependentFixtureInterface, ContainerAwareInterface
{
    private Crypto $crypto;

    public function setContainer(Container $container): void
    {
        $this->crypto = $container->getByType(Crypto::class);
    }

    public function load(ObjectManager $manager): void
    {
        [$unitId, $troopId] = EventFixture::unitIds();
        $now = new DateTimeImmutable();

        $accounts = [];
        foreach ([$unitId => 'stredisko.slunecni.hodiny@example.cz', $troopId => 'vlcata.3oddil@example.cz'] as $ownerUnitId => $email) {
            $account = new GoogleOAuth($ownerUnitId, $email, $this->crypto->encrypt('fixture-refresh-token'), $now);
            $manager->persist($account);
            $accounts[$ownerUnitId] = $account;

            $settings = new UnitSettings($ownerUnitId);
            $settings->edit(
                $ownerUnitId === $troopId ? 'Souhlasím s účastí na akci a s pravidly oddílu.' : null,
                $account,
            );
            $manager->persist($settings);
        }

        $manager->persist(new UnitEmailTemplate(
            $troopId,
            EmailType::Rejected,
            'Přihláška na %akce% nebyla přijata',
            "Ahoj,\n\npřihlášku na akci **%akce%** (%termin%) jsme nemohli přijmout, akce je určená jen členům oddílu.\n\n%poznamka%\n\nDotazy: %kontakt%\n%jednotka%",
        ));

        foreach ($manager->getRepository(Event::class)->findAll() as $event) {
            $event->setMailAccount($accounts[$event->getUnitId()] ?? $accounts[$troopId], $now);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [EventFixture::class];
    }
}
