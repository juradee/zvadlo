<?php

declare(strict_types=1);

namespace App\Model\Event;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * A custom field of the registration form of one event.
 */
#[ORM\Entity]
#[ORM\Table(name: 'event_field')]
class EventField
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'fields')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\Column(length: 20, enumType: EventFieldType::class)]
    private EventFieldType $type;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $help;

    #[ORM\Column]
    private bool $required;

    #[ORM\Column]
    private int $position;

    #[ORM\Column]
    private bool $hidden = false;

    /** @var Collection<int, EventFieldOption> */
    #[ORM\OneToMany(targetEntity: EventFieldOption::class, mappedBy: 'field', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $options;

    /**
     * @param list<string> $options
     */
    public function __construct(Event $event, EventFieldType $type, string $label, ?string $help, bool $required, int $position, array $options = [])
    {
        $this->event = $event;
        $this->type = $type;
        $this->position = $position;
        $this->options = new ArrayCollection();
        $this->edit($label, $help, $required, $options);
    }

    /**
     * @param list<string> $options
     */
    public function edit(string $label, ?string $help, bool $required, array $options): void
    {
        $this->label = $label;
        $this->help = $help;
        $this->required = $required;

        if (! $this->type->hasOptions()) {
            $this->options->clear();

            return;
        }

        $this->options->clear();
        foreach (array_values($options) as $position => $optionLabel) {
            $this->options->add(new EventFieldOption($this, $optionLabel, $position));
        }
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getId(): int
    {
        if ($this->id === null) {
            throw new LogicException('The field has not been persisted yet.');
        }

        return $this->id;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getType(): EventFieldType
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getHelp(): ?string
    {
        return $this->help;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * @return list<EventFieldOption>
     */
    public function getOptions(): array
    {
        return $this->options->getValues();
    }

    /**
     * @return list<string>
     */
    public function getOptionLabels(): array
    {
        return array_map(static fn (EventFieldOption $option): string => $option->getLabel(), $this->getOptions());
    }
}
