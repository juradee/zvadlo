<?php

declare(strict_types=1);

namespace App\Presentation\Auth;

use App\Model\Skautis\SkautisGateway;
use App\Model\User\LoginFailedException;
use App\Model\User\LoginService;
use App\Presentation\BasePresenter;

/**
 * Login and logout through skautIS. skautIS posts the token to skautIS() and returns to skautisLogout()
 * after logging the user out; both paths are registered with the AppID.
 */
final class AuthPresenter extends BasePresenter
{
    public function __construct(
        private readonly SkautisGateway $gateway,
        private readonly LoginService $loginService,
    ) {
        parent::__construct();
    }

    public function actionIn(?string $backlink = null): void
    {
        $this->redirectUrl($this->gateway->getLoginUrl($backlink ?? ''));
    }

    public function actionSkautIS(?string $ReturnUrl = null): void
    {
        $request = $this->getHttpRequest();
        if (! $request->isMethod('POST')) {
            $this->redirect(':Home:default');
        }

        try {
            $this->loginService->loginFromSkautis($request->getPost());
        } catch (LoginFailedException) {
            $this->flashMessage('Přihlášení přes skautIS se nezdařilo. Zkuste to prosím znovu.', 'danger');
            $this->redirect(':Home:default');
        }

        if ($ReturnUrl !== null && $ReturnUrl !== '') {
            $this->restoreRequest($ReturnUrl);
        }

        $this->redirect(':Dashboard:default');
    }

    public function actionOut(): void
    {
        if (! $this->getUser()->isLoggedIn()) {
            $this->redirect(':Home:default');
        }

        $this->redirectUrl($this->gateway->getLogoutUrl());
    }

    public function actionSkautisLogout(): void
    {
        $this->loginService->logout();
        $this->flashMessage('Byli jste odhlášeni.', 'info');
        $this->redirect(':Home:default');
    }
}
