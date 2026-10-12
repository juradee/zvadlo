<?php

declare(strict_types=1);

namespace App\Model\BugReport;

use App\Model\Mailing\MailerFactory;
use Nette\Mail\Message;
use Nette\Utils\Json;
use RuntimeException;

/**
 * System mail around bug reports (sendmail in production, Mailpit in dev), same texts as h.skauting.
 */
final class BugReportMailer
{
    /**
     * @param list<string> $adminEmails ERROR_EMAILS
     */
    public function __construct(
        private readonly MailerFactory $mailers,
        private readonly array $adminEmails,
        private readonly string $systemEmail,
        private readonly string $appBaseUrl,
        private readonly ScreenshotStorage $screenshots,
    ) {
    }

    public function notifyAdmins(TechnicalErrorReport $report): void
    {
        $recipients = array_values(array_filter($this->adminEmails));
        if ($recipients === []) {
            throw new RuntimeException('Není nastaven žádný příjemce hlášení (ERROR_EMAILS).');
        }

        $message = $this->message()
            ->setSubject(\sprintf('[Zvadlo] Hlášení technické chyby #%d', $report->getId()))
            ->setBody(implode("\n", [
                'Byla nahlášena technická chyba ve Zvadle.',
                '',
                'Hlášení: #'.$report->getId(),
                'Administrace: '.$this->appBaseUrl.'/admin/hlaseni-chyb/'.$report->getId(),
                'Nahlášeno: '.$report->getCreatedAt()->format('d.m.Y H:i:s'),
                'Uživatel: '.$report->getReporterDisplayName().' (ID '.$report->getReporterUserId().')',
                'E-mail uživatele: '.($report->getReporterEmail() ?? '-'),
                'Role: '.($report->getRoleName() ?? '-').($report->getRoleId() === null ? '' : ' (ID '.$report->getRoleId().')'),
                'Jednotka: '.($report->getUnitName() ?? '-').($report->getUnitId() === null ? '' : ' (ID '.$report->getUnitId().')'),
                'URL chyby: '.($report->getReportedUrl() ?? '-'),
                'IP: '.($report->getIpAddress() ?? '-'),
                'Release: '.$report->getAppRelease(),
                'User-Agent: '.($report->getUserAgent() ?? '-'),
                'Screenshot: '.($report->hasScreenshot() ? ($report->getScreenshotOriginalName() ?? 'přiložen') : '-'),
                '',
                'Popis:',
                $report->getDescription(),
                '',
                'Diagnostika:',
                Json::encode($report->getDiagnostics(), pretty: true),
            ]));
        foreach ($recipients as $recipient) {
            $message->addTo($recipient);
        }
        if ($report->getReporterEmail() !== null) {
            $message->addReplyTo($report->getReporterEmail(), $report->getReporterDisplayName());
        }
        $screenshot = $this->screenshots->absolutePath($report);
        if ($screenshot !== null) {
            $message->addAttachment($report->getScreenshotOriginalName() ?? 'screenshot', (string) file_get_contents($screenshot), $report->getScreenshotContentType());
        }

        $this->mailers->system()->send($message);
    }

    public function sendReply(TechnicalErrorReport $report, string $text): void
    {
        $this->toReporter($report, \sprintf('[Zvadlo] Odpověď k hlášení #%d', $report->getId()), [
            'Dobrý den,',
            '',
            \sprintf('posíláme odpověď k vámi nahlášenému problému #%d ve Zvadle.', $report->getId()),
            '',
            'Zpráva správce:',
            $text,
        ]);
    }

    public function sendResolution(TechnicalErrorReport $report, string $text): void
    {
        $this->toReporter($report, \sprintf('[Zvadlo] Hlášení #%d bylo zpracováno', $report->getId()), [
            'Dobrý den,',
            '',
            \sprintf('vámi nahlášený problém #%d ve Zvadle byl zpracován.', $report->getId()),
            '',
            'Zpráva správce:',
            $text,
        ]);
    }

    public function sendRejection(TechnicalErrorReport $report, string $text): void
    {
        $this->toReporter($report, \sprintf('[Zvadlo] Hlášení #%d bylo zamítnuto', $report->getId()), [
            'Dobrý den,',
            '',
            \sprintf('vámi nahlášený problém #%d ve Zvadle byl uzavřen bez opravy.', $report->getId()),
            '',
            'Důvod uzavření:',
            $text,
        ]);
    }

    /**
     * @param list<string> $lines
     */
    private function toReporter(TechnicalErrorReport $report, string $subject, array $lines): void
    {
        $recipient = $report->getReporterEmail();
        if ($recipient === null) {
            throw new RuntimeException('U hlášení není uložený e-mail autora.');
        }

        $message = $this->message()
            ->addTo($recipient, $report->getReporterDisplayName())
            ->setSubject($subject)
            ->setBody(implode("\n", [...$lines, '', 'Původní hlášení:', $report->getDescription(), '', 'URL chyby: '.($report->getReportedUrl() ?? '-'), '', 'Děkujeme za nahlášení.', 'Zvadlo']));
        $this->mailers->system()->send($message);
    }

    private function message(): Message
    {
        return (new Message())->setFrom($this->systemEmail, 'Zvadlo');
    }
}
