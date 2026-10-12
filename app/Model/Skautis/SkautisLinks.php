<?php

declare(strict_types=1);

namespace App\Model\Skautis;

use App\Model\Event\Event;
use Skaut\Skautis\Config;

/**
 * Links to skautIS pages, to the test or the production skautIS the application talks to.
 */
final readonly class SkautisLinks
{
    public function __construct(private Config $config)
    {
    }

    public function event(Event $event): ?string
    {
        if ($event->isCamp()) {
            return $this->page('Camp', $event->getSkautisCampId());
        }

        return $this->page('General', $event->getSkautisEventId());
    }

    private function page(string $kind, ?int $id): ?string
    {
        return $id === null ? null : $this->config->getBaseUrl().'Junak/Events/'.$kind.'/Detail.aspx?ID='.$id;
    }
}
