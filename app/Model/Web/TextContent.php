<?php

declare(strict_types=1);

namespace App\Model\Web;

use App\Model\Event\Event;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_content_text')]
class TextContent extends EventContent
{
    #[ORM\Column(type: 'text')]
    private string $body;

    public function __construct(Event $event, string $title, int $position, string $body)
    {
        parent::__construct($event, $title, $position);
        $this->body = $body;
    }

    public function getType(): ContentType
    {
        return ContentType::Text;
    }

    public function edit(string $title, string $body): void
    {
        $this->rename($title);
        $this->body = $body;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
