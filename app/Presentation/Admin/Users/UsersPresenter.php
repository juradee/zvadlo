<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Users;

use App\Model\Admin\SystemRole;
use App\Model\Admin\SystemUserRoleRepository;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\Admin\AdminBasePresenter;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;

/**
 * System roles per skautIS user id, as in h.skauting.
 */
final class UsersPresenter extends AdminBasePresenter
{
    private ?int $editedUserId = null;

    public function __construct(
        private readonly SystemUserRoleRepository $roles,
        private readonly FormFactory $forms,
    ) {
        parent::__construct();
    }

    public function actionDefault(?int $upravit = null): void
    {
        if ($upravit !== null && ! isset($this->roles->findAllGroupedByUserId()[$upravit])) {
            $this->flashMessage('Požadovaný uživatel nebyl nalezen.', 'warning');
            $this->redirect('default');
        }
        $this->editedUserId = $upravit;
    }

    public function renderDefault(): void
    {
        $users = [];
        foreach ($this->roles->findAllGroupedByUserId() as $userId => $roles) {
            $users[] = ['userId' => $userId, 'roles' => implode(', ', array_map(static fn (SystemRole $role): string => $role->value, $roles))];
        }

        $this->template->users = $users;
        $this->template->configuredAdminIds = $this->adminAccess->getConfiguredAdminIds();
        $this->template->editedUserId = $this->editedUserId;
    }

    protected function createComponentUserRolesForm(): Form
    {
        $form = $this->forms->create();
        if ($this->editedUserId === null) {
            $form->addInteger('userId', 'skautIS ID uživatele')
                ->setRequired('Zadejte ID uživatele ze skautISu.')
                ->addRule($form::Min, 'ID musí být kladné číslo.', 1)
                ->setOption('description', 'ID uživatele (ne osoby) ze skautISu; uživatel ho vidí v hlášení chyby nebo mu ho řeknete z detailu hlášení.');
        }
        $form->addCheckboxList('roles', 'Systémové role', SystemRole::options())
            ->setRequired('Vyberte alespoň jednu roli.');
        if ($this->editedUserId !== null) {
            $form->setDefaults(['roles' => array_map(static fn (SystemRole $role): string => $role->value, $this->roles->findAllGroupedByUserId()[$this->editedUserId] ?? [])]);
        }
        $form->addSubmit('save', $this->editedUserId === null ? 'Přidat uživatele' : 'Uložit role');

        $form->onSuccess[] = function (Form $form, array $values): void {
            $userId = $this->editedUserId ?? (int) $values['userId'];
            if ($this->editedUserId === null && isset($this->roles->findAllGroupedByUserId()[$userId])) {
                $this->flashMessage('Tento uživatel už systémové role má, upravte je.', 'warning');
                $this->redirect('default', ['upravit' => $userId]);
            }
            $this->roles->replaceRoles($userId, array_values(array_map(static fn (mixed $role): SystemRole => SystemRole::from((string) $role), (array) $values['roles'])));
            $this->flashMessage($this->editedUserId === null ? 'Uživateli byly přiřazeny systémové role.' : 'Systémové role uživatele byly upraveny.', 'success');
            $this->redirect('default');
        };

        return $form;
    }

    /**
     * @return Multiplier<Form>
     */
    protected function createComponentRemoveUser(): Multiplier
    {
        return new Multiplier(function (string $userId): Form {
            $form = $this->forms->create();
            $form->addSubmit('remove', 'Odebrat role')
                ->setOption('class', 'btn btn-sm btn-outline-danger')
                ->setHtmlAttribute('data-confirm', 'Odebrat všechny systémové role tohoto uživatele?');
            $form->onSuccess[] = function () use ($userId): void {
                $this->roles->removeAllForUser((int) $userId);
                $this->flashMessage('Všechny systémové role uživatele byly odebrány.', 'success');
                $this->redirect('default');
            };

            return $form;
        });
    }
}
