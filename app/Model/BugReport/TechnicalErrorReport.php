<?php

declare(strict_types=1);

namespace App\Model\BugReport;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * A problem a leader reported from the footer link, with everything needed to find it in the logs.
 * Fields and behaviour follow h.skauting.
 */
#[ORM\Entity]
#[ORM\Table(name: 'technical_error_report')]
#[ORM\Index(name: 'idx_error_report_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_error_report_resolved', columns: ['resolved_at'])]
class TechnicalErrorReport
{
    public const string RESOLUTION_FIXED = 'fixed';
    public const string RESOLUTION_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $reportedUrl;

    #[ORM\Column]
    private int $reporterUserId;

    #[ORM\Column(length: 255)]
    private string $reporterDisplayName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reporterEmail;

    #[ORM\Column(nullable: true)]
    private ?int $roleId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $roleName;

    #[ORM\Column(nullable: true)]
    private ?int $unitId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $unitName;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $screenshotPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $screenshotOriginalName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $screenshotContentType = null;

    #[ORM\Column(nullable: true)]
    private ?int $screenshotSize = null;

    #[ORM\Column(length: 255)]
    private string $appRelease;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $diagnostics;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $notificationSentAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notificationError = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $resolutionState = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resolutionMessage = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $resolutionNotificationSentAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resolutionNotificationError = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $replyError = null;

    #[ORM\Column(nullable: true)]
    private ?int $githubIssueNumber = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $githubIssueUrl = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $githubIssueCreatedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $githubSyncError = null;

    /** @var Collection<int, TechnicalErrorReportReply> */
    #[ORM\OneToMany(targetEntity: TechnicalErrorReportReply::class, mappedBy: 'report', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sentAt' => 'DESC', 'id' => 'DESC'])]
    private Collection $replies;

    /**
     * @param array<string, mixed> $diagnostics
     */
    public function __construct(
        string $description,
        ?string $reportedUrl,
        int $reporterUserId,
        string $reporterDisplayName,
        ?string $reporterEmail,
        ?int $roleId,
        ?string $roleName,
        ?int $unitId,
        ?string $unitName,
        ?string $ipAddress,
        ?string $userAgent,
        string $appRelease,
        array $diagnostics,
        DateTimeImmutable $now,
    ) {
        $this->description = $description;
        $this->reportedUrl = $reportedUrl;
        $this->reporterUserId = $reporterUserId;
        $this->reporterDisplayName = $reporterDisplayName;
        $this->reporterEmail = $reporterEmail;
        $this->roleId = $roleId;
        $this->roleName = $roleName;
        $this->unitId = $unitId;
        $this->unitName = $unitName;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->appRelease = $appRelease;
        $this->diagnostics = $diagnostics;
        $this->createdAt = $now;
        $this->replies = new ArrayCollection();
    }

    public function attachScreenshot(string $path, string $originalName, string $contentType, int $size): void
    {
        $this->screenshotPath = $path;
        $this->screenshotOriginalName = $originalName;
        $this->screenshotContentType = $contentType;
        $this->screenshotSize = $size;
    }

    public function markNotificationSent(DateTimeImmutable $now): void
    {
        $this->notificationSentAt = $now;
        $this->notificationError = null;
    }

    public function markNotificationFailed(string $error): void
    {
        $this->notificationSentAt = null;
        $this->notificationError = $error;
    }

    public function markReplySent(string $message, DateTimeImmutable $now): TechnicalErrorReportReply
    {
        $reply = new TechnicalErrorReportReply($this, $message, $now);
        $this->replies->add($reply);
        $this->replyError = null;

        return $reply;
    }

    public function markReplyFailed(string $error): void
    {
        $this->replyError = $error;
    }

    public function resolve(string $state, string $message, DateTimeImmutable $now): void
    {
        $this->resolvedAt = $now;
        $this->resolutionState = $state;
        $this->resolutionMessage = $message;
    }

    public function markResolutionNotificationSent(DateTimeImmutable $now): void
    {
        $this->resolutionNotificationSentAt = $now;
        $this->resolutionNotificationError = null;
    }

    public function markResolutionNotificationFailed(string $error): void
    {
        $this->resolutionNotificationSentAt = null;
        $this->resolutionNotificationError = $error;
    }

    public function markGithubIssueCreated(int $number, string $url, DateTimeImmutable $now): void
    {
        $this->githubIssueNumber = $number;
        $this->githubIssueUrl = $url;
        $this->githubIssueCreatedAt = $now;
        $this->githubSyncError = null;
    }

    public function markGithubSyncFailed(string $error): void
    {
        $this->githubSyncError = $error;
    }

    public function getId(): int
    {
        return $this->id ?? throw new LogicException('The report has not been persisted yet.');
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getReportedUrl(): ?string
    {
        return $this->reportedUrl;
    }

    public function getReporterUserId(): int
    {
        return $this->reporterUserId;
    }

    public function getReporterDisplayName(): string
    {
        return $this->reporterDisplayName;
    }

    public function getReporterEmail(): ?string
    {
        return $this->reporterEmail;
    }

    public function getRoleId(): ?int
    {
        return $this->roleId;
    }

    public function getRoleName(): ?string
    {
        return $this->roleName;
    }

    public function getUnitId(): ?int
    {
        return $this->unitId;
    }

    public function getUnitName(): ?string
    {
        return $this->unitName;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function hasScreenshot(): bool
    {
        return $this->screenshotPath !== null;
    }

    public function getScreenshotPath(): ?string
    {
        return $this->screenshotPath;
    }

    public function getScreenshotOriginalName(): ?string
    {
        return $this->screenshotOriginalName;
    }

    public function getScreenshotContentType(): ?string
    {
        return $this->screenshotContentType;
    }

    public function getScreenshotSize(): ?int
    {
        return $this->screenshotSize;
    }

    public function getAppRelease(): string
    {
        return $this->appRelease;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function wasNotificationSent(): bool
    {
        return $this->notificationSentAt !== null;
    }

    public function getNotificationError(): ?string
    {
        return $this->notificationError;
    }

    public function isResolved(): bool
    {
        return $this->resolvedAt !== null;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function getResolutionState(): ?string
    {
        return $this->resolutionState;
    }

    public function getResolutionMessage(): ?string
    {
        return $this->resolutionMessage;
    }

    public function getResolutionNotificationError(): ?string
    {
        return $this->resolutionNotificationError;
    }

    public function getReplyError(): ?string
    {
        return $this->replyError;
    }

    public function hasGithubIssue(): bool
    {
        return $this->githubIssueNumber !== null;
    }

    public function getGithubIssueNumber(): ?int
    {
        return $this->githubIssueNumber;
    }

    public function getGithubIssueUrl(): ?string
    {
        return $this->githubIssueUrl;
    }

    public function getGithubSyncError(): ?string
    {
        return $this->githubSyncError;
    }

    /**
     * @return list<TechnicalErrorReportReply>
     */
    public function getReplies(): array
    {
        return $this->replies->getValues();
    }
}
