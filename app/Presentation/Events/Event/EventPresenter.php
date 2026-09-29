<?php

declare(strict_types=1);

namespace App\Presentation\Events\Event;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventRepository;
use App\Model\Event\EventService;
use App\Model\Event\EventState;
use App\Model\Event\InvalidStateTransitionException;
use App\Model\Skautis\EventCodeLists;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\SecuredPresenter;
use DateTimeImmutable;
use LogicException;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\SubmitButton;

final class EventPresenter extends SecuredPresenter
{
    private ?Event $event = null;

    public function __construct(
        private readonly EventRepository $events,
        private readonly EventService $service,
        private readonly EventCodeLists $codeLists,
        private readonly FormFactory $forms,
    ) {
        parent::__construct();
    }

    public function actionDefault(int $id): void
    {
        $this->event = $this->loadEvent($id, edit: false);
    }

    public function renderDefault(): void
    {
        $event = $this->requireEvent();
        $access = $this->currentUser->getAccess();

        $this->template->event = $event;
        $this->template->unit = $access->read[$event->getUnitId()] ?? null;
        $this->template->canEdit = $this->authorizator->canEditUnit($event->getUnitId());
        $this->template->typeName = $this->codeLists->getEventTypes()[$event->getSkautisTypeId()] ?? $event->getSkautisTypeId();
        $this->template->scopeName = $this->codeLists->getEventScopes()[$event->getSkautisScopeId()] ?? $event->getSkautisScopeId();
        $this->template->editLocked = $event->isEditLocked(new DateTimeImmutable());
    }

    public function actionCreate(): void
    {
        if ($this->currentUser->getAccess()->edit === []) {
            $this->error('Vaše role neumožňuje zakládat akce. Zkuste přepnout roli.', 403);
        }

        $this->setView('form');
    }

    public function actionEdit(int $id): void
    {
        $this->event = $this->loadEvent($id, edit: true);
        $this->setView('form');
    }

    public function renderForm(): void
    {
        $this->template->event = $this->event;
    }

    protected function createComponentEventForm(): Form
    {
        $event = $this->event;
        $access = $this->currentUser->getAccess();

        $form = $this->forms->create();

        if ($event === null) {
            $units = [];
            foreach ($access->edit as $unit) {
                $units[$unit->id] = $unit->displayName;
            }
            $form->addSelect('unitId', 'Jednotka', $units)
                ->setDefaultValue(isset($units[$this->currentUser->getRole()->unitId]) ? $this->currentUser->getRole()->unitId : array_key_first($units))
                ->setRequired();
        }

        $form->addText('name', 'Název akce')
            ->setRequired('Zadejte název akce.')
            ->addRule($form::MaxLength, 'Název může mít nejvýše %d znaků.', 255);
        $form->addSelect('skautisTypeId', 'Typ akce', $this->codeLists->getEventTypes())
            ->setPrompt('Vyberte')
            ->setRequired('Vyberte typ akce.');
        $form->addSelect('skautisScopeId', 'Rozsah akce', $this->codeLists->getEventScopes())
            ->setPrompt('Vyberte')
            ->setRequired('Vyberte rozsah akce.');
        $form->addCheckbox('isCamp', 'Jde o tábor (zakládá se ve skautISu, Zvadlo do něj jen propisuje účastníky)');
        $form->addDate('dateFrom', 'Začátek')
            ->setRequired('Zadejte začátek akce.');
        $dateTo = $form->addDate('dateTo', 'Konec')
            ->setRequired('Zadejte konec akce.');
        $form->addText('place', 'Místo')
            ->setNullable()
            ->addRule($form::MaxLength, 'Místo může mít nejvýše %d znaků.', 255);
        $form->addTextArea('description', 'Popis', null, 6)
            ->setNullable()
            ->setOption('description', 'Zobrazí se na miniwebu a v e-mailech. Lze použít Markdown.');
        $deadline = $form->addDate('registrationDeadline', 'Uzávěrka přihlášek')
            ->setOption('description', 'Po tomto dni nelze podat novou přihlášku. Prázdné = do začátku akce.');
        $form->addInteger('editLockDays', 'Zámek úprav (dní před akcí)')
            ->setDefaultValue(3)
            ->setRequired()
            ->addRule($form::Range, 'Zadejte 0 až 60 dní.', [0, 60])
            ->setOption('description', 'Tolik dní před začátkem účastníci už nemohou přihlášku měnit ani rušit.');
        $form->addInteger('capacity', 'Kapacita')
            ->setNullable()
            ->addRule($form::Min, 'Kapacita musí být kladná.', 1)
            ->setOption('description', 'Prázdné = bez omezení. Po naplnění jdou další přihlášky na čekací listinu.');
        $form->addInteger('price', 'Cena (Kč)')
            ->setNullable()
            ->addRule($form::Min, 'Cena nemůže být záporná.', 0)
            ->setOption('description', 'Jen informativní; platby systém neřeší.');
        $form->addText('contactName', 'Kontaktní osoba')
            ->setRequired('Zadejte kontaktní osobu.')
            ->addRule($form::MaxLength, 'Jméno může mít nejvýše %d znaků.', 255);
        $form->addEmail('contactEmail', 'E-mail kontaktní osoby')
            ->setRequired('Zadejte e-mail kontaktní osoby.')
            ->setOption('description', 'Na tuto adresu odpovídají rodiče (Reply-To).');
        $form->addText('contactPhone', 'Telefon kontaktní osoby')
            ->setNullable()
            ->addRule($form::MaxLength, 'Telefon může mít nejvýše %d znaků.', 50);
        $form->addSubmit('save', $event === null ? 'Založit akci' : 'Uložit změny');

        if ($event !== null) {
            $form->setDefaults([
                'name' => $event->getName(),
                'skautisTypeId' => $event->getSkautisTypeId(),
                'skautisScopeId' => $event->getSkautisScopeId(),
                'isCamp' => $event->isCamp(),
                'dateFrom' => $event->getDateFrom(),
                'dateTo' => $event->getDateTo(),
                'place' => $event->getPlace(),
                'description' => $event->getDescription(),
                'registrationDeadline' => $event->getRegistrationDeadline(),
                'editLockDays' => $event->getEditLockDays(),
                'capacity' => $event->getCapacity(),
                'price' => $event->getPrice(),
                'contactName' => $event->getContactName(),
                'contactEmail' => $event->getContactEmail(),
                'contactPhone' => $event->getContactPhone(),
            ]);
        } else {
            $form->setDefaults(['contactName' => $this->currentUser->getUser()->displayName]);
        }

        $form->onValidate[] = static function (Form $form) use ($dateTo, $deadline): void {
            $values = $form->getValues();
            if ($values['dateFrom'] instanceof DateTimeImmutable && $values['dateTo'] instanceof DateTimeImmutable && $values['dateTo'] < $values['dateFrom']) {
                $dateTo->addError('Konec akce nemůže být před jejím začátkem.');
            }
            if ($values['registrationDeadline'] instanceof DateTimeImmutable && $values['dateTo'] instanceof DateTimeImmutable && $values['registrationDeadline'] > $values['dateTo']) {
                $deadline->addError('Uzávěrka přihlášek nemůže být po konci akce.');
            }
        };

        $form->onSuccess[] = function (Form $form, array $values): void {
            $input = $this->inputFromValues($values);

            if ($this->event === null) {
                $unitId = (int) $values['unitId'];
                if (! $this->authorizator->canEditUnit($unitId)) {
                    $this->error('Tuto jednotku nemůžete spravovat.', 403);
                }
                $event = $this->service->create($unitId, $input, $this->currentUser->getUser()->id);
                $this->flashMessage('Akce byla založena. Doplňte vlastní pole a potom ji otevřete.', 'success');
            } else {
                $event = $this->event;
                $this->service->update($event, $input);
                $this->flashMessage('Změny byly uloženy.', 'success');
            }

            $this->redirect('default', ['id' => $event->getId()]);
        };

        return $form;
    }

