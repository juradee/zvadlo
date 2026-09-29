<?php

declare(strict_types=1);

namespace App\Model\Event;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * An event of one unit (výprava, výlet, tábor) people register for.
 */
#[ORM\Entity]
#[ORM\Table(name: 'event')]
#[ORM\UniqueConstraint(name: 'uniq_event_unit_slug', columns: ['unit_id', 'slug'])]
#[ORM\Index(name: 'idx_event_unit', columns: ['unit_id'])]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $unitId;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 100)]
    private string $slug;

    #[ORM\Column(length: 50)]
    private string $skautisTypeId;

    #[ORM\Column(length: 50)]
    private string $skautisScopeId;

    #[ORM\Column]
    private bool $isCamp;

    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $dateFrom;

    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $dateTo;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $place;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $registrationDeadline;

    #[ORM\Column(type: 'smallint')]
    private int $editLockDays;

    #[ORM\Column(nullable: true)]
    private ?int $capacity;

    /** Informational price in CZK; payments are not handled here. */
    #[ORM\Column(nullable: true)]
    private ?int $price;

    #[ORM\Column(length: 255)]
    private string $contactName;

    #[ORM\Column(length: 255)]
    private string $contactEmail;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $contactPhone;

    #[ORM\Column(length: 20, enumType: EventState::class)]
    private EventState $state = EventState::Draft;

    #[ORM\Column(nullable: true)]
    private ?int $skautisEventId = null;

    #[ORM\Column(nullable: true)]
    private ?int $skautisCampId = null;

    /** Shared secret of the mini-web link; stored in the clear because leaders need to show the link. */
    #[ORM\Column(length: 64)]
    private string $webToken;

    #[ORM\Column]
    private int $createdByUserId;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, EventField> */
    #[ORM\OneToMany(targetEntity: EventField::class, mappedBy: 'event', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $fields;

    public function __construct(int $unitId, string $slug, EventInput $input, string $webToken, int $createdByUserId, DateTimeImmutable $now)
    {
        $this->unitId = $unitId;
        $this->slug = $slug;
        $this->webToken = $webToken;
        $this->createdByUserId = $createdByUserId;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->fields = new ArrayCollection();
        $this->apply($input, $now);
    }

    public function update(EventInput $input, DateTimeImmutable $now): void
    {
        $this->apply($input, $now);
    }

    /**
     * @throws InvalidStateTransitionException
     */
    public function changeState(EventState $next, DateTimeImmutable $now): void
    {
        if (! $this->state->canTransitionTo($next)) {
            throw InvalidStateTransitionException::between($this->state, $next);
        }

        $this->state = $next;
        $this->updatedAt = $now;
    }

    /**
     * Participants may change their registration until this day (exclusive), leaders always.
     */
    public function getEditLockDate(): DateTimeImmutable
    {
        return $this->dateFrom->modify(\sprintf('-%d days', $this->editLockDays));
    }

    public function isEditLocked(DateTimeImmutable $now): bool
    {
        return $now->setTime(0, 0) >= $this->getEditLockDate();
    }

    public function isRegistrationOpen(DateTimeImmutable $now): bool
    {
        if ($this->state !== EventState::Open) {
            return false;
        }

        return $this->registrationDeadline === null || $now->setTime(0, 0) <= $this->registrationDeadline;
    }

    public function overlaps(self $other): bool
    {
        return $this->dateFrom <= $other->dateTo && $other->dateFrom <= $this->dateTo;
    }

    public function addField(EventField $field): void
    {
        $this->fields->add($field);
    }

    public function removeField(EventField $field): void
    {
        $this->fields->removeElement($field);
    }

    public function regenerateWebToken(string $webToken, DateTimeImmutable $now): void
    {
        $this->webToken = $webToken;
        $this->updatedAt = $now;
    }

    public function linkSkautisEvent(int $skautisEventId, DateTimeImmutable $now): void
    {
        $this->skautisEventId = $skautisEventId;
        $this->updatedAt = $now;
    }

    public function linkSkautisCamp(?int $skautisCampId, DateTimeImmutable $now): void
    {
        $this->skautisCampId = $skautisCampId;
        $this->updatedAt = $now;
    }

    public function getId(): int
    {
        if ($this->id === null) {
            throw new LogicException('The event has not been persisted yet.');
        }

        return $this->id;
    }

    public function getUnitId(): int
    {
        return $this->unitId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getSkautisTypeId(): string
    {
        return $this->skautisTypeId;
    }

    public function getSkautisScopeId(): string
    {
        return $this->skautisScopeId;
    }

    public function isCamp(): bool
    {
        return $this->isCamp;
    }

    public function getDateFrom(): DateTimeImmutable
    {
        return $this->dateFrom;
    }

    public function getDateTo(): DateTimeImmutable
    {
        return $this->dateTo;
    }

    public function getPlace(): ?string
    {
        return $this->place;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getRegistrationDeadline(): ?DateTimeImmutable
    {
        return $this->registrationDeadline;
    }

    public function getEditLockDays(): int
    {
        return $this->editLockDays;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function getContactName(): string
    {
        return $this->contactName;
    }

    public function getContactEmail(): string
    {
        return $this->contactEmail;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function getState(): EventState
    {
        return $this->state;
    }

    public function getSkautisEventId(): ?int
    {
        return $this->skautisEventId;
    }

    public function getSkautisCampId(): ?int
    {
        return $this->skautisCampId;
    }

    public function getWebToken(): string
    {
        return $this->webToken;
    }

    public function getCreatedByUserId(): int
    {
        return $this->createdByUserId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return list<EventField>
     */
    public function getFields(): array
    {
        return $this->fields->getValues();
    }

    /**
     * @return list<EventField>
     */
    public function getVisibleFields(): array
    {
        return array_values(array_filter($this->getFields(), static fn (EventField $field): bool => ! $field->isHidden()));
    }

    private function apply(EventInput $input, DateTimeImmutable $now): void
    {
        $this->name = $input->name;
        $this->skautisTypeId = $input->skautisTypeId;
        $this->skautisScopeId = $input->skautisScopeId;
        $this->isCamp = $input->isCamp;
        $this->dateFrom = $input->dateFrom;
        $this->dateTo = $input->dateTo;
        $this->place = $input->place;
        $this->description = $input->description;
        $this->registrationDeadline = $input->registrationDeadline;
        $this->editLockDays = $input->editLockDays;
        $this->capacity = $input->capacity;
        $this->price = $input->price;
        $this->contactName = $input->contactName;
        $this->contactEmail = $input->contactEmail;
        $this->contactPhone = $input->contactPhone;
        $this->updatedAt = $now;
    }
}
