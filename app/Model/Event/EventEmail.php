<?php

declare(strict_types=1);

namespace App\Model\Event;

use App\Model\Mailing\EmailTemplate;
use App\Model\Mailing\EmailType;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * The event's own text for one kind of e-mail, copied from the unit's defaults when the event is created.
 */
#[ORM\Entity]
#[ORM\Table(name: 'event_email')]
#[ORM\UniqueConstraint(name: 'uniq_event_email_type', columns: ['event_id', 'type'])]
class EventEmail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'emails')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\Column(length: 20, enumType: EmailType::class)]
    private EmailType $type;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $body;

    public function __construct(Event $event, EmailTemplate $template)
    {
        $this->event = $event;
        $this->type = $template->type;
        $this->subject = $template->subject;
        $this->body = $template->body;
    }

    public function edit(bool $enabled, string $subject, string $body): void
    {
        $this->enabled = $enabled || ! $this->type->isOptional();
        $this->subject = $subject;
        $this->body = $body;
    }

    public function getId(): int
    {
        return $this->id ?? throw new LogicException('The e-mail has not been persisted yet.');
    }

    public function getType(): EmailType
    {
        return $this->type;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function toTemplate(): EmailTemplate
    {
        return new EmailTemplate($this->type, $this->subject, $this->body);
    }
}
