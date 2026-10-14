<?php

declare(strict_types=1);

namespace App\Presentation\Events\Event;

use App\Model\Event\Event;
use App\Model\Event\EventInput;
use App\Model\Event\EventRepository;
use App\Model\Event\EventService;
use App\Model\Event\EventState;
use App\Model\Event\InvalidStateTransitionException;
use App\Model\Event\MissingMailAccountException;
use App\Model\Event\PublicRegistration;
use App\Model\Event\ScopeLevels;
use App\Model\Mailing\GoogleOAuthRepository;
use App\Model\Mailing\MailAccounts;
use App\Model\Mailing\MailerFactory;
use App\Model\Registration\MemberFingerprints;
use App\Model\Registration\RegistrationRepository;
use App\Model\Registration\RegistrationState;
use App\Model\Skautis\Dto\Camp;
use App\Model\Skautis\EventCodeLists;
use App\Model\Skautis\SkautisGateway;
use App\Model\Skautis\SkautisLinks;
use App\Model\Unit\UnitSettingsRepository;
use App\Model\User\CurrentUserEmail;
use App\Model\User\UnitHierarchy;
use App\Presentation\Accessory\Form\DateInput;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\SecuredPresenter;
use DateTimeImmutable;
use LogicException;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\SubmitButton;

final class EventPresenter extends SecuredPresenter
{
    private ?Event $event = null;

    /** The skautIS camp a new event is being created from. */
    private ?Camp $camp = null;

    public function __construct(
        private readonly EventRepository $events,
        private readonly EventService $service,
        private readonly EventCodeLists $codeLists,
        private readonly RegistrationRepository $registrations,
        private readonly MemberFingerprints $fingerprints,
        private readonly SkautisGateway $skautis,
        private readonly SkautisLinks $links,
        private readonly CurrentUserEmail $currentUserEmail,
        private readonly GoogleOAuthRepository $accounts,
        private readonly MailAccounts $mailAccounts,
        private readonly UnitHierarchy $unitHierarchy,
        private readonly UnitSettingsRepository $unitSettings,
        private readonly MailerFactory $mailers,
        private readonly FormFactory $forms,
    ) {
        parent::__construct();
    }

    public function actionDefault(int $id): void
    {
        $event = $this->loadEvent($id, edit: false);
        $this->event = $event;
    }

    public function renderDefault(): void
    {
        $event = $this->requireEvent();
        $access = $this->currentUser->getAccess();
        $counts = $this->registrations->countByState($event);

        $this->template->event = $event;
        $this->template->unit = $access->read[$event->getUnitId()] ?? null;
        $this->template->canEdit = $this->authorizator->canEditUnit($event->getUnitId());
        $typeId = $event->getSkautisTypeId();
        $this->template->typeName = $event->isCamp() ? 'tábor' : ($typeId === null ? 'neuveden' : ($this->codeLists->getEventTypes()[$typeId] ?? $typeId));
        $this->template->skautisUrl = $this->links->event($event);
        $scopeId = $event->getSkautisScopeId();
        $this->template->scopeName = $event->isCamp() ? 'ze skautISu' : ($scopeId === null ? 'neuveden' : ($this->codeLists->getEventScopes()[$scopeId] ?? $scopeId));
        $this->template->registrationOpen = $event->isRegistrationOpen(new DateTimeImmutable());
        $this->template->counts = $counts;
        $this->template->total = array_sum($counts) - $counts[RegistrationState::Rejected->value];
        $this->template->registrationStates = RegistrationState::active();
        $this->template->publicLink = $event->getPublicRegistration()->isEnabled() ? $this->publicLink($event) : null;
        $this->template->accountRequired = $this->mailers->requiresAccount();
        $this->template->fingerprintCount = $this->fingerprints->countFingerprints($event->getUnitId());
        $this->template->fingerprintsRefreshedAt = $this->fingerprints->getRefreshedAt($event->getUnitId());
    }

