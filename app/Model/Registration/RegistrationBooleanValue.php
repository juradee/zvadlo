<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Event\EventField;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'registration_boolean_value')]
class RegistrationBooleanValue
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Registration::class, inversedBy: 'booleanValues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Registration $registration;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: EventField::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private EventField $field;

    #[ORM\Column]
    private bool $value;

    public function __construct(Registration $registration, EventField $field, bool $value)
    {
        $this->registration = $registration;
        $this->field = $field;
        $this->value = $value;
    }

    public function getField(): EventField
    {
        return $this->field;
    }

    public function getValue(): bool
    {
        return $this->value;
    }

    public function setValue(bool $value): void
    {
        $this->value = $value;
    }
}
