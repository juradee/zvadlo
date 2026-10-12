<?php

declare(strict_types=1);

namespace App\Presentation\Events\Registration;

use App\Model\Event\Event;
use App\Model\Event\EventField;
use App\Model\Event\EventRepository;
use App\Model\Mailing\EmailMessageRepository;
use App\Model\Mailing\EmailType;
use App\Model\Mailing\MailerFactory;
use App\Model\Registration\DuplicateRegistrationException;
use App\Model\Registration\EventFullException;
use App\Model\Registration\RecipientSource;
use App\Model\Registration\Registration;
use App\Model\Registration\RegistrationOrigin;
use App\Model\Registration\RegistrationRecipient;
use App\Model\Registration\RegistrationRepository;
use App\Model\Registration\RegistrationService;
use App\Model\Registration\RegistrationState;
use App\Model\Task\Task;
use App\Model\Task\TaskAlreadyRunningException;
use App\Model\Task\TaskQueue;
use App\Model\Task\TaskRepository;
use App\Model\Task\TaskState;
use App\Model\Task\TaskType;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\SecuredPresenter;
use DateTimeImmutable;
use LogicException;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Nette\Forms\Controls\SubmitButton;
use Nette\Utils\Strings;

/**
 * Registrations of one event as the leader sees them: list with address selection, export and the detail
 * with manual actions.
 */
final class RegistrationPresenter extends SecuredPresenter
{
    private ?Event $event = null;

    private ?Registration $registration = null;

    /** @var list<Registration> */
    private array $listed = [];

    public function __construct(
        private readonly EventRepository $events,
        private readonly RegistrationRepository $registrations,
        private readonly RegistrationService $service,
        private readonly TaskQueue $queue,
        private readonly TaskRepository $tasks,
        private readonly EmailMessageRepository $messages,
        private readonly MailerFactory $mailers,
        private readonly FormFactory $forms,
    ) {
        parent::__construct();
    }

    public function actionDefault(int $id, ?string $stav = null, ?string $hledat = null): void
    {
        $event = $this->loadEvent($id);
        $this->event = $event;

        $state = $stav === null ? null : RegistrationState::tryFrom($stav);
        $search = $hledat === null ? '' : Strings::lower(trim($hledat));
        $this->listed = array_values(array_filter(
            $this->registrations->findByEvent($event, $state),
            static fn (Registration $registration): bool => $search === '' || str_contains(Strings::lower($registration->getDisplayName()), $search),
        ));
    }

    public function renderDefault(?string $stav = null, ?string $hledat = null): void
    {
        $event = $this->requireEvent();
        $state = $stav === null ? null : RegistrationState::tryFrom($stav);
        $canEdit = $this->authorizator->canEditUnit($event->getUnitId());
        $counts = $this->registrations->countByState($event);

        $this->template->event = $event;
        $this->template->registrations = $this->listed;
        $this->template->counts = $counts;
        $this->template->total = array_sum($counts) - $counts[RegistrationState::Rejected->value];
        $this->template->states = RegistrationState::cases();
        $this->template->state = $state;
        $this->template->search = $hledat ?? '';
        $this->template->canEdit = $canEdit;
        $this->template->now = new DateTimeImmutable();
        $this->template->tasks = $this->tasks->findStartedByEvent($event);
        $automaticActive = \count($this->tasks->findAutomaticByEvent($event, TaskState::Queued, TaskState::Running));
        $automaticFailed = \count($this->tasks->findAutomaticByEvent($event, TaskState::Failed));
        $this->template->automaticActive = $automaticActive;
        $this->template->automaticFailed = $automaticFailed;
        $this->template->automaticLabel = self::automaticLabel($automaticActive, $automaticFailed);
        $this->template->invitable = \count($this->invitable());
        $this->template->remindable = \count($this->remindable());
        $this->template->canSend = $canEdit && ($event->getMailAccount() !== null || ! $this->mailers->requiresAccount());
        $this->template->places = $this->service->placesAndWaitlist($event);
    }

