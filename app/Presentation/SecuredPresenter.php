<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Model\Auth\Authorizator;
use App\Model\User\CurrentUser;
use App\Model\User\LoginService;
use Nette\DI\Attributes\Inject;

/**
 * Pages for leaders: require a valid skautIS login.
 */
abstract class SecuredPresenter extends BasePresenter
{
    #[Inject]
    public LoginService $loginService;

    #[Inject]
    public CurrentUser $currentUser;

    #[Inject]
    public Authorizator $authorizator;

    protected function startup(): void
    {
        parent::startup();

        if (! $this->loginService->isValid()) {
            $this->flashMessage('Přihlaste se prosím přes skautIS.', 'warning');
            $this->redirect(':Auth:in', ['backlink' => $this->storeRequest()]);
        }
    }

    protected function beforeRender(): void
    {
        parent::beforeRender();

        $this->template->currentUser = $this->currentUser;
    }
}
