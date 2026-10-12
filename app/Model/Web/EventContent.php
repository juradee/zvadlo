<?php

declare(strict_types=1);

namespace App\Model\Web;

use App\Model\Event\Event;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * One block of the event's mini-web; the subclasses hold the type-specific data in their own tables.
 */
#[ORM\Entity]
#[ORM\Table(name: 'event_content')]
#[ORM\Index(name: 'idx_event_content_event', columns: ['event_id', 'position'])]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string', length: 10)]
#[ORM\DiscriminatorMap(['text' => TextContent::class, 'file' => FileContent::class, 'link' => LinkContent::class])]
abstract class EventContent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected Event $event;

    #[ORM\Column(length: 255)]
    protected string $title;

    #[ORM\Column]
    protected int $position;

    public function __construct(Event $event, string $title, int $position)
    {
        $this->event = $event;
        $this->title = $title;
        $this->position = $position;
    }

    abstract public function getType(): ContentType;

    public function rename(string $title): void
    {
        $this->title = $title;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getId(): int
    {
        return $this->id ?? throw new LogicException('The content has not been persisted yet.');
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
