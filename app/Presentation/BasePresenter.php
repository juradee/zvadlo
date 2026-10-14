<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\Accessory\EnvironmentInfo;
use App\Presentation\Accessory\RoleSwitchFormFactory;
use Nette\Application\UI\Form;
use Nette\Application\UI\Link;
use Nette\Application\UI\Presenter;
use Nette\DI\Attributes\Inject;

abstract class BasePresenter extends Presenter
{
    #[Inject]
    public EnvironmentInfo $environmentInfo;

    #[Inject]
    public RoleSwitchFormFactory $roleSwitchFormFactory;

    protected function beforeRender(): void
    {
        parent::beforeRender();

        $this->template->environment = $this->environmentInfo;
        $this->template->keepAliveUrl = null;
        $this->template->keepAliveInterval = 0;
        $this->template->canAccessAdministration = false;
        // Also on error pages: a leader whose new role may not see a page can switch back from its 403 page.
        $this->template->canSwitchRole = $this->getUser()->isLoggedIn();
    }

    protected function createComponentRoleSwitchForm(): Form
    {
        $form = $this->roleSwitchFormFactory->create(RoleSwitchFormFactory::backFor($this));
        // Handled by Session:switchRole, not by the page itself: a page the new role may not see would refuse it.
        $form->setAction(new Link($this, ':Session:switchRole', [self::SignalKey => 'roleSwitchForm-submit']));

        return $form;
    }
}