    /**
     * A place is free and someone waits: the leader decides whether the first on the waiting list gets it.
     */
    protected function createComponentPromoteNextForm(): Form
    {
        $form = $this->forms->create();
        $form->addSubmit('promote', 'Přijmout prvního náhradníka')->setOption('class', 'btn btn-sm btn-success');
        $form->onSuccess[] = function (): void {
            $event = $this->requireEvent();
            if (! $this->authorizator->canEditUnit($event->getUnitId())) {
                $this->error('Tuto akci nemůžete spravovat.', 403);
            }
            $next = $this->service->placesAndWaitlist($event)['waitlisted'][0] ?? null;
            if ($next === null) {
                $this->flashMessage('Na čekací listině už nikdo není.', 'info');
                $this->redirect('this');
            }
            try {
                $this->service->promote($next, $this->currentUser->getUser()->id);
                $this->flashMessage(\sprintf('%s je teď mezi účastníky, odchází mu e-mail o přesunu z čekací listiny.', $next->getDisplayName()), 'success');
            } catch (EventFullException $exception) {
                $this->flashMessage($exception->getMessage(), 'warning');
            }
            $this->redirect('this');
        };

        return $form;
    }

    /**
     * AJAX: progress of one task for the panel on the page.
     */
    public function handleTaskStatus(int $task): void
    {
        $found = $this->tasks->find($task);
        if ($found === null || $found->getEvent() !== $this->requireEvent()) {
            $this->error('Úloha nebyla nalezena.');
        }

        $this->sendJson([
            'state' => $found->getState()->value,
            'label' => $found->getProgressLabel(),
            'active' => $found->getState()->isActive(),
            'done' => $found->getProgressDone(),
            'total' => $found->getProgressTotal(),
            'percent' => $found->getProgressPercent(),
            'error' => $found->getError(),
        ]);
    }

    /**
     * AJAX: the summary of the automatic mails, which the page shows as one panel.
     */
    public function handleAutomaticStatus(): void
    {
        $event = $this->requireEvent();
        $active = \count($this->tasks->findAutomaticByEvent($event, TaskState::Queued, TaskState::Running));

        $this->sendJson([
            'active' => $active > 0,
            'label' => self::automaticLabel($active, \count($this->tasks->findAutomaticByEvent($event, TaskState::Failed))),
        ]);
    }

    public function actionExport(int $id): void
    {
        $event = $this->loadEvent($id);
        $fields = $event->getFields();

        $rows = [[
            'Příjmení', 'Jméno', 'Přezdívka', 'skautIS ID', 'Datum narození', 'Pohlaví', 'Stav', 'Původ', 'E-mail', 'Telefon',
            'Ulice', 'Město', 'PSČ', 'Poznámka', 'Důvod odmítnutí', 'Odpověděl', 'E-maily pozvánky',
            ...array_map(static fn (EventField $field): string => $field->getLabel(), $fields),
        ]];
        foreach ($this->registrations->findByEvent($event) as $registration) {
            $rows[] = [
                $registration->getLastName() ?? '',
                $registration->getFirstName() ?? '',
                $registration->getNickName() ?? '',
                $registration->getSkautisPersonId() ?? '',
                $registration->getBirthDate()?->format('d.m.Y') ?? '',
                $registration->getSex()?->label() ?? '',
                $registration->getState()->label(),
                $registration->getOrigin()->label(),
                $registration->getContactEmail() ?? '',
                $registration->getPhone() ?? '',
                $registration->getStreet() ?? '',
                $registration->getCity() ?? '',
                $registration->getPostcode() ?? '',
                $registration->getNote() ?? '',
                $registration->getDeclineReason() ?? '',
                $registration->getRespondedAt()?->format('d.m.Y H:i') ?? '',
                implode(', ', $registration->getSelectedRecipientEmails()),
                ...array_map(static fn (EventField $field): string => $registration->getFieldValueText($field), $fields),
            ];
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new LogicException('Cannot open a temporary stream.');
        }
        fwrite($stream, "\u{FEFF}");
        foreach ($rows as $row) {
            fputcsv($stream, array_map(self::csvSafe(...), $row), ';', '"', '', "\r\n");
        }
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        $response = $this->getHttpResponse();
        $response->setContentType('text/csv', 'utf-8');
        $response->setHeader('Content-Disposition', 'attachment; filename="prihlasky-'.$event->getSlug().'.csv"');
        $this->sendResponse(new TextResponse($csv));
    }

