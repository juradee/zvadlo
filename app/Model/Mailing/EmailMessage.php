<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use App\Model\Event\Event;
use App\Model\Registration\Registration;
use App\Model\Task\Task;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * Log of one outgoing e-mail: who got what, from which task, and whether it left.
 */
#[ORM\Entity]
#[ORM\Table(name: 'email_message')]
#[ORM\Index(name: 'idx_email_message_registration', columns: ['registration_id', 'type'])]
class EmailMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Task::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Task $task;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Event $event;

    #[ORM\ManyToOne(targetEntity: Registration::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Registration $registration;

    #[ORM\Column(length: 20, enumType: EmailType::class)]
    private EmailType $type;

    #[ORM\Column(length: 255)]
    private string $toEmail;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $toName;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\ManyToOne(targetEntity: GoogleOAuth::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GoogleOAuth $oauth;

    #[ORM\Column(length: 20, enumType: EmailMessageState::class)]
    private EmailMessageState $state;

    #[ORM\Column]
    private DateTimeImmutable $sentAt;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $error;

    public function __construct(
        ?Task $task,
        ?Event $event,
        ?Registration $registration,
        EmailType $type,
        string $toEmail,
        ?string $toName,
        string $subject,
        string $body,
        ?GoogleOAuth $oauth,
        ?string $error,
        DateTimeImmutable $sentAt,
    ) {
        $this->task = $task;
        $this->event = $event;
        $this->registration = $registration;
        $this->type = $type;
        $this->toEmail = $toEmail;
        $this->toName = $toName;
        $this->subject = $subject;
        $this->body = $body;
        $this->oauth = $oauth;
        $this->state = $error === null ? EmailMessageState::Sent : EmailMessageState::Failed;
        $this->error = $error === null ? null : mb_substr($error, 0, 1000);
        $this->sentAt = $sentAt;
    }

    public function getId(): int
    {
        return $this->id ?? throw new LogicException('The message has not been persisted yet.');
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function getRegistration(): ?Registration
    {
        return $this->registration;
    }

    public function getType(): EmailType
    {
        return $this->type;
    }

    public function getToEmail(): string
    {
        return $this->toEmail;
    }

    public function getToName(): ?string
    {
        return $this->toName;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getOauth(): ?GoogleOAuth
    {
        return $this->oauth;
    }

    public function getState(): EmailMessageState
    {
        return $this->state;
    }

    public function isSent(): bool
    {
        return $this->state === EmailMessageState::Sent;
    }

    public function getSentAt(): DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
