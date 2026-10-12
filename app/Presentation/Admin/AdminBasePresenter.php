<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Presentation\SecuredPresenter;

/**
 * Pages of the administration: a system role (admin, or support for bug reports) is required.
 */
abstract class AdminBasePresenter extends SecuredPresenter
{
    protected function startup(): void
    {
        parent::startup();

        if (! $this->hasAccess()) {
            $this->flashMessage('Nemáte oprávnění vstoupit do administrace.', 'danger');
            $this->redirect(':Dashboard:default');
        }
    }

    protected function beforeRender(): void
    {
        parent::beforeRender();

        $this->template->isAdmin = $this->adminAccess->isAdmin();
    }

    protected function hasAccess(): bool
    {
        return $this->adminAccess->isAdmin();
    }
}