    /**
     * Participants type the cells; a leading =, +, -, @ or control character would run as a formula in Excel.
     */
    private static function csvSafe(mixed $cell): string
    {
        $text = (string) $cell;

        return $text !== '' && (str_contains('=+-@', $text[0]) || \ord($text[0]) < 32) ? "'".$text : $text;
    }

    public function actionDetail(int $id, int $rid): void
    {
        $this->event = $this->loadEvent($id);
        $registration = $this->registrations->find($rid);
        if ($registration === null || $registration->getEvent() !== $this->event) {
            $this->error('Přihláška nebyla nalezena.');
        }

        $this->registration = $registration;
    }

    public function renderDetail(): void
    {
        $event = $this->requireEvent();
        $registration = $this->requireRegistration();
        $canEdit = $this->authorizator->canEditUnit($event->getUnitId());
        $rejected = $registration->getState() === RegistrationState::Rejected;

        $this->template->event = $event;
        $this->template->registration = $registration;
        $this->template->fields = $event->getFields();
        $this->template->canEdit = $canEdit;
        $this->template->personalLink = $canEdit && ! $rejected ? $this->personalLink($event, $this->service->getToken($registration)) : null;
        $this->template->canPair = $canEdit && ! $rejected && ! $registration->isMember() && $registration->getOrigin() !== RegistrationOrigin::Invited;
        $this->template->mailLog = $this->messages->findByRegistration($registration);
        $this->template->now = new DateTimeImmutable();
    }

    /**
     * The inline edit of one row: which addresses get the mail, optionally one more by hand.
     *
     * @return Multiplier<Form>
     */
    protected function createComponentRowRecipients(): Multiplier
    {
        return new Multiplier(function (string $registrationId): Form {
            $registration = null;
            foreach ($this->listed as $listed) {
                if ($listed->getId() === (int) $registrationId) {
                    $registration = $listed;
                }
            }
            if ($registration === null) {
                $this->error('Přihláška nebyla nalezena.');
            }

            $items = [];
            $selected = [];
            foreach ($registration->getRecipients() as $recipient) {
                $items[$recipient->getId()] = $recipient->getEmail().' ('.$recipient->getSource()->label().')';
                if ($recipient->isSelected()) {
                    $selected[] = $recipient->getId();
                }
            }
            $form = $this->forms->create();
            $form->addCheckboxList('selected', null, $items)->setDefaultValue($selected);
            $form->addEmail('email')->setNullable();
            $form->addSubmit('save', 'Uložit');
            $form->onSuccess[] = function (Form $form, array $values) use ($registration): void {
                if (! $this->authorizator->canEditUnit($this->requireEvent()->getUnitId())) {
                    $this->error('Tuto akci nemůžete spravovat.', 403);
                }
                $this->service->updateRecipients(
                    $registration,
                    array_values(array_map(intval(...), (array) $values['selected'])),
                    $values['email'] === null ? null : (string) $values['email'],
                    $this->currentUser->getUser()->id,
                );
                $this->flashMessage(\sprintf('Adresy u %s byly uloženy.', $registration->getDisplayName()), 'success');
                $this->redirect('this');
            };

            return $form;
        });
    }

