<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\Accessory\EnvironmentInfo;
use Nette\Application\UI\Presenter;
use Nette\DI\Attributes\Inject;

abstract class BasePresenter extends Presenter
{
    #[Inject]
    public EnvironmentInfo $environmentInfo;

    protected function beforeRender(): void
    {
        parent::beforeRender();

        $this->template->environment = $this->environmentInfo;
        $this->template->keepAliveUrl = null;
        $this->template->keepAliveInterval = 0;
        $this->template->canAccessAdministration = false;
    }
}
