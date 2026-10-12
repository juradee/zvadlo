<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Model\Admin\AdminAccess;
use App\Model\Auth\Authorizator;
use App\Model\Monitoring\Sentry;
use App\Model\User\CurrentUser;
use App\Model\User\LoginService;
use App\Model\User\UserPreferenceRepository;
use Nette\DI\Attributes\Inject;
use Nette\Http\IResponse;

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

    #[Inject]
    public UserPreferenceRepository $preferences;

    #[Inject]
    public AdminAccess $adminAccess;

    /** The skautIS login lasts 30 minutes; the browser asks for an extension every 20 (as h.skauting does). */
    public const int KEEP_ALIVE_INTERVAL_MS = 1_200_000;

    protected function startup(): void
    {
        parent::startup();

        // A page the leader opens extends the skautIS login. Background requests (task polling, AJAX batches) only
        // check it: an open window stays logged in through the keep-alive ping alone, and only when the leader wants.
        if ($this->isAjax()) {
            if (! $this->loginService->isValid()) {
                $this->getHttpResponse()->setCode(IResponse::S401_Unauthorized);
                $this->sendJson(['ok' => false, 'error' => 'Přihlášení vypršelo, obnovte stránku a přihlaste se znovu.']);
            }
        } elseif (! $this->loginService->refresh()) {
            $this->flashMessage('Přihlaste se prosím přes skautIS.', 'warning');
            $this->redirect(':Auth:in', ['backlink' => $this->storeRequest()]);
        }
        $role = $this->currentUser->getRole();
        Sentry::setUser($this->currentUser->getUser()->id, $role->unitId, $role->key);
    }

    protected function beforeRender(): void
    {
        parent::beforeRender();

        $this->template->currentUser = $this->currentUser;
        $extend = $this->preferences->get($this->currentUser->getUser()->id)->shouldExtendLogin();
        $this->template->keepAliveUrl = $extend ? $this->link(':Session:keepAlive') : null;
        $this->template->keepAliveInterval = self::KEEP_ALIVE_INTERVAL_MS;
        $this->template->canAccessAdministration = $this->adminAccess->canAccessAdministration();
    }
}