    /**
     * Sending: invitations to those never invited, reminders to those invited without an answer, a free message.
     */
    protected function createComponentSendForm(): Form
    {
        $form = $this->forms->create();
        $form->addSubmit('invitations', 'Odeslat pozvánky')->setOption('class', 'btn btn-primary btn-sm')->setValidationScope([]);
        $form->addSubmit('reminder', 'Poslat připomínku')->setOption('class', 'btn btn-outline-primary btn-sm')->setValidationScope([]);
        $states = [];
        foreach (RegistrationState::active() as $state) {
            $states[$state->value] = $state->label();
        }
        $form->addCheckboxList('states', 'Komu', $states)
            ->setDefaultValue([RegistrationState::Registered->value, RegistrationState::Waitlisted->value]);
        $form->addTextArea('note', 'Text zprávy', null, 4)
            ->setRequired('Napište text zprávy.');
        $form->addSubmit('info', 'Poslat zprávu')->setOption('class', 'btn btn-outline-primary btn-sm');

        $form->onSuccess[] = function (Form $form, array $values): void {
            $event = $this->requireEvent();
            if (! $this->authorizator->canEditUnit($event->getUnitId())) {
                $this->error('Tuto akci nemůžete spravovat.', 403);
            }

            $button = $form->isSubmitted();
            $action = $button instanceof SubmitButton ? (string) $button->getName() : '';
            $userId = $this->currentUser->getUser()->id;

            try {
                switch ($action) {
                    case 'invitations':
                        $targets = $this->invitable();
                        $this->enqueue(TaskType::SendInvitations, EmailType::Invitation, $targets, [], $userId, 'Pozvánky se odesílají (%d).', 'Nikdo nečeká na pozvánku: buď všichni pozvánku dostali, nebo nemají vybranou adresu.');
                        break;
                    case 'reminder':
                        if (! $event->isRegistrationOpen(new DateTimeImmutable())) {
                            $this->flashMessage('Přihlašování už neběží, připomínka nemá smysl.', 'warning');
                            break;
                        }
                        $this->enqueue(TaskType::SendReminder, EmailType::Reminder, $this->remindable(), [], $userId, 'Připomínka se odesílá (%d).', 'Nikomu dnes připomínku poslat nelze: buď už odpověděli, otevřeli odkaz, nebo ji dnes dostali.');
                        break;
                    case 'info':
                        $wanted = array_map(static fn (mixed $value): ?RegistrationState => RegistrationState::tryFrom((string) $value), (array) $values['states']);
                        $targets = array_values(array_filter(
                            $this->registrations->findByEvent($event),
                            static fn (Registration $registration): bool => \in_array($registration->getState(), $wanted, true) && $registration->getMailAddresses() !== [],
                        ));
                        $this->enqueue(TaskType::SendInfo, EmailType::Info, $targets, ['note' => (string) $values['note']], $userId, 'Zpráva se odesílá (%d).', 'Ve vybraných stavech není nikdo s e-mailem.');
                        break;
                }
            } catch (TaskAlreadyRunningException $exception) {
                $this->flashMessage($exception->getMessage(), 'warning');
            }

            $this->redirect('this');
        };

        return $form;
    }

    protected function createComponentRetryAutomaticForm(): Form
    {
        $form = $this->forms->create();
        $form->addSubmit('retry', 'Spustit znovu')->setOption('class', 'btn btn-sm btn-outline-secondary');
        $form->onSuccess[] = function (): void {
            $event = $this->requireEvent();
            if (! $this->authorizator->canEditUnit($event->getUnitId())) {
                $this->error('Tuto akci nemůžete spravovat.', 403);
            }
            foreach ($this->tasks->findAutomaticByEvent($event, TaskState::Failed) as $task) {
                $this->queue->retry($task);
            }
            $this->flashMessage('Neodeslané automatické e-maily byly zařazeny znovu.', 'success');
            $this->redirect('this');
        };

        return $form;
    }

