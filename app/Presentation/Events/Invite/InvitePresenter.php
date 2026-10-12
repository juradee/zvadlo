<?php

declare(strict_types=1);

namespace App\Presentation\Events\Invite;

use App\Model\Event\Event;
use App\Model\Event\EventRepository;
use App\Model\Event\EventState;
use App\Model\Registration\FoundAddress;
use App\Model\Registration\MemberAddresses;
use App\Model\Registration\RecipientSource;
use App\Model\Registration\RegistrationRepository;
use App\Model\Registration\RegistrationService;
use App\Model\Skautis\Dto\Person;
use App\Model\Skautis\MemberDirectory;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\SecuredPresenter;
use DateTimeImmutable;
use LogicException;
use Nette\Application\UI\Form;
use Nette\Utils\Strings;

/**
 * Choosing whom to invite: members of the unit tree from skautIS with the e-mails each invitation goes to
 * (as when adding payments in h.skauting), or a non-member by hand.
 */
final class InvitePresenter extends SecuredPresenter
{
    private ?Event $event = null;

    /** @var list<Person> */
    private array $members = [];

    /** @var list<int> */
    private array $invitedPersonIds = [];

    public function __construct(
        private readonly EventRepository $events,
        private readonly MemberDirectory $directory,
        private readonly RegistrationRepository $registrations,
        private readonly RegistrationService $service,
        private readonly MemberAddresses $addresses,
        private readonly FormFactory $forms,
    ) {
        parent::__construct();
    }

    public function actionDefault(int $id, bool $obnovit = false): void
    {
        $event = $this->events->find($id);
        if ($event === null) {
            $this->error('Akce nebyla nalezena.');
        }
        if (! $this->authorizator->canEditUnit($event->getUnitId())) {
            $this->error('K této akci nemáte oprávnění.', 403);
        }
        if ($event->getState() === EventState::Archived) {
            $this->flashMessage('Na archivovanou akci už nelze zvát.', 'warning');
            $this->redirect(':Events:Event:default', ['id' => $id]);
        }

        if ($obnovit) {
            $this->directory->getMembers($event->getUnitId(), refresh: true);
            $this->flashMessage('Seznam členů byl znovu načten ze skautISu.', 'info');
            $this->redirect('default', ['id' => $id]);
        }

        $this->event = $event;
        $this->members = $this->directory->getMembers($event->getUnitId());
        $this->invitedPersonIds = $this->registrations->findInvitedPersonIds($event);
    }

    public function renderDefault(?string $hledat = null, ?int $jednotka = null): void
    {
        $search = $hledat === null ? '' : Strings::lower(trim($hledat));
        $members = array_values(array_filter($this->members, static function (Person $person) use ($search, $jednotka): bool {
            if ($jednotka !== null && $person->unitId !== $jednotka) {
                return false;
            }

            return $search === '' || str_contains(Strings::lower($person->getDisplayName()), $search);
        }));

        $this->template->event = $this->requireEvent();
        $this->template->members = $members;
        $this->template->totalMembers = \count($this->members);
        $this->template->invited = array_flip($this->invitedPersonIds);
        $this->template->units = $this->currentUser->getAccess()->read;
        $this->template->search = $hledat ?? '';
        $this->template->unitFilter = $jednotka;
        $this->template->now = new DateTimeImmutable();
        $this->template->addressTypes = RecipientSource::bulkSelectable();
        // The e-mails are loaded by the page in AJAX batches; members already in the event need none.
        $this->template->addressPersonIds = array_values(array_filter(
            array_map(static fn (Person $person): int => $person->id, $members),
            fn (int $id): bool => ! \in_array($id, $this->invitedPersonIds, true),
        ));
    }

    /**
     * AJAX: the e-mails of one batch of members (comma-separated person ids, at most 10) with the default choice.
     */
    public function handleAddresses(string $ids = ''): void
    {
        $wanted = \array_slice(array_map(intval(...), array_filter(explode(',', $ids), is_numeric(...))), 0, 10);
        $now = new DateTimeImmutable();
        $result = [];
        foreach ($this->members as $person) {
            if (\in_array($person->id, $wanted, true) && ! \in_array($person->id, $this->invitedPersonIds, true)) {
                $result[(string) $person->id] = array_map(static fn (FoundAddress $address): array => [
                    'email' => $address->email,
                    'label' => $address->source->label(),
                    'source' => $address->source->value,
                    'selected' => $address->selected,
                ], $this->addresses->find($person->id, $person->birthday, $now)->addresses);
            }
        }

        $this->sendJson(['persons' => (object) $result]);
    }

    protected function createComponentInviteForm(): Form
    {
        $items = [];
        foreach ($this->members as $person) {
            $items[$person->id] = $person->getDisplayName();
        }

        $form = $this->forms->create();
        $form->addCheckboxList('members', 'Členové', $items)
            ->setDisabled($this->invitedPersonIds)
            ->setRequired('Vyberte alespoň jednoho člena.');
        $form->addSubmit('invite', 'Přidat vybrané');

        $form->onSuccess[] = function (Form $form, array $values): void {
            // The e-mail checkboxes are drawn by the page once the addresses arrive (shown[] lists those rows);
            // a member whose row never got them is invited with the default choice.
            $post = $this->getHttpRequest()->getPost();
            $shown = array_map(intval(...), \is_array($post['shown'] ?? null) ? $post['shown'] : []);
            $emails = \is_array($post['emails'] ?? null) ? $post['emails'] : [];
            $chosen = [];
            foreach ((array) $values['members'] as $id) {
                $id = (int) $id;
                $chosen[$id] = \in_array($id, $shown, true)
                    ? array_values(array_filter(\is_array($emails[$id] ?? null) ? $emails[$id] : [], \is_string(...)))
                    : null;
            }
            $created = $this->service->inviteMembers($this->requireEvent(), $chosen, $this->currentUser->getUser()->id);
            $this->flashMessage(\sprintf('Přidáno %d členů.', \count($created)), 'success');
            $this->redirect(':Events:Registration:default', ['id' => $this->requireEvent()->getId()]);
        };

        return $form;
    }

    protected function createComponentNonMemberForm(): Form
    {
        $form = $this->forms->create();
        $form->addEmail('email', 'E-mail')
            ->setRequired('Zadejte e-mail.')
            ->setOption('description', 'Zvadlo přijde na tuto adresu a účastník si jí přihlášku odemkne; ostatní údaje vyplní sám.');
        $form->addText('firstName', 'Jméno (nepovinné)')
            ->setNullable()
            ->addRule($form::MaxLength, 'Jméno může mít nejvýše %d znaků.', 100);
        $form->addText('lastName', 'Příjmení (nepovinné)')
            ->setNullable()
            ->addRule($form::MaxLength, 'Příjmení může mít nejvýše %d znaků.', 100);
        $form->addSubmit('invite', 'Přidat nečlena')->setOption('class', 'btn btn-outline-primary');

        $form->onSuccess[] = function (Form $form, array $values): void {
            $registration = $this->service->inviteNonMember(
                $this->requireEvent(),
                (string) $values['email'],
                $values['firstName'] === null ? null : (string) $values['firstName'],
                $values['lastName'] === null ? null : (string) $values['lastName'],
                $this->currentUser->getUser()->id,
            );
            $this->flashMessage(\sprintf('Nečlen %s byl přidán.', $registration->getDisplayName()), 'success');
            $this->redirect(':Events:Registration:default', ['id' => $this->requireEvent()->getId()]);
        };

        return $form;
    }

    private function requireEvent(): Event
    {
        return $this->event ?? throw new LogicException('The event is loaded in the action.');
    }
}
