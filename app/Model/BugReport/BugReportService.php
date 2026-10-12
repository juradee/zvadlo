<?php

declare(strict_types=1);

namespace App\Model\BugReport;

use App\Model\Skautis\Dto\Unit;
use App\Model\User\CurrentUser;
use App\Model\User\CurrentUserEmail;
use DateTimeImmutable;
use Nette\Http\FileUpload;
use Nette\Http\IRequest;
use Throwable;

/**
 * Stores a bug report with the diagnostics h.skauting collects and notifies the administrators.
 */
final class BugReportService
{
    public function __construct(
        private readonly TechnicalErrorReportRepository $reports,
        private readonly BugReportMailer $mailer,
        private readonly ScreenshotStorage $screenshots,
        private readonly CurrentUser $currentUser,
        private readonly CurrentUserEmail $currentUserEmail,
        private readonly IRequest $request,
        private readonly string $appRelease,
    ) {
    }

    /**
     * @param array<string, mixed> $clientDiagnostics
     */
    public function submit(string $description, ?string $reportedUrl, array $clientDiagnostics, ?string $reporterEmail, ?FileUpload $screenshot): TechnicalErrorReport
    {
        $account = $this->currentUser->getUser();
        $role = $this->currentUser->getRole();
        $access = $this->currentUser->getAccess();
        $now = new DateTimeImmutable();

        $report = new TechnicalErrorReport(
            trim($description),
            $reportedUrl === null || trim($reportedUrl) === '' ? null : trim($reportedUrl),
            $account->id,
            $account->displayName,
            $this->currentUserEmail->main() ?? ($reporterEmail === null || trim($reporterEmail) === '' ? null : trim($reporterEmail)),
            $role->id,
            $role->displayName,
            $role->unitId,
            $role->unitName,
            $this->request->getRemoteAddress(),
            $this->request->getHeader('User-Agent'),
            $this->appRelease,
            [
                'user' => [
                    'id' => $account->id,
                    'personId' => $account->personId,
                    'userName' => $account->userName,
                    'activeRole' => ['id' => $role->id, 'key' => $role->key, 'name' => $role->displayName, 'unitId' => $role->unitId, 'unitName' => $role->unitName],
                    'allRoles' => array_map(static fn ($r): array => ['id' => $r->id, 'key' => $r->key, 'name' => $r->displayName, 'unitId' => $r->unitId], $this->currentUser->getRoles()),
                    'accessibleUnitIds' => [
                        'read' => array_map(static fn (Unit $unit): int => $unit->id, array_values($access->read)),
                        'edit' => array_map(static fn (Unit $unit): int => $unit->id, array_values($access->edit)),
                    ],
                ],
                'request' => [
                    'url' => (string) $this->request->getUrl(),
                    'method' => $this->request->getMethod(),
                    'remoteAddress' => $this->request->getRemoteAddress(),
                    'headers' => $this->safeHeaders(),
                ],
                'application' => ['release' => $this->appRelease, 'phpVersion' => \PHP_VERSION, 'phpSapi' => \PHP_SAPI],
                'browser' => $clientDiagnostics,
            ],
            $now,
        );

        if ($screenshot !== null && $screenshot->hasFile()) {
            $stored = $this->screenshots->store($screenshot);
            $report->attachScreenshot($stored['path'], $stored['originalName'], $stored['contentType'], $stored['size']);
        }
        $this->reports->add($report);

        try {
            $this->mailer->notifyAdmins($report);
            $report->markNotificationSent($now);
        } catch (Throwable $exception) {
            $report->markNotificationFailed($exception->getMessage());
        }
        $this->reports->save();

        return $report;
    }

    /**
     * @return array<string, string>
     */
    private function safeHeaders(): array
    {
        $headers = [];
        foreach (['Accept-Language', 'Referer', 'User-Agent', 'Sec-CH-UA', 'Sec-CH-UA-Mobile', 'Sec-CH-UA-Platform', 'X-Forwarded-For'] as $name) {
            $value = $this->request->getHeader($name);
            if ($value !== null && $value !== '') {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