    /**
     * Rebuilds the member fingerprints public sign-ups are matched against.
     */
    protected function createComponentFingerprintForm(): Form
    {
        $form = $this->forms->create();
        $form->addSubmit('refresh', 'Obnovit členy pro párování')->setOption('class', 'btn btn-sm btn-outline-secondary');
        $form->onSuccess[] = function (): void {
            $event = $this->requireEvent();
            if (! $this->authorizator->canEditUnit($event->getUnitId())) {
                $this->error('Tuto akci nemůžete spravovat.', 403);
            }
            if ($this->fingerprints->refreshQuietly($event->getUnitId())) {
                $this->flashMessage('Členové pro párování byli načteni ze skautISu.', 'success');
            } else {
                $this->flashMessage('Členy se nepodařilo načíst ze skautISu, zůstává předchozí seznam. Zkuste to prosím za chvíli.', 'warning');
            }
            $this->redirect('this');
        };

        return $form;
    }

    public function actionCreate(): void
    {
        $this->requireEditableUnit();
        $this->setView('form');
    }

    /**
     * Camps are never created by hand: the leader picks one from skautIS and adds only what Zvadlo needs.
     */
    public function actionCreateCamp(?int $tabor = null): void
    {
        $this->requireEditableUnit();

        $year = (int) date('Y');
        $camps = [];
        foreach ([$year, $year + 1] as $y) {
            foreach ($this->skautis->getCamps($y) as $camp) {
                if ($this->authorizator->canEditUnit($camp->unitId)) {
                    $camps[$camp->id] = $camp;
                }
            }
        }
        usort($camps, static fn (Camp $a, Camp $b): int => $a->dateFrom <=> $b->dateFrom);

        if ($tabor === null) {
            $linked = [];
            foreach ($camps as $camp) {
                $linked[$camp->id] = $this->events->findBySkautisCampId($camp->id);
            }
            $this->template->camps = $camps;
            $this->template->linked = $linked;
            $this->setView('campList');

            return;
        }

        foreach ($camps as $camp) {
            if ($camp->id === $tabor) {
                $this->camp = $camp;
            }
        }
        if ($this->camp === null) {
            $this->error('Tábor nebyl ve skautISu nalezen mezi tábory vašich jednotek.');
        }
        if ($this->events->findBySkautisCampId($tabor) !== null) {
            $this->flashMessage('Tento tábor už ve Zvadle je.', 'warning');
            $this->redirect('createCamp');
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
        $this->template->camp = $this->camp;
    }

    protected function createComponentEventForm(): Form
    {
        $event = $this->event;
        $camp = $this->camp;
        $isCamp = $camp !== null || ($event?->isCamp() ?? false);
        $access = $this->currentUser->getAccess();

        $form = $this->forms->create();

        $defaultUnitId = null;
        if ($event === null && $camp === null) {
            $units = [];
            foreach ($access->edit as $unit) {
                $units[$unit->id] = $unit->displayName;
            }
            $defaultUnitId = isset($units[$this->currentUser->getRole()->unitId]) ? $this->currentUser->getRole()->unitId : array_key_first($units);
            $form->addSelect('unitId', 'Jednotka', $units)
                ->setDefaultValue($defaultUnitId)
                ->setRequired();
        }
        $unitTypes = array_map(static fn ($unit): string => $unit->typeId, $access->read);

        $name = $form->addText('name', 'Název akce')
            ->setRequired('Zadejte název akce.')
            ->addRule($form::MaxLength, 'Název může mít nejvýše %d znaků.', 255);
        if (! $isCamp) {
            // A stored id skautIS no longer lists stays empty, so the leader picks again instead of the form failing.
            $form->addSelect('skautisTypeId', 'Typ akce', $this->codeLists->getEventTypes())
                ->checkDefaultValue(false)
                ->setPrompt('Vyberte')
                ->setRequired('Vyberte typ akce.');
            // A scope never below the organising unit, preset to the unit's own level; for a new event both follow the chosen unit.
            $scopes = $this->codeLists->getEventScopes();
            $allowed = [];
            $defaultScopes = [];
            foreach ($event === null ? array_keys($access->edit) : [$event->getUnitId()] as $unitId) {
                $allowed[$unitId] = ScopeLevels::allowedFor($unitTypes[$unitId] ?? null, $scopes);
                $defaultScopes[$unitId] = ScopeLevels::defaultFor($unitTypes[$unitId] ?? null, $scopes);
            }
            $scopeItems = $event === null ? $scopes : array_intersect_key($scopes, array_flip([...$allowed[$event->getUnitId()], (string) $event->getSkautisScopeId()]));
            $scope = $form->addSelect('skautisScopeId', 'Rozsah akce', $scopeItems)
                ->checkDefaultValue(false)
                ->setPrompt('Vyberte')
                ->setRequired('Vyberte rozsah akce.')
                ->setOption('description', 'Rozsah nemůže být menší než pořádající jednotka; družina může pořádat i akci pro celé středisko.')
                ->setHtmlAttribute('data-scopes-by-unit', json_encode($allowed, \JSON_THROW_ON_ERROR))
                ->setHtmlAttribute('data-default-scope-by-unit', json_encode($defaultScopes, \JSON_THROW_ON_ERROR));
            if ($defaultUnitId !== null) {
                $scope->setDefaultValue($defaultScopes[$defaultUnitId] ?? null);
            }
            $form->onValidate[] = static function (Form $form) use ($scope, $allowed, $event): void {
                $values = $form->getValues();
                $unitId = $event?->getUnitId() ?? (int) ($values['unitId'] ?? 0);
                $chosen = (string) $values['skautisScopeId'];
                if ($chosen !== '' && $chosen !== $event?->getSkautisScopeId() && ! \in_array($chosen, $allowed[$unitId] ?? [], true)) {
                    $scope->addError('Tento rozsah je menší než pořádající jednotka, vyberte jiný.');
                }
            };
        }
        $dateFrom = DateInput::add($form, 'dateFrom', 'Začátek')
            ->setRequired('Zadejte začátek akce.');
        $dateTo = DateInput::add($form, 'dateTo', 'Konec')
            ->setOption('description', 'Prázdné = jednodenní akce, konec je stejný den jako začátek.');
        $place = $form->addText('place', 'Místo')
            ->setNullable()
            ->addRule($form::MaxLength, 'Místo může mít nejvýše %d znaků.', 255);
        if ($isCamp) {
            foreach ([$name, $dateFrom, $dateTo, $place] as $control) {
                $control->setDisabled()->setOption('description', 'Přebírá se ze skautISu.');
            }
        }
        $form->addTextArea('description', 'Popis', null, 6)
            ->setNullable()
            ->setOption('description', 'Zobrazí se na miniwebu a v e-mailech. Lze použít Markdown.');
        $deadline = DateInput::add($form, 'registrationDeadline', 'Uzávěrka přihlášek')
            ->setOption('description', 'Do tohoto dne včetně lze přihlášku podat, změnit i zrušit. Prázdné = do konce akce. Dřív přihlašování ukončíte uzavřením akce.');
        // The notice is about the deadline, so it shows only with one.
        $deadline->addCondition($form::Filled)->toggle('lock-notice-days');
        $capacity = $form->addInteger('capacity', 'Kapacita')
            ->setNullable()
            ->addRule($form::Min, 'Kapacita musí být kladná.', 1)
            ->setOption('description', 'Prázdné = bez omezení.');
        $capacity->addCondition($form::Filled)->toggle('waitlist');
        $form->addCheckbox('waitlistEnabled', 'Čekací listina (náhradníci)')
            ->setDefaultValue(true)
            ->setOption('id', 'waitlist')
            ->setOption('description', 'Po naplnění kapacity se další přihlásí jako náhradníci. Když se účastník sám odhlásí, místo dostane první náhradník a přijde mu e-mail. Bez čekací listiny plná akce další přihlášky nepřijme.');
        $form->addInteger('price', 'Cena (Kč)')
            ->setNullable()
            ->addRule($form::Min, 'Cena nemůže být záporná.', 0)
            ->setOption('description', 'Jen informativní; platby systém neřeší.');
        $form->addInteger('lockNoticeDays', 'Upozornění na uzávěrku (dní předem)')
            ->setDefaultValue(2)
            ->setNullable()
            ->setOption('id', 'lock-notice-days')
            ->setOption('description', 'Přihlášeným přijde upozornění, že brzy nepůjde přihlášku měnit. 0 = neposílat.')
            ->addConditionOn($deadline, $form::Filled)
                ->setRequired('Zadejte počet dní upozornění, 0 = neposílat.')
                ->addRule($form::Range, 'Zadejte 0 až 30 dní.', [0, 30]);
        $accounts = [];
        foreach ($this->mailAccounts->availableForAny(array_keys($access->edit)) as $id => $account) {
            $accounts[$id] = $account->getEmail().' ('.($this->unitHierarchy->unit($account->getUnitId())->displayName ?? $account->getUnitId()).')';
        }
        // An event keeps the account it already has, even one its unit may no longer choose.
        $current = $this->event?->getMailAccount();
        if ($current !== null && ! isset($accounts[$current->getId()])) {
            $accounts[$current->getId()] = $current->getEmail().' (dosavadní účet)';
        }
        $mailAccount = $form->addSelect('mailAccount', 'Odesílací e-mailový účet', $accounts)
            ->setPrompt($accounts === [] ? 'Žádný Gmail účet není propojený' : 'Vyberte')
            ->setOption('description', 'Gmail účet jednotky, ze kterého odchází pozvánky a potvrzení; nebo účet nadřízené jednotky, pokud ho podřízeným povolila. Propojuje se v nastavení jednotky.');
        $form->addSelect('publicRegistration', 'Veřejné přihlašování', [
            PublicRegistration::None->value => PublicRegistration::None->label(),
            PublicRegistration::Open->value => PublicRegistration::Open->label(),
            PublicRegistration::Skautis->value => PublicRegistration::Skautis->label(),
        ])
            ->setRequired()
            ->setOption('description', 'S veřejným odkazem se přihlásí kdokoli bez pozvánky; členové jednotky se spárují podle jména a data narození. „Jen přes skautIS“ pustí kohokoli s účtem ve skautISu, i z jiné jednotky, bez účtu se přihlásit nejde.');
        $form->addText('contactName', 'Kontaktní osoba')
            ->setRequired('Zadejte kontaktní osobu.')
            ->addRule($form::MaxLength, 'Jméno může mít nejvýše %d znaků.', 255);
        $form->addEmail('contactEmail', 'E-mail kontaktní osoby')
            ->setRequired('Zadejte e-mail kontaktní osoby.')
            ->setOption('description', 'Na tuto adresu odpovídají rodiče (Reply-To).');
        $form->addText('contactPhone', 'Telefon kontaktní osoby')
            ->setNullable()
            ->addRule($form::MaxLength, 'Telefon může mít nejvýše %d znaků.', 50);
        $form->addSubmit('save', $event === null ? ($camp === null ? 'Založit akci' : 'Založit tábor') : 'Uložit změny')
            ->setHtmlAttribute('data-test', 'event-submit');

        if ($event !== null) {
            $form->setDefaults([
                'name' => $event->getName(),
                'skautisTypeId' => $event->getSkautisTypeId(),
                'skautisScopeId' => $event->getSkautisScopeId(),
                'dateFrom' => $event->getDateFrom(),
                'dateTo' => $event->getDateTo(),
                'place' => $event->getPlace(),
                'description' => $event->getDescription(),
                'registrationDeadline' => $event->getRegistrationDeadline(),
                'lockNoticeDays' => $event->getLockNoticeDays(),
                'waitlistEnabled' => $event->isWaitlistEnabled(),
                'mailAccount' => $event->getMailAccount()?->getId(),
                'capacity' => $event->getCapacity(),
                'price' => $event->getPrice(),
                'publicRegistration' => $event->getPublicRegistration()->value,
                'contactName' => $event->getContactName(),
                'contactEmail' => $event->getContactEmail(),
                'contactPhone' => $event->getContactPhone(),
            ]);
        } else {
            $settings = $this->unitSettings->get($camp->unitId ?? $this->currentUser->getRole()->unitId);
            $defaultAccount = $settings->getDefaultMailAccount()?->getId() ?? (\count($accounts) === 1 ? (string) array_key_first($accounts) : null);
            $form->setDefaults([
                'contactName' => $this->currentUser->getUser()->displayName,
                'contactEmail' => $this->currentUserEmail->main(),
                'name' => $camp?->name,
                'dateFrom' => $camp?->dateFrom,
                'dateTo' => $camp?->dateTo,
                'place' => $camp === null || $camp->location === '' ? null : $camp->location,
                'mailAccount' => isset($accounts[$defaultAccount ?? '']) ? $defaultAccount : null,
            ]);
        }

        $form->onValidate[] = static function (Form $form) use ($dateTo, $deadline, $isCamp): void {
            $values = $form->getValues();
            if (! $isCamp && $values['dateFrom'] instanceof DateTimeImmutable && $values['dateTo'] instanceof DateTimeImmutable && $values['dateTo'] < $values['dateFrom']) {
                $dateTo->addError('Konec akce nemůže být před jejím začátkem.');
            }
            $end = $values['dateTo'] ?? $values['dateFrom'] ?? null;
            if ($values['registrationDeadline'] instanceof DateTimeImmutable && $end instanceof DateTimeImmutable && $values['registrationDeadline'] > $end) {
                $deadline->addError('Uzávěrka přihlášek nemůže být po konci akce.');
            }
        };

        $form->onSuccess[] = function (Form $form, array $values) use ($mailAccount): void {
            $input = $this->inputFromValues($values);
            $userId = $this->currentUser->getUser()->id;
            $account = $values['mailAccount'] === null ? null : $this->accounts->find((string) $values['mailAccount']);
            $eventUnitId = $this->camp->unitId ?? $this->event?->getUnitId() ?? (int) $values['unitId'];
            $unchanged = $account !== null && $account === $this->event?->getMailAccount();
            if ($account !== null && ! $unchanged && ! $this->mailAccounts->isAvailableFor($account, $eventUnitId)) {
                $mailAccount->addError('Tento účet jednotka akce nesmí používat. Vyberte účet jednotky nebo účet, který jí nadřízená jednotka povolila.');

                return;
            }

            if ($this->camp !== null) {
                $event = $this->service->createFromCamp($this->camp, $input, $userId, $account);
                $this->flashMessage('Tábor byl založen podle skautISu. Doplňte vlastní pole a potom ho otevřete.', 'success');
            } elseif ($this->event === null) {
                $unitId = (int) $values['unitId'];
                if (! $this->authorizator->canEditUnit($unitId)) {
                    $this->error('Tuto jednotku nemůžete spravovat.', 403);
                }
                $event = $this->service->create($unitId, $input, $userId, $account);
                $this->flashMessage('Akce byla založena. Doplňte vlastní pole a potom ji otevřete.', 'success');
            } else {
                $event = $this->event;
                $wasPublic = $event->getPublicRegistration()->isEnabled();
                $this->service->update($event, $input, $account);
                $this->flashMessage('Změny byly uloženy.', 'success');
            }

            // Members rarely change: the fingerprints are built when an event with public sign-up is created or
            // when public sign-up is switched on; the button on the event page rebuilds them on demand.
            $switchedOn = $this->event === null || ! ($wasPublic ?? false);
            if ($event->getPublicRegistration()->isEnabled() && $switchedOn && ! $this->fingerprints->refreshQuietly($event->getUnitId())) {
                $this->flashMessage('Členy pro párování veřejných přihlášek se nepodařilo načíst ze skautISu. Načtěte je tlačítkem v detailu akce.', 'warning');
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
            } catch (InvalidStateTransitionException|MissingMailAccountException $exception) {
                $this->flashMessage($exception->getMessage(), 'danger');
            }

            $this->redirect('this');
        };

        return $form;
    }

    /**
     * Pulls the camp's name, dates and place from skautIS again.
     */
    protected function createComponentCampRefreshForm(): Form
    {
        $form = $this->forms->create();
        $form->addSubmit('refresh', 'Obnovit ze skautISu')->setOption('class', 'btn btn-sm btn-outline-secondary');
        $form->onSuccess[] = function (): void {
            $event = $this->requireEvent();
            if (! $this->authorizator->canEditUnit($event->getUnitId())) {
                $this->error('Tuto akci nemůžete spravovat.', 403);
            }

            $campId = $event->getSkautisCampId();
            foreach ([(int) $event->getDateFrom()->format('Y')] as $year) {
                foreach ($this->skautis->getCamps($year) as $camp) {
                    if ($camp->id === $campId) {
                        $this->service->refreshFromCamp($event, $camp);
                        $this->flashMessage('Údaje tábora byly obnoveny ze skautISu.', 'success');
                        $this->redirect('this');
                    }
                }
            }

            $this->flashMessage('Tábor už ve skautISu nebyl nalezen.', 'warning');
            $this->redirect('this');
        };

        return $form;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function inputFromValues(array $values): EventInput
    {
        $source = $this->event ?? $this->camp;
        $dateFrom = $values['dateFrom'] ?? null;
        // No end date means a one-day event.
        $dateTo = $values['dateTo'] ?? $dateFrom;
        $deadline = $values['registrationDeadline'];

        // Disabled controls of a camp send nothing; the values come from skautIS.
        if ($source instanceof Camp) {
            [$name, $dateFrom, $dateTo, $place] = [$source->name, $source->dateFrom, $source->dateTo, $source->location === '' ? null : $source->location];
        } elseif ($source instanceof Event && $source->isCamp()) {
            [$name, $dateFrom, $dateTo, $place] = [$source->getName(), $source->getDateFrom(), $source->getDateTo(), $source->getPlace()];
        } else {
            $name = (string) $values['name'];
            $place = $values['place'] === null ? null : (string) $values['place'];
        }
        \assert($dateFrom instanceof DateTimeImmutable && $dateTo instanceof DateTimeImmutable);

        return new EventInput(
            name: $name,
            skautisTypeId: isset($values['skautisTypeId']) ? (string) $values['skautisTypeId'] : null,
            skautisScopeId: isset($values['skautisScopeId']) ? (string) $values['skautisScopeId'] : null,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            place: $place,
            description: $values['description'] === null ? null : (string) $values['description'],
            registrationDeadline: $deadline instanceof DateTimeImmutable ? $deadline : null,
            capacity: $values['capacity'] === null ? null : (int) $values['capacity'],
            price: $values['price'] === null ? null : (int) $values['price'],
            contactName: (string) $values['contactName'],
            contactEmail: (string) $values['contactEmail'],
            contactPhone: $values['contactPhone'] === null ? null : (string) $values['contactPhone'],
            publicRegistration: PublicRegistration::from((string) $values['publicRegistration']),
            lockNoticeDays: (int) $values['lockNoticeDays'],
            waitlistEnabled: (bool) $values['waitlistEnabled'],
        );
    }

    private function publicLink(Event $event): string
    {
        return $this->link('//:Web:Public:default', ['unitId' => $event->getUnitId(), 'slug' => $event->getSlug(), 't' => $event->getWebToken()]);
    }

    private function requireEditableUnit(): void
    {
        if ($this->currentUser->getAccess()->edit === []) {
            $this->error('Vaše role neumožňuje zakládat akce. Zkuste přepnout roli.', 403);
        }
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
