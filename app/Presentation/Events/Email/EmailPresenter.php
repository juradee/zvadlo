<?php

declare(strict_types=1);

namespace App\Presentation\Events\Email;

use App\Model\Event\Event;
use App\Model\Event\EventEmail;
use App\Model\Event\EventRepository;
use App\Model\Event\EventService;
use App\Model\Mailing\EmailTemplate;
use App\Model\Mailing\EmailType;
use App\Model\Mailing\EventMailer;
use App\Model\Mailing\MailComposer;
use App\Model\Mailing\TemplateRenderer;
use App\Model\User\CurrentUserEmail;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\SecuredPresenter;
use LogicException;
use Nette\Application\UI\Form;
use Throwable;

/**
 * The event's e-mail texts: editor per type with a preview and a test mail to the leader.
 */
final class EmailPresenter extends SecuredPresenter
{
    private ?Event $event = null;

    private ?EventEmail $edited = null;

    public function __construct(
        private readonly EventRepository $events,
        private readonly EventService $service,
        private readonly MailComposer $composer,
        private readonly EventMailer $mailer,
        private readonly CurrentUserEmail $currentUserEmail,
        private readonly FormFactory $forms,
    ) {
        parent::__construct();
    }

    public function actionDefault(int $id, ?string $typ = null): void
    {
        $event = $this->events->find($id);
        if ($event === null) {
            $this->error('Akce nebyla nalezena.');
        }
        if (! $this->authorizator->canEditUnit($event->getUnitId())) {
            $this->error('K této akci nemáte oprávnění.', 403);
        }

        $this->event = $event;
        $type = $typ === null ? EmailType::Invitation : (EmailType::tryFrom($typ) ?? EmailType::Invitation);
        $this->edited = $event->getEmail($type->templateType());
        if ($this->edited === null) {
            $event->seedEmails([EmailTemplate::default($type->templateType())]);
            $this->events->save();
            $this->edited = $event->getEmail($type->templateType());
        }
    }

    public function renderDefault(): void
    {
        $event = $this->requireEvent();
        $edited = $this->requireEdited();

        $this->template->event = $event;
        $this->template->emails = $event->getEmails();
        $this->template->edited = $edited;
        $this->template->preview = $this->composer->preview($event, $edited->toTemplate());
        $this->template->symbols = TemplateRenderer::SYMBOLS;
    }

    protected function createComponentEmailForm(): Form
    {
        $edited = $this->requireEdited();

        $form = $this->forms->create();
        if ($edited->getType()->isOptional()) {
            $form->addCheckbox('enabled', 'Posílat tento e-mail');
        }
        $form->addText('subject', 'Předmět')
            ->setRequired('Zadejte předmět.')
            ->addRule($form::MaxLength, 'Předmět může mít nejvýše %d znaků.', 255);
        $form->addTextArea('body', 'Text (Markdown)', null, 14)
            ->setRequired('Zadejte text.');
        $form->addSubmit('save', 'Uložit');
        $form->addSubmit('test', 'Uložit a poslat zkušební e-mail sobě')->setOption('class', 'btn btn-outline-primary');
        $form->setDefaults(['enabled' => $edited->isEnabled(), 'subject' => $edited->getSubject(), 'body' => $edited->getBody()]);

        $form->onSuccess[] = function (Form $form, array $values): void {
            $edited = $this->requireEdited();
            $this->service->updateEmail($edited, (bool) ($values['enabled'] ?? true), (string) $values['subject'], (string) $values['body']);

            $button = $form->isSubmitted();
            if ($button instanceof \Nette\Forms\Controls\SubmitButton && (string) $button->getName() === 'test') {
                $this->sendTestMail($edited);
            } else {
                $this->flashMessage('Text e-mailu byl uložen.', 'success');
            }

            $this->redirect('this');
        };

        return $form;
    }

    private function sendTestMail(EventEmail $edited): void
    {
        $event = $this->requireEvent();
        $email = $this->currentUserEmail->main();
        if ($email === null) {
            $this->flashMessage('Text je uložen, ale ve skautISu nemáte hlavní e-mail, zkušební e-mail nelze poslat.', 'warning');

            return;
        }

        try {
            $messages = $this->mailer->send($event, $edited->getType(), null, null, [['email' => $email, 'name' => $this->currentUser->getUser()->displayName]], $this->composer->preview($event, $edited->toTemplate()));
            $failed = array_filter($messages, static fn ($message): bool => ! $message->isSent());
            $this->flashMessage($failed === [] ? \sprintf('Zkušební e-mail odešel na %s.', $email) : 'Zkušební e-mail se nepodařilo odeslat: '.(reset($failed)->getError() ?? ''), $failed === [] ? 'success' : 'danger');
        } catch (Throwable $exception) {
            $this->flashMessage('Zkušební e-mail se nepodařilo odeslat: '.$exception->getMessage(), 'danger');
        }
    }

    private function requireEvent(): Event
    {
        return $this->event ?? throw new LogicException('The event is loaded in the action.');
    }

    private function requireEdited(): EventEmail
    {
        return $this->edited ?? throw new LogicException('The e-mail is loaded in the action.');
    }
}
