<?php

declare(strict_types=1);

namespace App\Model\Task;

use App\Model\Event\Event;
use App\Model\Registration\Registration;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * One queued job with its progress; the worker processes it in batches.
 */
#[ORM\Entity]
#[ORM\Table(name: 'task')]
#[ORM\Index(name: 'idx_task_state', columns: ['state', 'id'])]
class Task
{
    public const int MAX_ATTEMPTS = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, enumType: TaskType::class)]
    private TaskType $type;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Event $event;

    #[ORM\Column(length: 20, enumType: TaskState::class)]
    private TaskState $state = TaskState::Queued;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column(type: 'smallint')]
    private int $attempts = 0;

    #[ORM\Column]
    private int $progressDone = 0;

    #[ORM\Column]
    private int $progressTotal;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(nullable: true)]
    private ?int $createdByUserId;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $finishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $heartbeatAt = null;

    /** @var Collection<int, TaskRegistration> */
    #[ORM\OneToMany(targetEntity: TaskRegistration::class, mappedBy: 'task', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $registrations;

    /**
     * @param array<string, mixed> $payload
     * @param list<Registration>   $registrations
     */
    public function __construct(TaskType $type, ?Event $event, array $payload, array $registrations, ?int $createdByUserId, DateTimeImmutable $now)
    {
        $this->type = $type;
        $this->event = $event;
        $this->payload = $payload;
        $this->createdByUserId = $createdByUserId;
        $this->createdAt = $now;
        $this->registrations = new ArrayCollection();
        foreach ($registrations as $registration) {
            $this->registrations->add(new TaskRegistration($this, $registration));
        }
        $this->progressTotal = \count($registrations);
    }

    /**
     * The worker is still alive: set on claim and after each batch, a stale value means it died.
     */
    public function touch(DateTimeImmutable $now): void
    {
        $this->heartbeatAt = $now;
    }

    public function getHeartbeatAt(): ?DateTimeImmutable
    {
        return $this->heartbeatAt;
    }

    public function start(DateTimeImmutable $now): void
    {
        $this->state = TaskState::Running;
        $this->startedAt ??= $now;
        $this->heartbeatAt = $now;
        ++$this->attempts;
    }

    public function advance(int $done): void
    {
        $this->progressDone = min($this->progressTotal, $done);
    }

    public function finish(DateTimeImmutable $now): void
    {
        $this->state = TaskState::Done;
        $this->progressDone = $this->progressTotal;
        $this->finishedAt = $now;
        $this->error = null;
    }

    /**
     * A batch failed: back to the queue, or failed for good after MAX_ATTEMPTS.
     */
    public function failAttempt(string $error, DateTimeImmutable $now): void
    {
        $this->error = mb_substr($error, 0, 1000);
        if ($this->attempts >= self::MAX_ATTEMPTS) {
            $this->state = TaskState::Failed;
            $this->finishedAt = $now;
        } else {
            $this->state = TaskState::Queued;
        }
    }

    /**
     * A leader retries a failed task from the beginning of the attempt count.
     */
    public function requeue(): void
    {
        $this->state = TaskState::Queued;
        $this->attempts = 0;
        $this->error = null;
        $this->finishedAt = null;
    }

    /**
     * The worker ran out of time; the next run picks the task up again.
     */
    public function requeueKeepingAttempts(): void
    {
        $this->state = TaskState::Queued;
        --$this->attempts;
    }

    public function getId(): int
    {
        return $this->id ?? throw new LogicException('The task has not been persisted yet.');
    }

    public function getType(): TaskType
    {
        return $this->type;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function getState(): TaskState
    {
        return $this->state;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getPayloadString(string $key): string
    {
        $value = $this->payload[$key] ?? '';

        return \is_scalar($value) ? (string) $value : '';
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getProgressDone(): int
    {
        return $this->progressDone;
    }

    public function getProgressTotal(): int
    {
        return $this->progressTotal;
    }

    public function getProgressLabel(): string
    {
        return $this->state->label().', '.$this->progressDone.' z '.$this->progressTotal;
    }

    public function getProgressPercent(): int
    {
        return $this->progressTotal === 0 ? 100 : (int) floor($this->progressDone * 100 / $this->progressTotal);
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getCreatedByUserId(): ?int
    {
        return $this->createdByUserId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /**
     * @return list<Registration>
     */
    public function getRegistrations(): array
    {
        return array_map(static fn (TaskRegistration $link): Registration => $link->getRegistration(), $this->registrations->getValues());
    }
}
