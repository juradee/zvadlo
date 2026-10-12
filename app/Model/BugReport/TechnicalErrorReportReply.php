<?php

declare(strict_types=1);

namespace App\Model\BugReport;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * An answer an administrator sent to the reporter; mirrored to the GitHub issue when there is one.
 */
#[ORM\Entity]
#[ORM\Table(name: 'technical_error_report_reply')]
class TechnicalErrorReportReply
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TechnicalErrorReport::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TechnicalErrorReport $report;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column]
    private DateTimeImmutable $sentAt;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $githubCommentUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $githubCommentError = null;

    public function __construct(TechnicalErrorReport $report, string $message, DateTimeImmutable $sentAt)
    {
        $this->report = $report;
        $this->message = $message;
        $this->sentAt = $sentAt;
    }

    public function markGithubCommentCreated(string $url): void
    {
        $this->githubCommentUrl = $url;
        $this->githubCommentError = null;
    }

    public function markGithubCommentFailed(string $error): void
    {
        $this->githubCommentError = $error;
    }

    public function getId(): int
    {
        return $this->id ?? throw new LogicException('The reply has not been persisted yet.');
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getSentAt(): DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getGithubCommentUrl(): ?string
    {
        return $this->githubCommentUrl;
    }

    public function getGithubCommentError(): ?string
    {
        return $this->githubCommentError;
    }
}
