<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use App\Model\Event\Event;
use App\Model\Registration\Registration;
use App\Model\Security\Crypto;
use Nette\Application\LinkGenerator;
use Nette\Mail\Message;

/**
 * Fills the event's templates with the participant's values and builds the Nette message.
 */
final class MailComposer
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly MarkdownRenderer $markdown,
        private readonly EmailTemplateProvider $templates,
        private readonly LinkGenerator $links,
        private readonly Crypto $crypto,
        private readonly string $appBaseUrl,
        private readonly string $systemEmail,
    ) {
    }

    /**
     * @param array<string, string> $extra extra symbol values (poznamka, kod)
     */
    public function compose(Event $event, EmailType $type, ?Registration $registration, array $extra = []): ComposedMail
    {
        $template = $this->template($event, $type);
        $values = array_merge($this->values($event, $registration), $extra);

        $subject = $this->renderer->render($template->subject, $values);
        if ($type === EmailType::Reminder) {
            $subject .= ' - připomínka';
        }
        $text = $this->renderer->render($template->body, $values);

        return new ComposedMail($subject, $text, $this->markdown->toHtml($text));
    }

    /**
     * Sample values for the template editor preview.
     */
    public function preview(Event $event, EmailTemplate $template): ComposedMail
    {
        $values = array_merge($this->values($event, null), [
            'jmeno' => 'Anna',
            'prijmeni' => 'Vzorová',
            'odkaz-prihlaska' => $this->appBaseUrl.'/'.$event->getUnitId().'/'.$event->getSlug().'/prihlaska/ukazkovy-odkaz',
            'poznamka' => 'Ukázkový text zprávy.',
            'kod' => '1234',
        ]);
        $text = $this->renderer->render($template->body, $values);

        return new ComposedMail($this->renderer->render($template->subject, $values), $text, $this->markdown->toHtml($text));
    }

    /**
     * @param list<array{email: string, name: string|null}> $to
     */
    public function message(Event $event, ComposedMail $mail, array $to): Message
    {
        $message = new Message();
        $message->setFrom($event->getMailAccount()?->getEmail() ?? $this->systemEmail, $event->getContactName());
        $message->addReplyTo($event->getContactEmail(), $event->getContactName());
        foreach ($to as $recipient) {
            $message->addTo($recipient['email'], $recipient['name']);
        }
        $message->setSubject($mail->subject);
        $message->setBody($mail->text);
        $message->setHtmlBody('<!doctype html><html lang="cs"><body style="font-family: sans-serif; line-height: 1.5">'.$mail->html.'</body></html>');

        return $message;
    }

    /**
     * @return array<string, string>
     */
    public function values(Event $event, ?Registration $registration): array
    {
        $contact = $event->getContactName().', '.$event->getContactEmail().($event->getContactPhone() === null ? '' : ', '.$event->getContactPhone());
        $dateFrom = $event->getDateFrom()->format('d.m.Y');
        $termin = $event->getDateTo() == $event->getDateFrom() ? $dateFrom : $dateFrom.' – '.$event->getDateTo()->format('d.m.Y');

        return [
            'jmeno' => $registration?->getFirstName() ?? '',
            'prijmeni' => $registration?->getLastName() ?? '',
            'akce' => $event->getName(),
            'termin' => $termin,
            'misto' => $event->getPlace() ?? '',
            'cena' => $event->getPrice() === null ? '' : $event->getPrice().' Kč',
            'odkaz-prihlaska' => $registration === null ? '' : $this->registrationLink($event, $registration),
            'odkaz-web' => $this->appBaseUrl.'/'.$event->getUnitId().'/'.$event->getSlug().'?t='.$event->getWebToken(),
            'uzaverka' => $event->getLastChangeDay()->format('d.m.Y'),
            'kontakt' => $contact,
            'jednotka' => $event->getContactName(),
            'poznamka' => '',
            'kod' => '',
        ];
    }

    private function registrationLink(Event $event, Registration $registration): string
    {
        return $this->links->link('Web:Registration:default', [
            'unitId' => $event->getUnitId(),
            'slug' => $event->getSlug(),
            'token' => $this->crypto->decrypt($registration->getTokenEncrypted()),
        ]);
    }

    private function template(Event $event, EmailType $type): EmailTemplate
    {
        if ($type === EmailType::Rejected) {
            return $this->templates->unitTemplate($event->getUnitId(), $type);
        }
        if ($type === EmailType::EditCode) {
            return EmailTemplate::default($type);
        }

        return $event->getEmail($type->templateType())?->toTemplate() ?? EmailTemplate::default($type->templateType());
    }
}