    /**
     * @return Multiplier<Form>
     */
    protected function createComponentRetryTask(): Multiplier
    {
        return new Multiplier(function (string $taskId): Form {
            $form = $this->forms->create();
            $form->addSubmit('retry', 'Spustit znovu')->setOption('class', 'btn btn-sm btn-outline-secondary');
            $form->onSuccess[] = function () use ($taskId): void {
                $task = $this->tasks->find((int) $taskId);
                if ($task === null || $task->getEvent() !== $this->requireEvent() || ! $this->authorizator->canEditUnit($this->requireEvent()->getUnitId())) {
                    $this->error('Úloha nebyla nalezena.');
                }
                $this->queue->retry($task);
                $this->flashMessage('Úloha byla zařazena znovu.', 'success');
                $this->redirect('this');
            };

            return $form;
        });
    }

    protected function createComponentActionForm(): Form
    {
        $registration = $this->requireRegistration();
        $state = $registration->getState();

        $form = $this->forms->create();
        $form->addText('reason', 'Důvod (nepovinné, k odmítnutí nebo zamítnutí)')
            ->setNullable()
            ->addRule($form::MaxLength, 'Důvod může mít nejvýše %d znaků.', 500);
        if ($state === RegistrationState::Waitlisted) {
            $places = $this->service->placesAndWaitlist($registration->getEvent());
            if ($places['free'] === 0) {
                // Over the capacity only with the leader's explicit confirmation.
                $form->addSubmit('overbook', \sprintf('Přijmout nad kapacitu (%d míst je obsazeno)', (int) $registration->getEvent()->getCapacity()))
                    ->setOption('class', 'btn btn-warning')
                    ->setHtmlAttribute('data-confirm', \sprintf('Akce je plně obsazená (%d míst). Opravdu přijmout náhradníka nad kapacitu akce?', (int) $registration->getEvent()->getCapacity()));
            } else {
                $form->addSubmit('promote', 'Přesunout mezi přihlášené')->setOption('class', 'btn btn-success');
            }
        }
        if ($state->isAttending()) {
            $form->addSubmit('cancel', 'Zrušit účast')->setOption('class', 'btn btn-outline-danger');
        }
        if ($state !== RegistrationState::Declined && $state !== RegistrationState::Rejected) {
            $form->addSubmit('decline', 'Zaznamenat odmítnutí')->setOption('class', 'btn btn-outline-secondary');
        }
        if ($state !== RegistrationState::Rejected) {
            $form->addSubmit('reject', 'Zamítnout přihlášku')
                ->setOption('class', 'btn btn-danger')
                ->setHtmlAttribute('data-confirm', 'Zamítnout přihlášku? Osobní údaje se smažou a účastník se nebude moci přihlásit znovu.');
            $form->addSubmit('newLink', 'Vygenerovat nový odkaz')
                ->setOption('class', 'btn btn-outline-secondary')
                ->setHtmlAttribute('data-confirm', 'Starý odkaz přestane platit a je nutné poslat nový. Pokračovat?');
        }

        $form->onSuccess[] = function (Form $form, array $values): void {
            $registration = $this->requireRegistration();
            if (! $this->authorizator->canEditUnit($this->requireEvent()->getUnitId())) {
                $this->error('Tuto akci nemůžete spravovat.', 403);
            }

            $button = $form->isSubmitted();
            $action = $button instanceof SubmitButton ? (string) $button->getName() : '';
            $userId = $this->currentUser->getUser()->id;
            $reason = $values['reason'] === null ? null : (string) $values['reason'];
            $wasRegistered = $registration->getState() === RegistrationState::Registered;

            switch ($action) {
                case 'newLink':
                    $this->service->replaceToken($registration, $userId);
                    $this->flashMessage('Nový osobní odkaz je připravený. Starý přestal platit, pošlete účastníkovi nový.', 'success');
                    break;
                case 'promote':
                case 'overbook':
                    try {
                        $this->service->promote($registration, $userId, overbook: $action === 'overbook');
                        $this->flashMessage($action === 'overbook' ? 'Přijato nad kapacitu akce.' : 'Přesunuto mezi přihlášené.', 'success');
                    } catch (EventFullException $exception) {
                        $this->flashMessage($exception->getMessage(), 'warning');
                    }
                    break;
                case 'cancel':
                    $this->service->cancelByLeader($registration, $userId);
                    $this->flashMessage('Účast byla zrušena.', 'success');
                    break;
                case 'decline':
                    $this->service->markDeclined($registration, $userId, $reason);
                    $this->flashMessage('Odmítnutí bylo zaznamenáno.', 'success');
                    break;
                case 'reject':
                    $this->service->reject($registration, $userId, $reason);
                    $this->flashMessage('Přihláška byla zamítnuta a osobní údaje smazány.', 'success');
                    break;
            }

            // A leader's change does not move anyone up on its own: the leader decides about the freed place.
            if ($wasRegistered && \in_array($action, ['cancel', 'decline', 'reject'], true)) {
                $places = $this->service->placesAndWaitlist($registration->getEvent());
                if ($places['waitlisted'] !== [] && $places['free'] !== 0) {
                    $this->flashMessage(\sprintf('Uvolnilo se místo a na čekací listině je %d náhradníků, první je %s. Přijmout ho můžete v přehledu přihlášek, jinak místo zůstane volné.', \count($places['waitlisted']), $places['waitlisted'][0]->getDisplayName()), 'info');
                }
            }

            $this->redirect('this');
        };

        return $form;
    }

