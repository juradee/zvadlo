<?php

declare(strict_types=1);

namespace App\Presentation\Dashboard;

use App\Presentation\SecuredPresenter;
use Nette\Application\UI\Form;

final class DashboardPresenter extends SecuredPresenter
{
    public function renderDefault(): void
    {
        $access = $this->currentUser->getAccess();

        $this->template->account = $this->currentUser->getUser();
        $this->template->role = $this->currentUser->getRole();
        $this->template->readUnits = $access->read;
        $this->template->editUnits = $access->edit;
    }

    protected function createComponentRoleForm(): Form
    {
        $items = [];
        foreach ($this->currentUser->getRoles() as $role) {
            $items[$role->id] = $role->displayName;
        }

        $form = new Form();
        $form->addSelect('role', 'Role ve skautISu', $items)
            ->setDefaultValue($this->currentUser->getRole()->id)
            ->setRequired();
        $form->addSubmit('switch', 'Přepnout roli');
        $form->onSuccess[] = function (Form $form, array $values): void {
            $this->loginService->switchRole((int) $values['role']);
            $this->flashMessage('Role byla přepnuta.', 'success');
            $this->redirect('this');
        };

        return $form;
    }
}