    protected function createComponentStateForm(): Form
    {
        $event = $this->requireEvent();
        $form = $this->forms->create();

        $transitions = [
            EventState::Open->value => ['Otevřít přihlašování', 'btn btn-success'],
            EventState::Closed->value => ['Uzavřít přihlašování', 'btn btn-warning'],
            EventState::Archived->value => ['Archivovat', 'btn btn-outline-dark'],
        ];
        foreach ($transitions as $state => [$label, $class]) {
            if ($event->getState()->canTransitionTo(EventState::from($state))) {
                $form->addSubmit($state, $label)->setOption('class', $class);
            }
        }

        $form->onSuccess[] = function (Form $form): void {
            if (! $this->authorizator->canEditUnit($this->requireEvent()->getUnitId())) {
                $this->error('Tuto akci nemůžete spravovat.', 403);
            }

            $button = $form->isSubmitted();
            if (! $button instanceof SubmitButton) {
                return;
            }

            try {
                $this->service->changeState($this->requireEvent(), EventState::from((string) $button->getName()));
                $this->flashMessage('Stav akce byl změněn.', 'success');
            } catch (InvalidStateTransitionException $exception) {
                $this->flashMessage($exception->getMessage(), 'danger');
            }

            $this->redirect('this');
        };

        return $form;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function inputFromValues(array $values): EventInput
    {
        \assert($values['dateFrom'] instanceof DateTimeImmutable && $values['dateTo'] instanceof DateTimeImmutable);
        $deadline = $values['registrationDeadline'];

        return new EventInput(
            name: (string) $values['name'],
            skautisTypeId: (string) $values['skautisTypeId'],
            skautisScopeId: (string) $values['skautisScopeId'],
            isCamp: (bool) $values['isCamp'],
            dateFrom: $values['dateFrom'],
            dateTo: $values['dateTo'],
            place: $values['place'] === null ? null : (string) $values['place'],
            description: $values['description'] === null ? null : (string) $values['description'],
            registrationDeadline: $deadline instanceof DateTimeImmutable ? $deadline : null,
            editLockDays: (int) $values['editLockDays'],
            capacity: $values['capacity'] === null ? null : (int) $values['capacity'],
            price: $values['price'] === null ? null : (int) $values['price'],
            contactName: (string) $values['contactName'],
            contactEmail: (string) $values['contactEmail'],
            contactPhone: $values['contactPhone'] === null ? null : (string) $values['contactPhone'],
        );
    }

    private function loadEvent(int $id, bool $edit): Event
    {
        $event = $this->events->find($id);
        if ($event === null) {
            $this->error('Akce nebyla nalezena.');
        }

        $allowed = $edit
            ? $this->authorizator->canEditUnit($event->getUnitId())
            : $this->authorizator->canReadUnit($event->getUnitId());
        if (! $allowed) {
            $this->error('K této akci nemáte oprávnění. Zkuste přepnout roli ve skautISu.', 403);
        }

        return $event;
    }

    private function requireEvent(): Event
    {
        return $this->event ?? throw new LogicException('The event is loaded in the action.');
    }
}
