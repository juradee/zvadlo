<?php

declare(strict_types=1);

namespace App\Model\Web;

use App\Model\Event\Event;
use Doctrine\ORM\Mapping as ORM;

/**
 * An external link (photo album, meeting point on a map); the data stay outside the system.
 */
#[ORM\Entity]
#[ORM\Table(name: 'event_content_link')]
class LinkContent extends EventContent
{
    #[ORM\Column(length: 2000)]
    private string $url;

    public function __construct(Event $event, string $title, int $position, string $url)
    {
        parent::__construct($event, $title, $position);
        $this->url = $url;
    }

    public function getType(): ContentType
    {
        return ContentType::Link;
    }

    public function edit(string $title, string $url): void
    {
        $this->rename($title);
        $this->url = $url;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
