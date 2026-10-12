<?php

declare(strict_types=1);

namespace App\Model\Registration;

use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * An address found for (or added to) an invitation; only selected addresses receive mail.
 */
#[ORM\Entity]
#[ORM\Table(name: 'registration_recipient')]
#[ORM\UniqueConstraint(name: 'uniq_recipient_registration_email', columns: ['registration_id', 'email'])]
class RegistrationRecipient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Registration::class, inversedBy: 'recipients')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Registration $registration;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name;

    #[ORM\Column(length: 20, enumType: RecipientSource::class)]
    private RecipientSource $source;

    #[ORM\Column]
    private bool $selected;

    public function __construct(Registration $registration, string $email, ?string $name, RecipientSource $source, bool $selected)
    {
        $this->registration = $registration;
        $this->email = $email;
        $this->name = $name;
        $this->source = $source;
        $this->selected = $selected;
    }

    public function setSelected(bool $selected): void
    {
        $this->selected = $selected;
    }

    public function getId(): int
    {
        if ($this->id === null) {
            throw new LogicException('The recipient has not been persisted yet.');
        }

        return $this->id;
    }

    public function getRegistration(): Registration
    {
        return $this->registration;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getSource(): RecipientSource
    {
        return $this->source;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }
}
