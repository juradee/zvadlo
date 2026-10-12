<?php

declare(strict_types=1);

namespace App\Model\Registration;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * What happened to a registration and who did it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'registration_log')]
class RegistrationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Registration::class, inversedBy: 'logs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Registration $registration;

    #[ORM\Column]
    private DateTimeImmutable $at;

    #[ORM\Column(length: 20, enumType: LogActor::class)]
    private LogActor $actor;

    #[ORM\Column(nullable: true)]
    private ?int $actorUserId;

    #[ORM\Column(length: 255)]
    private string $message;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $changes;

    /**
     * @param array<string, mixed>|null $changes
     */
    public function __construct(Registration $registration, LogActor $actor, ?int $actorUserId, string $message, ?array $changes, DateTimeImmutable $at)
    {
        $this->registration = $registration;
        $this->actor = $actor;
        $this->actorUserId = $actorUserId;
        $this->message = $message;
        $this->changes = $changes;
        $this->at = $at;
    }

    public function getAt(): DateTimeImmutable
    {
        return $this->at;
    }

    public function getActor(): LogActor
    {
        return $this->actor;
    }

    public function getActorUserId(): ?int
    {
        return $this->actorUserId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getChanges(): ?array
    {
        return $this->changes;
    }
}
