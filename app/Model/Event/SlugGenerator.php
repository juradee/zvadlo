<?php

declare(strict_types=1);

namespace App\Model\Event;

use Nette\Utils\Strings;

/**
 * URL slug of an event, unique within the unit: "letni-tabor", "letni-tabor-2", ...
 */
final class SlugGenerator
{
    public function __construct(private readonly EventRepository $events)
    {
    }

    public function generate(int $unitId, string $name, ?int $ignoreEventId = null): string
    {
        $base = Strings::webalize(Strings::truncate($name, 80, ''));
        if ($base === '') {
            $base = 'akce';
        }

        $slug = $base;
        for ($suffix = 2; $this->events->slugExists($unitId, $slug, $ignoreEventId); ++$suffix) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