    protected function createComponentPairForm(): Form
    {
        $registration = $this->requireRegistration();
        $items = [];
        foreach ($this->service->suggestPairs($registration) as $person) {
            $items[$person->id] = $person->getDisplayName().($person->birthday === null ? '' : ' ('.$person->birthday->format('d.m.Y').')');
        }

        $form = $this->forms->create();
        $form->addSelect('person', 'Osoba ve skautISu', $items)
            ->setPrompt($items === [] ? 'Nikdo se stejným příjmením ani datem narození' : 'Vyberte')
            ->setRequired('Vyberte osobu.');
        $form->addSubmit('pair', 'Spárovat')->setOption('class', 'btn btn-primary btn-sm');

        $form->onSuccess[] = function (Form $form, array $values): void {
            if (! $this->authorizator->canEditUnit($this->requireEvent()->getUnitId())) {
                $this->error('Tuto akci nemůžete spravovat.', 403);
            }

            try {
                $this->service->pair($this->requireRegistration(), (int) $values['person'], $this->currentUser->getUser()->id);
                $this->flashMessage('Přihláška byla spárována se členem.', 'success');
            } catch (DuplicateRegistrationException $exception) {
                $this->flashMessage($exception->getMessage(), 'danger');
            }

            $this->redirect('this');
        };

        return $form;
    }

    protected function createComponentRecipientForm(): Form
    {
        $form = $this->forms->create();
        $form->addEmail('email', 'E-mail')->setRequired('Zadejte e-mail.');
        $form->addText('name', 'Jméno (nepovinné)')
            ->setNullable()
            ->addRule($form::MaxLength, 'Jméno může mít nejvýše %d znaků.', 255);
        $form->addSubmit('add', 'Přidat e-mail')->setOption('class', 'btn btn-outline-primary btn-sm');

        $form->onSuccess[] = function (Form $form, array $values): void {
            if (! $this->authorizator->canEditUnit($this->requireEvent()->getUnitId())) {
                $this->error('Tuto akci nemůžete spravovat.', 403);
            }

            $added = $this->service->addRecipient(
                $this->requireRegistration(),
                (string) $values['email'],
                $values['name'] === null ? null : (string) $values['name'],
                $this->currentUser->getUser()->id,
            );
            $this->flashMessage($added ? 'E-mail byl přidán.' : 'Tento e-mail už je v seznamu.', $added ? 'success' : 'info');
            $this->redirect('this');
        };

        return $form;
    }

