<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Event\EventField;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'registration_text_value')]
class RegistrationTextValue
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Registration::class, inversedBy: 'textValues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Registration $registration;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: EventField::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private EventField $field;

    #[ORM\Column(type: 'text')]
    private string $value;

    public function __construct(Registration $registration, EventField $field, string $value)
    {
        $this->registration = $registration;
        $this->field = $field;
        $this->value = $value;
    }

    public function getField(): EventField
    {
        return $this->field;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}
