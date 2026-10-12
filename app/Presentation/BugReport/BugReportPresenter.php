<?php

declare(strict_types=1);

namespace App\Presentation\BugReport;

use App\Model\BugReport\BugReportService;
use App\Model\BugReport\ScreenshotStorage;
use App\Model\User\CurrentUserEmail;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\SecuredPresenter;
use InvalidArgumentException;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

/**
 * "Nahlásit problém" from the footer; the same form and processing as in h.skauting.
 */
final class BugReportPresenter extends SecuredPresenter
{
    private ?string $reportedUrl = null;

    private ?string $reporterEmail = null;

    public function __construct(
        private readonly BugReportService $service,
        private readonly CurrentUserEmail $currentUserEmail,
        private readonly FormFactory $forms,
    ) {
        parent::__construct();
    }

    public function actionDefault(?string $url = null): void
    {
        $this->reportedUrl = $url;
        $this->reporterEmail = $this->currentUserEmail->main();
    }

    public function renderDefault(): void
    {
        $this->template->reporterEmail = $this->reporterEmail;
    }

    protected function createComponentBugReportForm(): Form
    {
        $form = $this->forms->create();
        $form->addTextArea('description', 'Popis chybového chování', null, 10)
            ->setRequired('Popište prosím chybové chování.')
            ->addRule($form::MaxLength, 'Popis může mít nejvýše %d znaků.', 10000)
            ->setOption('description', 'Uveďte, co jste dělali, co jste očekávali a co se místo toho stalo.');
        $form->addText('url', 'URL stránky s chybou')
            ->setNullable()
            ->setDefaultValue($this->reportedUrl)
            ->setHtmlAttribute('placeholder', 'https://…')
            ->addCondition($form::Filled)
                ->addRule($form::URL, 'Zadejte platnou URL adresu.')
                ->addRule($form::MaxLength, 'URL může mít nejvýše %d znaků.', 2048);
        if ($this->reporterEmail === null) {
            $form->addEmail('reporterEmail', 'Váš e-mail')
                ->setRequired('Zadejte prosím e-mail, na který vám můžeme odpovědět.')
                ->addRule($form::MaxLength, 'E-mail může mít nejvýše %d znaků.', 255)
                ->setOption('description', 'E-mail se použije pro odpověď správců a jako Reply-To v hlášení.');
        }
        $form->addUpload('screenshot', 'Screenshot')
            ->setHtmlAttribute('accept', 'image/*')
            ->addRule($form::MaxFileSize, 'Screenshot může mít nejvýše 5 MB.', ScreenshotStorage::MAX_SIZE)
            ->setOption('description', 'Volitelně přiložte screenshot chyby (PNG, JPEG, GIF nebo WebP, nejvýše 5 MB).');
        $form->addHidden('clientDiagnostics', '{}')->setHtmlAttribute('data-bug-report-diagnostics', 'true');
        $form->addSubmit('send', 'Odeslat hlášení');

        $form->onValidate[] = static function (Form $form): void {
            $upload = $form->getValues()['screenshot'];
            if ($upload instanceof FileUpload && $upload->hasFile() && $upload->isOk() && ! ScreenshotStorage::accepts($upload->getContentType())) {
                $form->addError('Screenshot musí být obrázek PNG, JPEG, GIF nebo WebP.');
            }
        };

        $form->onSuccess[] = function (Form $form, array $values): void {
            $screenshot = $values['screenshot'];
            try {
                $report = $this->service->submit(
                    (string) $values['description'],
                    $values['url'] === null ? null : (string) $values['url'],
                    $this->decodeDiagnostics((string) $values['clientDiagnostics']),
                    isset($values['reporterEmail']) ? (string) $values['reporterEmail'] : $this->reporterEmail,
                    $screenshot instanceof FileUpload ? $screenshot : null,
                );
            } catch (InvalidArgumentException $exception) {
                $form->addError($exception->getMessage());

                return;
            }

            $this->flashMessage(
                $report->wasNotificationSent() ? 'Hlášení technické chyby bylo uloženo a odesláno správcům.' : 'Hlášení technické chyby bylo uloženo. E-mailové upozornění se nepodařilo odeslat.',
                $report->wasNotificationSent() ? 'success' : 'warning',
            );
            $this->redirect(':Dashboard:default');
        };

        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeDiagnostics(string $value): array
    {
        if ($value === '' || \strlen($value) > 50000) {
            return [];
        }
        try {
            $decoded = Json::decode($value, forceArrays: true);
        } catch (JsonException) {
            return [];
        }

        return \is_array($decoded) ? $decoded : [];
    }
}
