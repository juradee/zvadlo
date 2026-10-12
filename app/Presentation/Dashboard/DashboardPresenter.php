<?php

declare(strict_types=1);

namespace App\Presentation\Dashboard;

use App\Model\Event\EventRepository;
use App\Model\User\UserPreferenceRepository;
use App\Presentation\SecuredPresenter;
use DateTimeImmutable;
use DateTimeImmutable as Now;
use Nette\Application\UI\Form;

final class DashboardPresenter extends SecuredPresenter
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly UserPreferenceRepository $userPreferences,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $access = $this->currentUser->getAccess();

        $this->template->account = $this->currentUser->getUser();
        $this->template->role = $this->currentUser->getRole();
        $this->template->readUnits = $access->read;
        $this->template->editUnits = $access->edit;
        $this->template->upcoming = $this->events->findUpcoming(array_keys($access->read), new DateTimeImmutable(), 5);
    }

    protected function createComponentRoleForm(): Form
    {
        $items = [];
        foreach ($this->currentUser->getRoles() as $role) {
            $items[$role->id] = $role->displayName;
        }

        $preference = $this->userPreferences->get($this->currentUser->getUser()->id);

        $form = new Form();
        $form->addSelect('role', 'Role ve skautISu', $items)
            ->setDefaultValue($this->currentUser->getRole()->id)
            ->setRequired();
        $form->addCheckbox('remember', 'Pamatovat si roli pro příští přihlášení')
            ->setDefaultValue($preference->shouldRememberRole());
        $form->addCheckbox('extend', 'Prodlužovat přihlášení, dokud mám Zvadlo otevřené')
            ->setDefaultValue($preference->shouldExtendLogin());
        $form->addSubmit('switch', 'Přepnout roli');
        $form->onSuccess[] = function (Form $form, array $values): void {
            $preference = $this->userPreferences->get($this->currentUser->getUser()->id);
            $preference->edit((bool) $values['extend'], (bool) $values['remember'], new Now());
            $this->userPreferences->save($preference);
            $this->loginService->switchRole((int) $values['role']);
            $this->flashMessage('Role byla přepnuta.', 'success');
            $this->redirect('this');
        };

        return $form;
    }
}
