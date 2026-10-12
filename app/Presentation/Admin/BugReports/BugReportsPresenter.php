<?php

declare(strict_types=1);

namespace App\Presentation\Admin\BugReports;

use App\Model\BugReport\BugReportMailer;
use App\Model\BugReport\GitHubIssueClient;
use App\Model\BugReport\ScreenshotStorage;
use App\Model\BugReport\TechnicalErrorReport;
use App\Model\BugReport\TechnicalErrorReportRepository;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\Admin\AdminBasePresenter;
use DateTimeImmutable;
use LogicException;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Form;
use Nette\Utils\Json;
use Throwable;

/**
 * Open bug reports, their diagnostics and the three actions: reply, GitHub issue, resolve or reject.
 */
final class BugReportsPresenter extends AdminBasePresenter
{
    private ?TechnicalErrorReport $report = null;

    public function __construct(
        private readonly TechnicalErrorReportRepository $reports,
        private readonly BugReportMailer $mailer,
        private readonly ScreenshotStorage $screenshots,
        private readonly GitHubIssueClient $github,
        private readonly FormFactory $forms,
    ) {
        parent::__construct();
    }

    protected function hasAccess(): bool
    {
        return $this->adminAccess->canAccessBugReports();
    }

    public function renderDefault(): void
    {
        $this->template->open = $this->reports->findOpen();
        $this->template->resolved = $this->reports->findResolved(20);
    }

    public function actionDetail(int $id): void
    {
        $report = $this->reports->find($id);
        if ($report === null) {
            $this->flashMessage('Hlášení technické chyby nebylo nalezeno.', 'warning');
            $this->redirect('default');
        }
        $this->report = $report;
    }

    public function renderDetail(): void
    {
        $report = $this->requireReport();
        $this->template->report = $report;
        $this->template->diagnosticsJson = Json::encode($report->getDiagnostics(), pretty: true);
        $this->template->githubConfigured = $this->github->isConfigured();
    }

    public function actionScreenshot(int $id): void
    {
        $report = $this->reports->find($id);
        $path = $report === null ? null : $this->screenshots->absolutePath($report);
        if ($report === null || $path === null) {
            $this->flashMessage('Screenshot hlášení nebyl nalezen.', 'warning');
            $this->redirect('default');
        }

        $contentType = $report->getScreenshotContentType();
        $inline = ScreenshotStorage::accepts($contentType);
        $this->getHttpResponse()->setHeader('X-Content-Type-Options', 'nosniff');
        $this->getHttpResponse()->setHeader('Content-Security-Policy', 'sandbox');
        $this->sendResponse(new FileResponse($path, $report->getScreenshotOriginalName() ?? 'screenshot', $inline ? (string) $contentType : 'application/octet-stream', forceDownload: ! $inline));
    }

    protected function createComponentReplyForm(): Form
    {
        $form = $this->forms->create();
        $form->addTextArea('message', 'Odpověď uživateli', null, 6)
            ->setRequired('Napište prosím odpověď autorovi hlášení.')
            ->addRule($form::MaxLength, 'Odpověď může mít nejvýše %d znaků.', 10000);
        $form->addSubmit('send', 'Odeslat odpověď');
        $form->onSuccess[] = function (Form $form, array $values): void {
            $report = $this->requireOpenReport();
            $message = trim((string) $values['message']);
            $now = new DateTimeImmutable();
            try {
                $this->mailer->sendReply($report, $message);
                $reply = $report->markReplySent($message, $now);
                if ($report->hasGithubIssue()) {
                    try {
                        $reply->markGithubCommentCreated($this->github->addComment($report, $message));
                    } catch (Throwable $exception) {
                        $reply->markGithubCommentFailed($exception->getMessage());
                        $this->flashMessage('Odpověď byla odeslána, ale komentář na GitHub se nepodařilo přidat: '.$exception->getMessage(), 'warning');
                    }
                }
                $this->reports->save();
                if ($reply->getGithubCommentError() === null) {
                    $this->flashMessage('Odpověď autorovi hlášení byla odeslána.', 'success');
                }
            } catch (Throwable $exception) {
                $report->markReplyFailed($exception->getMessage());
                $this->reports->save();
                $this->flashMessage('Odpověď se nepodařilo odeslat: '.$exception->getMessage(), 'warning');
            }
            $this->redirect('this');
        };

        return $form;
    }

