<?php

declare(strict_types=1);

namespace App\Model\Event;

use App\Model\Mailing\EmailTemplate;
use App\Model\Mailing\EmailType;
use App\Model\Mailing\GoogleOAuth;
use App\Model\Skautis\Dto\Camp;
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

    /** Type and scope of a general event (skautIS code lists); a camp has neither. */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $skautisTypeId;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $skautisScopeId;

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

    /** Without free capacity new attendees go to the waiting list; otherwise the event refuses them. */
    #[ORM\Column(options: ['default' => true])]
    private bool $waitlistEnabled = true;

    /** How many days before the edit lock the lock notice goes out. */
    #[ORM\Column(type: 'smallint')]
    private int $lockNoticeDays;

    /** Retention: when the answers and notes of all registrations were deleted (spec ch. 14). */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $answersForgottenAt = null;

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

    #[ORM\Column(length: 20, enumType: PublicRegistration::class)]
    private PublicRegistration $publicRegistration = PublicRegistration::None;

    #[ORM\Column(nullable: true)]
    private ?int $skautisEventId = null;

    #[ORM\Column(nullable: true)]
    private ?int $skautisCampId = null;

    /** When h.skauting last received this event (push, spec 9.4); later changes are pushed again. Not part of updatedAt. */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $hskautingPushedAt = null;

    /** Shared secret of the mini-web link; stored in the clear because leaders need to show the link. */
    #[ORM\Column(length: 64)]
    private string $webToken;

    /** The unit's Gmail account the event's e-mails leave from. */
    #[ORM\ManyToOne(targetEntity: GoogleOAuth::class)]
    #[ORM\JoinColumn(name: 'oauth_id', nullable: true, onDelete: 'SET NULL')]
    private ?GoogleOAuth $mailAccount = null;

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

    /** @var Collection<int, EventEmail> */
    #[ORM\OneToMany(targetEntity: EventEmail::class, mappedBy: 'event', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $emails;

    public function __construct(int $unitId, string $slug, EventInput $input, string $webToken, int $createdByUserId, DateTimeImmutable $now)
    {
        $this->unitId = $unitId;
        $this->slug = $slug;
        $this->webToken = $webToken;
        $this->createdByUserId = $createdByUserId;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->fields = new ArrayCollection();
        $this->emails = new ArrayCollection();
        $this->apply($input, $now);
    }

    /**
     * @param list<EmailTemplate> $templates
     */
    public function seedEmails(array $templates): void
    {
        foreach ($templates as $template) {
            if ($this->getEmail($template->type) === null) {
                $this->emails->add(new EventEmail($this, $template));
            }
        }
    }

    public function getEmail(EmailType $type): ?EventEmail
    {
        foreach ($this->emails as $email) {
            if ($email->getType() === $type) {
                return $email;
            }
        }

        return null;
    }

    /**
     * @return list<EventEmail>
     */
    public function getEmails(): array
    {
        $emails = $this->emails->getValues();
        $order = array_flip(array_map(static fn (EmailType $type): string => $type->value, EmailType::eventTemplates()));
        usort($emails, static fn (EventEmail $a, EventEmail $b): int => ($order[$a->getType()->value] ?? 99) <=> ($order[$b->getType()->value] ?? 99));

        return $emails;
    }

    public function isEmailEnabled(EmailType $type): bool
    {
        return $this->getEmail($type->templateType())?->isEnabled() ?? false;
    }

    public function setMailAccount(?GoogleOAuth $account, DateTimeImmutable $now): void
    {
        $this->mailAccount = $account;
        $this->updatedAt = $now;
    }

    public function getMailAccount(): ?GoogleOAuth
    {
        return $this->mailAccount;
    }

    public function getLockNoticeDays(): int
    {
        return $this->lockNoticeDays;
    }

    /**
     * The day the lock notice should go out; null when the event does not send it.
     */
    public function getLockNoticeDate(): ?DateTimeImmutable
    {
        if ($this->registrationDeadline === null || ! $this->isEmailEnabled(EmailType::LockNotice) || $this->lockNoticeDays <= 0) {
            return null;
        }

        return $this->registrationDeadline->modify(\sprintf('-%d days', $this->lockNoticeDays));
    }

    public function update(EventInput $input, DateTimeImmutable $now): void
    {
        $this->apply($input, $now);
    }

    public function markAnswersForgotten(DateTimeImmutable $now): void
    {
        $this->answersForgottenAt = $now;
        $this->updatedAt = $now;
    }

    public function getAnswersForgottenAt(): ?DateTimeImmutable
    {
        return $this->answersForgottenAt;
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
    /**
     * The last day a participant may sign up, change or cancel: the deadline, or the last day of the event.
     */
    public function getLastChangeDay(): DateTimeImmutable
    {
        return $this->registrationDeadline ?? $this->dateTo;
    }

    /**
     * One limit for signing up, changing and cancelling (owner's decision 36); closing the event ends it early.
     */
    public function isRegistrationOpen(DateTimeImmutable $now): bool
    {
        return $this->state === EventState::Open && $now->setTime(0, 0) <= $this->getLastChangeDay();
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

    public function markPushedToHskauting(DateTimeImmutable $now): void
    {
        $this->hskautingPushedAt = $now;
    }

    public function getHskautingPushedAt(): ?DateTimeImmutable
    {
        return $this->hskautingPushedAt;
    }

    public function linkSkautisEvent(int $skautisEventId, DateTimeImmutable $now): void
    {
        $this->skautisEventId = $skautisEventId;
        $this->updatedAt = $now;
    }

    /**
     * Camps exist in skautIS only; name, dates and place are mirrored from there and never edited here.
     */
    public function linkSkautisCamp(Camp $camp, DateTimeImmutable $now): void
    {
        $this->skautisCampId = $camp->id;
        $this->updateFromCamp($camp, $now);
    }

    public function updateFromCamp(Camp $camp, DateTimeImmutable $now): void
    {
        $this->name = $camp->name;
        $this->dateFrom = $camp->dateFrom;
        $this->dateTo = $camp->dateTo;
        $this->place = $camp->location === '' ? null : $camp->location;
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

    public function getSkautisTypeId(): ?string
    {
        return $this->skautisTypeId;
    }

    public function getSkautisScopeId(): ?string
    {
        return $this->skautisScopeId;
    }

    public function isCamp(): bool
    {
        return $this->skautisCampId !== null;
    }

    public function getPublicRegistration(): PublicRegistration
    {
        return $this->publicRegistration;
    }

    /**
     * Whether someone with the public link may register right now (state, deadline and mode).
     */
    public function isPublicRegistrationOpen(DateTimeImmutable $now): bool
    {
        return $this->publicRegistration === PublicRegistration::Open && $this->isRegistrationOpen($now);
    }

    /**
     * Whether someone may sign up after logging into skautIS (4.6): in both public modes, as long as registration runs.
     */
    public function acceptsSkautisLogin(DateTimeImmutable $now): bool
    {
        return $this->publicRegistration->isEnabled() && $this->isRegistrationOpen($now);
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

    public function isWaitlistEnabled(): bool
    {
        return $this->waitlistEnabled;
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
        $this->skautisTypeId = $input->skautisTypeId;
        $this->skautisScopeId = $input->skautisScopeId;
        if (! $this->isCamp()) {
            $this->name = $input->name;
            $this->dateFrom = $input->dateFrom;
            $this->dateTo = $input->dateTo;
            $this->place = $input->place;
        }
        $this->publicRegistration = $input->publicRegistration;
        $this->description = $input->description;
        $this->registrationDeadline = $input->registrationDeadline;
        $this->waitlistEnabled = $input->waitlistEnabled;
        $this->lockNoticeDays = $input->lockNoticeDays;
        $this->capacity = $input->capacity;
        $this->price = $input->price;
        $this->contactName = $input->contactName;
        $this->contactEmail = $input->contactEmail;
        $this->contactPhone = $input->contactPhone;
        $this->updatedAt = $now;
    }
}
