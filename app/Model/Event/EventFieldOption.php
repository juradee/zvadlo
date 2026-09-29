<?php

declare(strict_types=1);

namespace App\Model\Event;

use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * One option of a choice or multi-choice field.
 */
#[ORM\Entity]
#[ORM\Table(name: 'event_field_option')]
#[ORM\UniqueConstraint(name: 'uniq_field_option_position', columns: ['field_id', 'position'])]
class EventFieldOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EventField::class, inversedBy: 'options')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private EventField $field;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column]
    private int $position;

    public function __construct(EventField $field, string $label, int $position)
    {
        $this->field = $field;
        $this->label = $label;
        $this->position = $position;
    }

    public function getId(): int
    {
        if ($this->id === null) {
            throw new LogicException('The option has not been persisted yet.');
        }

        return $this->id;
    }

    public function getField(): EventField
    {
        return $this->field;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