    protected function createComponentIssueForm(): Form
    {
        $form = $this->forms->create();
        $form->addSubmit('create', 'Vytvořit GitHub issue')->setOption('class', 'btn btn-outline-dark btn-sm');
        $form->onSuccess[] = function (): void {
            $report = $this->requireOpenReport();
            if ($report->hasGithubIssue()) {
                $this->flashMessage('GitHub issue už u hlášení je.', 'info');
                $this->redirect('this');
            }
            try {
                $issue = $this->github->createIssue($report);
                $report->markGithubIssueCreated($issue['number'], $issue['url'], new DateTimeImmutable());
                $this->flashMessage('GitHub issue bylo vytvořeno.', 'success');
            } catch (Throwable $exception) {
                $report->markGithubSyncFailed($exception->getMessage());
                $this->flashMessage('GitHub issue se nepodařilo vytvořit: '.$exception->getMessage(), 'warning');
            }
            $this->reports->save();
            $this->redirect('this');
        };

        return $form;
    }

    protected function createComponentResolveForm(): Form
    {
        $form = $this->forms->create();
        $form->addTextArea('message', 'Zpráva autorovi', null, 4)
            ->setDefaultValue('Požadavek byl zpracován a oprava je v nové verzi aplikace.')
            ->setRequired('Napište zprávu autorovi hlášení.')
            ->addRule($form::MaxLength, 'Zpráva může mít nejvýše %d znaků.', 10000);
        $form->addSubmit('resolve', 'Potvrdit opravu')->setOption('class', 'btn btn-success');
        $form->addSubmit('reject', 'Zamítnout')->setOption('class', 'btn btn-outline-danger')
            ->setHtmlAttribute('data-confirm', 'Zamítnout hlášení a poslat autorovi uvedenou zprávu?');
        $form->onSuccess[] = function (Form $form, array $values): void {
            $report = $this->requireOpenReport();
            $message = trim((string) $values['message']);
            $button = $form->isSubmitted();
            $rejected = $button instanceof \Nette\Forms\Controls\SubmitButton && (string) $button->getName() === 'reject';
            $now = new DateTimeImmutable();

            try {
                if ($rejected) {
                    $this->mailer->sendRejection($report, $message);
                } else {
                    $this->mailer->sendResolution($report, $message);
                }
                $report->markResolutionNotificationSent($now);
                $this->flashMessage($rejected ? 'Hlášení bylo zamítnuto a autor informován e-mailem.' : 'Hlášení bylo označeno jako opravené a autor informován e-mailem.', 'success');
            } catch (Throwable $exception) {
                $report->markResolutionNotificationFailed($exception->getMessage());
                $this->flashMessage(($rejected ? 'Hlášení bylo zamítnuto' : 'Hlášení bylo označeno jako opravené').', ale e-mail autorovi se nepodařilo odeslat: '.$exception->getMessage(), 'warning');
            }
            $report->resolve($rejected ? TechnicalErrorReport::RESOLUTION_REJECTED : TechnicalErrorReport::RESOLUTION_FIXED, $message, $now);
            $this->reports->save();
            $this->redirect('default');
        };

        return $form;
    }

    private function requireOpenReport(): TechnicalErrorReport
    {
        $report = $this->requireReport();
        if ($report->isResolved()) {
            $this->flashMessage('Hlášení už je vyřízené.', 'warning');
            $this->redirect('default');
        }

        return $report;
    }

    private function requireReport(): TechnicalErrorReport
    {
        return $this->report ?? throw new LogicException('The report is loaded in the action.');
    }
}
