<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Event;

use App\Model\Event\EventRepository;
use App\Model\Event\SlugGenerator;
use Codeception\Test\Unit;

final class SlugGeneratorTest extends Unit
{
    public function testWebalizesAndAddsSuffixOnCollision(): void
    {
        $taken = ['letni-tabor', 'letni-tabor-2'];
        $events = $this->createMock(EventRepository::class);
        $events->method('slugExists')->willReturnCallback(
            static fn (int $unitId, string $slug): bool => $unitId === 1003 && \in_array($slug, $taken, true),
        );

        $generator = new SlugGenerator($events);

        self::assertSame('letni-tabor-3', $generator->generate(1003, 'Letní tábor'));
        self::assertSame('letni-tabor', $generator->generate(1004, 'Letní tábor'));
        self::assertSame('vyprava-na-snezku-2027', $generator->generate(1003, 'Výprava na Sněžku 2027!'));
        self::assertSame('akce', $generator->generate(1003, '***'));
    }
}
