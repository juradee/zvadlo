<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Event\Event;
use App\Model\Event\EventField;
use App\Model\Event\EventFieldOption;
use App\Model\Event\EventFieldType;
use App\Model\Skautis\Dto\Person;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * One person for one event, from the invitation (or public sign-up) through the answer to the archive.
 */
#[ORM\Entity]
#[ORM\Table(name: 'registration')]
#[ORM\UniqueConstraint(name: 'uniq_registration_token', columns: ['token_hash'])]
#[ORM\UniqueConstraint(name: 'uniq_registration_event_person', columns: ['event_id', 'skautis_person_id'])]
#[ORM\Index(name: 'idx_registration_event_state', columns: ['event_id', 'state'])]
#[ORM\Index(name: 'idx_registration_event_email_hash', columns: ['event_id', 'email_hash'])]
class Registration
{
    public const int MAX_VERIFICATION_ATTEMPTS = 5;
    public const string BLOCK_DURATION = '+15 minutes';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\Column(length: 20, enumType: RegistrationOrigin::class)]
    private RegistrationOrigin $origin;

    #[ORM\Column(nullable: true)]
    private ?int $skautisPersonId = null;

    #[ORM\Column(length: 20, nullable: true, enumType: PairingHint::class)]
    private ?PairingHint $pairingHint = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nickName = null;

    #[ORM\Column(length: 10, nullable: true, enumType: Sex::class)]
    private ?Sex $sex = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $birthDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postcode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactEmail = null;

    /** Keyed fingerprint of the contact e-mail; survives anonymisation so a rejected person cannot come back. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailHash = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 20, enumType: RegistrationState::class)]
    private RegistrationState $state = RegistrationState::Invited;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $declineReason = null;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    /** The token itself, encrypted with APP_SECRET, so the same link can be shown and sent again. */
    #[ORM\Column(length: 255)]
    private string $tokenEncrypted;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $invitedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $seenAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $respondedAt = null;

    /** When the person got on the waiting list; the order of the list, unchanged by later edits. */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $waitlistedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $rejectedAt = null;

    #[ORM\Column(type: 'smallint')]
    private int $failedVerifications = 0;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $blockedUntil = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $editCodeHash = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $editCodeExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $skautisParticipantId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $skautisError = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $anonymizedAt = null;

    /** Set when Zvadlo created the skautIS person of a non-member; such data live in skautIS and go sooner (spec ch. 14). */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $skautisPersonCreatedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, RegistrationRecipient> */
    #[ORM\OneToMany(targetEntity: RegistrationRecipient::class, mappedBy: 'registration', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $recipients;

    /** @var Collection<int, RegistrationTextValue> */
    #[ORM\OneToMany(targetEntity: RegistrationTextValue::class, mappedBy: 'registration', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $textValues;

    /** @var Collection<int, RegistrationBooleanValue> */
    #[ORM\OneToMany(targetEntity: RegistrationBooleanValue::class, mappedBy: 'registration', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $booleanValues;

    /** @var Collection<int, RegistrationOptionValue> */
    #[ORM\OneToMany(targetEntity: RegistrationOptionValue::class, mappedBy: 'registration', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $optionValues;

    /** @var Collection<int, RegistrationLog> */
    #[ORM\OneToMany(targetEntity: RegistrationLog::class, mappedBy: 'registration', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['at' => 'ASC', 'id' => 'ASC'])]
    private Collection $logs;

    private function __construct(Event $event, RegistrationOrigin $origin, RegistrationToken $token, DateTimeImmutable $now)
    {
        $this->event = $event;
        $this->origin = $origin;
        $this->tokenHash = $token->hash;
        $this->tokenEncrypted = $token->encrypted;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->recipients = new ArrayCollection();
        $this->textValues = new ArrayCollection();
        $this->booleanValues = new ArrayCollection();
        $this->optionValues = new ArrayCollection();
        $this->logs = new ArrayCollection();
    }

    public static function inviteMember(Event $event, Person $person, RegistrationToken $token, int $invitedByUserId, DateTimeImmutable $now): self
    {
        $registration = new self($event, RegistrationOrigin::Invited, $token, $now);
        $registration->applyPerson($person);
        $registration->log(LogActor::User, $invitedByUserId, 'Přidán ze skautISu.', null, $now);

        return $registration;
    }

    /**
     * A non-member is invited by e-mail; the name is optional and the person fills the rest in the form.
     */
    public static function inviteNonMember(Event $event, string $email, ?string $firstName, ?string $lastName, RegistrationToken $token, int $invitedByUserId, DateTimeImmutable $now): self
    {
        $registration = new self($event, RegistrationOrigin::Invited, $token, $now);
        $registration->firstName = self::nullable($firstName);
        $registration->lastName = self::nullable($lastName);
        $registration->addRecipient($email, null, RecipientSource::Manual);
        $registration->log(LogActor::User, $invitedByUserId, 'Přidán jako nečlen (e-mail '.mb_strtolower(trim($email)).').', null, $now);

        return $registration;
    }

    /**
     * Someone signed up through the public link; when the fingerprints matched a member, the registration
     * is paired right away.
     */
    public static function registerPublic(Event $event, RegistrationAnswer $answer, RegistrationToken $token, ?Person $matchedMember, ?PairingHint $hint, bool $capacityAvailable, DateTimeImmutable $now): self
    {
        $registration = new self($event, RegistrationOrigin::Public, $token, $now);
        if ($matchedMember !== null) {
            $registration->applyPerson($matchedMember);
            $registration->log(LogActor::System, null, 'Spárováno se členem podle jména a data narození.', null, $now);
        } else {
            $registration->pairingHint = $hint;
        }
        $registration->log(LogActor::Participant, null, 'Přihlášen veřejným odkazem.', null, $now);
        $registration->respond($answer, $capacityAvailable, $now);

        return $registration;
    }

    /**
     * Someone logged into skautIS and signed up themselves or their child: the identity is proven, the
     * registration is paired from the start (4.6).
     */
    public static function registerViaSkautis(Event $event, RegistrationAnswer $answer, RegistrationToken $token, Person $person, bool $capacityAvailable, DateTimeImmutable $now): self
    {
        $registration = new self($event, RegistrationOrigin::Skautis, $token, $now);
        $registration->applyPerson($person);
        $registration->log(LogActor::Participant, null, 'Přihlášen po přihlášení přes skautIS; osobu ověřil skautIS.', null, $now);
        $registration->respond($answer, $capacityAvailable, $now);

        return $registration;
    }

    public function isMember(): bool
    {
        return $this->skautisPersonId !== null;
    }

    public function getVerificationMethod(): VerificationMethod
    {
        // A person Zvadlo created in skautIS is still a non-member: the birth date is theirs to type, not a secret.
        if ($this->isMember() && $this->skautisPersonCreatedAt === null) {
            return $this->birthDate === null ? VerificationMethod::None : VerificationMethod::BirthDate;
        }

        return $this->respondedAt === null ? VerificationMethod::Email : VerificationMethod::Code;
    }

    public function isBlocked(DateTimeImmutable $now): bool
    {
        return $this->blockedUntil !== null && $this->blockedUntil > $now;
    }

    /**
     * Signing up, changing and cancelling share one limit: the open event until its deadline or end.
     */
    public function canBeChangedByParticipant(DateTimeImmutable $now): bool
    {
        return $this->state !== RegistrationState::Rejected && $this->event->isRegistrationOpen($now);
    }

    public function canBeCancelledByParticipant(DateTimeImmutable $now): bool
    {
        return $this->state->isAttending() && $this->canBeChangedByParticipant($now);
    }

    public function verifyBirthDate(DateTimeImmutable $birthDate, DateTimeImmutable $now): bool
    {
        return $this->verify($this->birthDate !== null && $this->birthDate->format('Y-m-d') === $birthDate->format('Y-m-d'), $now);
    }

    /**
     * A non-member unlocks the first fill-in with the address the invitation went to.
     */
    public function verifyEmail(string $email, DateTimeImmutable $now): bool
    {
        $email = mb_strtolower(trim($email));

        return $this->verify($email !== '' && \in_array($email, $this->getRecipientEmails(), true), $now);
    }

    public function issueEditCode(string $codeHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
    {
        $this->editCodeHash = $codeHash;
        $this->editCodeExpiresAt = $expiresAt;
        $this->updatedAt = $now;
    }

    public function hasValidEditCode(DateTimeImmutable $now): bool
    {
        return $this->editCodeHash !== null && $this->editCodeExpiresAt !== null && $this->editCodeExpiresAt > $now;
    }

    public function verifyEditCode(string $codeHash, DateTimeImmutable $now): bool
    {
        $correct = $this->hasValidEditCode($now) && hash_equals((string) $this->editCodeHash, $codeHash);
        if ($correct) {
            $this->editCodeHash = null;
            $this->editCodeExpiresAt = null;
        }

        return $this->verify($correct, $now);
    }

    public function markSeen(DateTimeImmutable $now): void
    {
        if ($this->state === RegistrationState::Invited) {
            $this->state = RegistrationState::Seen;
            $this->seenAt = $now;
            $this->updatedAt = $now;
            $this->log(LogActor::Participant, null, 'Odkaz otevřen.', null, $now);
        }
    }

    public function markInvited(DateTimeImmutable $now): void
    {
        $this->invitedAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * The participant's answer. A new attendee goes to the waiting list when the capacity is full;
     * someone already registered keeps the place while editing.
     */
    public function respond(RegistrationAnswer $answer, bool $capacityAvailable, DateTimeImmutable $now): void
    {
        $previous = $this->state;

        if (! $answer->attending) {
            $this->state = RegistrationState::Declined;
            $this->declineReason = $answer->declineReason;
        } else {
            $this->declineReason = null;
            // Someone already registered or waitlisted keeps the place; a new attendee needs free capacity.
            $this->state = match (true) {
                $previous === RegistrationState::Registered || $previous === RegistrationState::Waitlisted => $previous,
                $capacityAvailable => RegistrationState::Registered,
                default => RegistrationState::Waitlisted,
            };
            if ($this->state === RegistrationState::Waitlisted && $previous !== RegistrationState::Waitlisted) {
                $this->waitlistedAt = $now;
            }
        }

        $this->contactEmail = $answer->contactEmail;
        $this->phone = $answer->phone;
        $this->note = $answer->note;

        if (! $this->isMember()) {
            $this->firstName = self::nullable($answer->firstName) ?? $this->firstName;
            $this->lastName = self::nullable($answer->lastName) ?? $this->lastName;
            $this->nickName = self::nullable($answer->nickName);
            $this->sex = $answer->sex;
            $this->birthDate = $answer->birthDate;
            $this->street = $answer->street;
            $this->city = $answer->city;
            $this->postcode = $answer->postcode;
        }

        $this->respondedAt = $now;
        $this->cancelledAt = null;
        $this->updatedAt = $now;
        $this->log(LogActor::Participant, null, match (true) {
            $this->state === RegistrationState::Declined => 'Účast odmítnuta.',
            $this->state === $previous => 'Přihláška upravena.',
            $this->state === RegistrationState::Waitlisted => 'Přihlášen na čekací listinu (kapacita je plná).',
            default => 'Přihlášen.',
        }, null, $now);
    }

    public function recordEmailHash(?string $emailHash): void
    {
        $this->emailHash = $emailHash;
    }

    public function cancel(LogActor $actor, ?int $userId, DateTimeImmutable $now): void
    {
        $this->state = RegistrationState::Cancelled;
        $this->cancelledAt = $now;
        $this->updatedAt = $now;
        $this->log($actor, $userId, $actor === LogActor::Participant ? 'Účast zrušena účastníkem.' : 'Účast zrušena vedoucím.', null, $now);
    }

    /**
     * From the waiting list to the participants: by the leader (possibly over capacity) or by the system
     * when an attendee cancelled themselves.
     */
    public function promote(LogActor $actor, ?int $userId, bool $overCapacity, DateTimeImmutable $now): void
    {
        if ($this->state !== RegistrationState::Waitlisted) {
            return;
        }

        $this->state = RegistrationState::Registered;
        $this->updatedAt = $now;
        $this->log($actor, $userId, match (true) {
            $overCapacity => 'Přijat z čekací listiny nad kapacitu akce (schválil vedoucí).',
            $actor === LogActor::System => 'Přesunut z čekací listiny na uvolněné místo.',
            default => 'Přesunut z čekací listiny mezi přihlášené.',
        }, null, $now);
    }

    public function markDeclinedByLeader(int $userId, ?string $reason, DateTimeImmutable $now): void
    {
        $this->state = RegistrationState::Declined;
        $this->declineReason = $reason;
        $this->respondedAt = $now;
        $this->updatedAt = $now;
        $this->log(LogActor::User, $userId, 'Odmítnutí zaznamenáno vedoucím.', null, $now);
    }

    /**
     * The leader turns away someone who does not belong to the event: personal data go immediately,
     * the record stays so the same person cannot sign up through the public link again.
     */
    public function reject(int $userId, ?string $reason, ?string $emailHash, DateTimeImmutable $now): void
    {
        $this->state = RegistrationState::Rejected;
        $this->rejectedAt = $now;
        $this->emailHash = $emailHash ?? $this->emailHash;
        $this->log(LogActor::User, $userId, $reason === null || $reason === '' ? 'Přihláška zamítnuta vedoucím.' : 'Přihláška zamítnuta vedoucím: '.$reason, null, $now);
        $this->anonymize($now);
    }

    public function anonymize(DateTimeImmutable $now): void
    {
        $this->firstName = null;
        $this->lastName = null;
        $this->nickName = null;
        $this->sex = null;
        $this->birthDate = null;
        $this->street = null;
        $this->city = null;
        $this->postcode = null;
        $this->contactEmail = null;
        $this->phone = null;
        $this->note = null;
        $this->declineReason = null;
        $this->pairingHint = null;
        $this->recipients->clear();
        $this->textValues->clear();
        $this->booleanValues->clear();
        $this->optionValues->clear();
        $this->anonymizedAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * Retention: the answers and the note go after the shorter period, the identity later or never.
     */
    public function forgetAnswers(DateTimeImmutable $now): void
    {
        $this->note = null;
        $this->textValues->clear();
        $this->booleanValues->clear();
        $this->optionValues->clear();
        $this->updatedAt = $now;
    }

    public function wasPersonCreatedInSkautis(): bool
    {
        return $this->skautisPersonCreatedAt !== null;
    }

    /**
     * The leader confirms which skautIS person a public registration belongs to.
     */
    public function pairWithPerson(Person $person, int $userId, DateTimeImmutable $now): void
    {
        $this->applyPerson($person);
        $this->updatedAt = $now;
        $this->log(LogActor::User, $userId, 'Spárováno vedoucím s osobou '.$person->getDisplayName().' ('.$person->id.').', null, $now);
    }

    /**
     * The participation exists in skautIS now; a non-member got a person there too.
     */
    public function recordSkautisParticipant(int $participantId, ?int $createdPersonId, LogActor $actor, ?int $userId, DateTimeImmutable $now): void
    {
        $this->skautisParticipantId = $participantId;
        $this->skautisError = null;
        if ($createdPersonId !== null && $this->skautisPersonId === null) {
            $this->skautisPersonId = $createdPersonId;
            $this->skautisPersonCreatedAt = $now;
            $this->log($actor, $userId, 'Založena osoba ve skautISu (ID '.$createdPersonId.') a propsána účast.', null, $now);
        } else {
            $this->log($actor, $userId, 'Účast propsána do skautISu.', null, $now);
        }
        $this->updatedAt = $now;
    }

    public function recordSkautisError(string $error, DateTimeImmutable $now): void
    {
        $this->skautisError = mb_substr($error, 0, 500);
        $this->updatedAt = $now;
    }

    public function clearSkautisParticipant(LogActor $actor, ?int $userId, DateTimeImmutable $now): void
    {
        $this->skautisParticipantId = null;
        $this->skautisError = null;
        $this->log($actor, $userId, 'Účast odebrána ze skautISu.', null, $now);
        $this->updatedAt = $now;
    }

    public function toNewPerson(): \App\Model\Skautis\Dto\NewPerson
    {
        return new \App\Model\Skautis\Dto\NewPerson(
            (string) $this->firstName,
            (string) $this->lastName,
            $this->nickName,
            $this->sex?->value,
            $this->birthDate,
            $this->street,
            $this->city,
            $this->postcode,
        );
    }

    public function addRecipient(string $email, ?string $name, RecipientSource $source, bool $selected = true): bool
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || $this->findRecipient($email) !== null) {
            return false;
        }

        $this->recipients->add(new RegistrationRecipient($this, $email, self::nullable($name), $source, $selected));

        return true;
    }

    public function removeRecipient(RegistrationRecipient $recipient): void
    {
        $this->recipients->removeElement($recipient);
    }

    public function findRecipient(string $email): ?RegistrationRecipient
    {
        $email = mb_strtolower(trim($email));
        foreach ($this->recipients as $recipient) {
            if ($recipient->getEmail() === $email) {
                return $recipient;
            }
        }

        return null;
    }

    /**
     * Only when the link leaked; the old link stops working and every address needs the new one.
     */
    public function replaceToken(RegistrationToken $token, int $userId, DateTimeImmutable $now): void
    {
        $this->tokenHash = $token->hash;
        $this->tokenEncrypted = $token->encrypted;
        $this->updatedAt = $now;
        $this->log(LogActor::User, $userId, 'Vygenerován nový osobní odkaz, starý přestal platit.', null, $now);
    }

    public function setTextValue(EventField $field, ?string $value): void
    {
        foreach ($this->textValues as $existing) {
            if ($existing->getField() === $field) {
                if ($value === null || $value === '') {
                    $this->textValues->removeElement($existing);
                } else {
                    $existing->setValue($value);
                }

                return;
            }
        }

        if ($value !== null && $value !== '') {
            $this->textValues->add(new RegistrationTextValue($this, $field, $value));
        }
    }

    public function setBooleanValue(EventField $field, bool $value): void
    {
        foreach ($this->booleanValues as $existing) {
            if ($existing->getField() === $field) {
                $existing->setValue($value);

                return;
            }
        }

        $this->booleanValues->add(new RegistrationBooleanValue($this, $field, $value));
    }

    /**
     * @param list<EventFieldOption> $options
     */
    public function setOptionValues(EventField $field, array $options): void
    {
        // Rows that stay selected are kept: removing and re-adding the same composite key in one flush collides.
        foreach ($this->optionValues as $existing) {
            if ($existing->getOption()->getField() === $field && ! \in_array($existing->getOption(), $options, true)) {
                $this->optionValues->removeElement($existing);
            }
        }

        $selected = $this->getSelectedOptions($field);
        foreach ($options as $option) {
            if (! \in_array($option, $selected, true)) {
                $this->optionValues->add(new RegistrationOptionValue($this, $option));
            }
        }
    }

    public function getTextValue(EventField $field): ?string
    {
        foreach ($this->textValues as $value) {
            if ($value->getField() === $field) {
                return $value->getValue();
            }
        }

        return null;
    }

    public function getBooleanValue(EventField $field): ?bool
    {
        foreach ($this->booleanValues as $value) {
            if ($value->getField() === $field) {
                return $value->getValue();
            }
        }

        return null;
    }

    /**
     * @return list<EventFieldOption>
     */
    public function getSelectedOptions(EventField $field): array
    {
        $options = [];
        foreach ($this->optionValues as $value) {
            if ($value->getOption()->getField() === $field) {
                $options[] = $value->getOption();
            }
        }
        usort($options, static fn (EventFieldOption $a, EventFieldOption $b): int => $a->getPosition() <=> $b->getPosition());

        return $options;
    }

    /**
     * Human-readable value of a custom field for lists and exports.
     */
    public function getFieldValueText(EventField $field): string
    {
        return match ($field->getType()) {
            EventFieldType::Text => $this->getTextValue($field) ?? '',
            EventFieldType::Boolean => match ($this->getBooleanValue($field)) {
                true => 'ano',
                false => 'ne',
                null => '',
            },
            EventFieldType::Choice, EventFieldType::MultiChoice => implode(', ', array_map(
                static fn (EventFieldOption $option): string => $option->getLabel(),
                $this->getSelectedOptions($field),
            )),
        };
    }

    /**
     * @param array<string, mixed>|null $changes
     */
    public function log(LogActor $actor, ?int $userId, string $message, ?array $changes, DateTimeImmutable $now): void
    {
        $this->logs->add(new RegistrationLog($this, $actor, $userId, $message, $changes, $now));
    }

    public function getId(): int
    {
        if ($this->id === null) {
            throw new LogicException('The registration has not been persisted yet.');
        }

        return $this->id;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getOrigin(): RegistrationOrigin
    {
        return $this->origin;
    }

    public function getSkautisPersonId(): ?int
    {
        return $this->skautisPersonId;
    }

    public function getPairingHint(): ?PairingHint
    {
        return $this->pairingHint;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getNickName(): ?string
    {
        return $this->nickName;
    }

    public function hasName(): bool
    {
        return $this->firstName !== null || $this->lastName !== null;
    }

    /**
     * "Příjmení Jméno (přezdívka)"; a non-member without a name yet is shown by the invitation e-mail.
     */
    public function getDisplayName(): string
    {
        if (! $this->hasName()) {
            if ($this->state === RegistrationState::Rejected) {
                return 'zamítnutá přihláška';
            }
            if ($this->anonymizedAt !== null) {
                return 'anonymizovaná přihláška';
            }

            return $this->getRecipientEmails()[0] ?? $this->contactEmail ?? 'neznámý účastník';
        }

        $name = trim(($this->lastName ?? '').' '.($this->firstName ?? ''));

        return $this->nickName === null ? $name : $name.' ('.$this->nickName.')';
    }

    public function getSex(): ?Sex
    {
        return $this->sex;
    }

    public function getBirthDate(): ?DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getPostcode(): ?string
    {
        return $this->postcode;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function getEmailHash(): ?string
    {
        return $this->emailHash;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhoneIfEmpty(?string $phone): void
    {
        if ($this->phone === null && $phone !== null && $phone !== '') {
            $this->phone = $phone;
        }
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getState(): RegistrationState
    {
        return $this->state;
    }

    public function getDeclineReason(): ?string
    {
        return $this->declineReason;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getTokenEncrypted(): string
    {
        return $this->tokenEncrypted;
    }

    public function getInvitedAt(): ?DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function getSeenAt(): ?DateTimeImmutable
    {
        return $this->seenAt;
    }

    public function getRespondedAt(): ?DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getRejectedAt(): ?DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function getBlockedUntil(): ?DateTimeImmutable
    {
        return $this->blockedUntil;
    }

    public function getSkautisParticipantId(): ?int
    {
        return $this->skautisParticipantId;
    }

    public function getSkautisError(): ?string
    {
        return $this->skautisError;
    }

    public function getAnonymizedAt(): ?DateTimeImmutable
    {
        return $this->anonymizedAt;
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
     * @return list<RegistrationRecipient>
     */
    public function getRecipients(): array
    {
        return $this->recipients->getValues();
    }

    /**
     * @return list<RegistrationRecipient>
     */
    public function getSelectedRecipients(): array
    {
        return array_values(array_filter($this->getRecipients(), static fn (RegistrationRecipient $recipient): bool => $recipient->isSelected()));
    }

    /**
     * All addresses found for the invitation, selected or not.
     *
     * @return list<string>
     */
    public function getRecipientEmails(): array
    {
        return array_map(static fn (RegistrationRecipient $recipient): string => $recipient->getEmail(), $this->getRecipients());
    }

    /**
     * Addresses the invitation (and reminders) go to.
     *
     * @return list<string>
     */
    public function getSelectedRecipientEmails(): array
    {
        return array_map(static fn (RegistrationRecipient $recipient): string => $recipient->getEmail(), $this->getSelectedRecipients());
    }

    public function hasRecipients(): bool
    {
        return ! $this->recipients->isEmpty();
    }

    public function hasSelectedRecipients(): bool
    {
        return $this->getSelectedRecipients() !== [];
    }

    /**
     * Where mail other than the invitation goes. A member invited by the leader gets it on every address chosen
     * at the invitation (mother, father and their own alike); a non-member, or a member who signed up through
     * the public link, on the one contact e-mail they gave.
     *
     * @return list<string>
     */
    public function getMailAddresses(): array
    {
        if ($this->isMember() && $this->hasSelectedRecipients()) {
            return $this->getSelectedRecipientEmails();
        }
        if ($this->contactEmail !== null && $this->contactEmail !== '') {
            return [$this->contactEmail];
        }

        return $this->getSelectedRecipientEmails();
    }

    /**
     * Whether the participant gives a contact e-mail in the form; an invited member's mail goes to the
     * addresses the leader chose instead.
     */
    public function asksForContactEmail(): bool
    {
        return ! ($this->isMember() && $this->hasSelectedRecipients());
    }

    /**
     * @return list<RegistrationLog>
     */
    public function getLogs(): array
    {
        return $this->logs->getValues();
    }

    private function applyPerson(Person $person): void
    {
        $this->skautisPersonId = $person->id;
        $this->firstName = $person->firstName;
        $this->lastName = $person->lastName;
        $this->nickName = self::nullable($person->nickName);
        $this->sex = $person->sex === null ? null : Sex::tryFrom($person->sex);
        $this->birthDate = $person->birthday;
        $this->pairingHint = null;
    }

    private function verify(bool $correct, DateTimeImmutable $now): bool
    {
        if ($this->isBlocked($now)) {
            // Attempts during the block neither count nor unlock, so the block cannot be brute-forced away.
            return false;
        }
        if ($correct) {
            $this->failedVerifications = 0;
            $this->blockedUntil = null;

            return true;
        }

        ++$this->failedVerifications;
        if ($this->failedVerifications >= self::MAX_VERIFICATION_ATTEMPTS) {
            $this->failedVerifications = 0;
            $this->blockedUntil = $now->modify(self::BLOCK_DURATION);
        }
        $this->updatedAt = $now;

        return false;
    }

    private static function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
