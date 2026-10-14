<?php

declare(strict_types=1);

namespace App\Presentation\Session;

use App\Model\User\LoginService;
use App\Presentation\BasePresenter;
use Nette\Http\IResponse;

/**
 * The browser pings this while a leader has Zvadlo open, so the skautIS login does not run out mid-work.
 */
final class SessionPresenter extends BasePresenter
{
    public function __construct(private readonly LoginService $loginService)
    {
        parent::__construct();
    }

    public function actionKeepAlive(): void
    {
        if (! $this->getUser()->isLoggedIn()) {
            $this->getHttpResponse()->setCode(IResponse::S401_Unauthorized);
            $this->sendJson(['ok' => false]);
        }

        if (! $this->loginService->refresh()) {
            $this->getHttpResponse()->setCode(IResponse::S409_Conflict);
            $this->sendJson(['ok' => false]);
        }

        $this->sendJson(['ok' => true]);
    }

    /**
     * Receives the role switch form from the navigation (BasePresenter::createComponentRoleSwitchForm).
     */
    public function actionSwitchRole(): void
    {
        if (! $this->getUser()->isLoggedIn() || ! $this->loginService->refresh()) {
            $this->flashMessage('Přihlaste se prosím přes skautIS.', 'warning');
            $this->redirect(':Auth:in');
        }
        if (! $this->getRequest()->isMethod('POST')) {
            $this->redirect(':Dashboard:default');
        }
    }
}