    /**
     * @return Multiplier<Form>
     */
    protected function createComponentRecipientActions(): Multiplier
    {
        return new Multiplier(function (string $recipientId): Form {
            $recipient = $this->findRecipient((int) $recipientId);
            $form = $this->forms->create();
            $form->addSubmit('toggle', $recipient->isSelected() ? 'Vypnout' : 'Zapnout')->setOption('class', 'btn btn-sm btn-outline-secondary');
            if ($recipient->getSource() === RecipientSource::Manual) {
                $form->addSubmit('remove', 'Odebrat')->setOption('class', 'btn btn-sm btn-outline-danger');
            }
            $form->onSuccess[] = function (Form $form) use ($recipient): void {
                if (! $this->authorizator->canEditUnit($this->requireEvent()->getUnitId())) {
                    $this->error('Tuto akci nemůžete spravovat.', 403);
                }

                $button = $form->isSubmitted();
                if ($button instanceof SubmitButton && (string) $button->getName() === 'remove') {
                    $this->service->removeRecipient($this->requireRegistration(), $recipient, $this->currentUser->getUser()->id);
                    $this->flashMessage('E-mail byl odebrán.', 'success');
                } else {
                    $recipient->setSelected(! $recipient->isSelected());
                    $this->registrations->save();
                    $this->flashMessage($recipient->isSelected() ? 'Na adresu budou chodit e-maily k akci.' : 'Na adresu už e-maily k akci chodit nebudou.', 'success');
                }
                $this->redirect('this');
            };

            return $form;
        });
    }

    /**
     * @param list<Registration>   $targets
     * @param array<string, mixed> $payload
     */
    private function enqueue(TaskType $type, EmailType $email, array $targets, array $payload, int $userId, string $success, string $nothing): void
    {
        if ($targets === []) {
            $this->flashMessage($nothing, 'warning');

            return;
        }

        $this->queue->enqueue($type, $this->requireEvent(), ['type' => $email->value] + $payload, $targets, $userId);
        $this->flashMessage(\sprintf($success, \count($targets)), 'success');
    }

    /**
     * @return list<Registration>
     */
    private function invitable(): array
    {
        return array_values(array_filter(
            $this->registrations->findByEvent($this->requireEvent()),
            static fn (Registration $registration): bool => $registration->getState()->isPending() && $registration->getInvitedAt() === null && $registration->hasSelectedRecipients(),
        ));
    }

    /**
     * @return list<Registration>
     */
    private function remindable(): array
    {
        $today = new DateTimeImmutable();

        return array_values(array_filter(
            $this->registrations->findByEvent($this->requireEvent(), RegistrationState::Invited),
            fn (Registration $registration): bool => $registration->getInvitedAt() !== null && $registration->hasSelectedRecipients() && ! $this->messages->hasSentToday($registration, EmailType::Reminder, $today),
        ));
    }

    private function personalLink(Event $event, string $token): string
    {
        return $this->link('//:Web:Registration:default', [
            'unitId' => $event->getUnitId(),
            'slug' => $event->getSlug(),
            'token' => $token,
        ]);
    }

    private function findRecipient(int $recipientId): RegistrationRecipient
    {
        foreach ($this->requireRegistration()->getRecipients() as $recipient) {
            if ($recipient->getId() === $recipientId) {
                return $recipient;
            }
        }

        $this->error('Adresa nebyla nalezena.');
    }

    private function loadEvent(int $id): Event
    {
        $event = $this->events->find($id);
        if ($event === null) {
            $this->error('Akce nebyla nalezena.');
        }
        if (! $this->authorizator->canReadUnit($event->getUnitId())) {
            $this->error('K této akci nemáte oprávnění.', 403);
        }

        return $event;
    }

    private function requireEvent(): Event
    {
        return $this->event ?? throw new LogicException('The event is loaded in the action.');
    }

    private function requireRegistration(): Registration
    {
        return $this->registration ?? throw new LogicException('The registration is loaded in the action.');
    }

    private static function automaticLabel(int $active, int $failed): string
    {
        return implode(' · ', array_filter([
            $active > 0 ? 'čeká na odeslání '.$active : null,
            $failed > 0 ? 'neodesláno '.$failed : null,
        ]));
    }
}
