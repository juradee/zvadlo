<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Model\Event\Event;
use App\Model\Web\LinkContent;
use App\Model\Web\TextContent;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Mini-web blocks for "Podzimní výprava".
 */
final class WebFixture implements FixtureInterface, DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $event = $manager->getRepository(Event::class)->findOneBy(['slug' => 'podzimni-vyprava']);
        if ($event === null) {
            return;
        }

        $manager->persist(new TextContent($event, 'Co s sebou', 1, "- spacák a karimatku\n- **jídlo na pátek večer**\n- pláštěnku, baterku, KPZ\n- 200 Kč na vlak"));
        $manager->persist(new TextContent($event, 'Sraz a návrat', 2, "Sraz v pátek v 16:30 na hlavním nádraží u pokladen.\n\nNávrat v neděli kolem 17:00 tamtéž; přesný čas pošleme SMS rodičům."));
        $manager->persist(new LinkContent($event, 'Fotky z loňské výpravy', 3, 'https://photos.app.goo.gl/ukazka'));
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [EventFixture::class];
    }
}
