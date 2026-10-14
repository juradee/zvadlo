<?php

declare(strict_types=1);

namespace App\Presentation\Google;

use App\Model\Mailing\GoogleAccounts;
use App\Model\Mailing\SenderDomains;
use App\Presentation\SecuredPresenter;
use Nette\Utils\Random;
use Throwable;

/**
 * Gmail account consent: /google/oauth sends the leader to Google, /google/token takes the code back.
 * Both paths are registered as the OAuth client's redirect URI.
 */
final class GooglePresenter extends SecuredPresenter
{
    public function __construct(
        private readonly GoogleAccounts $google,
        private readonly SenderDomains $senderDomains,
    ) {
        parent::__construct();
    }

    public function actionOauth(int $jednotka): void
    {
        if (! $this->authorizator->canEditUnit($jednotka)) {
            $this->error('Tuto jednotku nemůžete spravovat.', 403);
        }
        if (! $this->google->isConfigured()) {
            $this->flashMessage('Propojení s Googlem není v tomto prostředí nastavené.', 'warning');
            $this->redirect(':Unit:Settings:default', ['jednotka' => $jednotka]);
        }
        $unit = $this->currentUser->getAccess()->edit[$jednotka];
        if (! SenderDomains::canOwnAccounts($unit->typeId)) {
            $this->flashMessage('Gmail účet propojuje oddíl nebo vyšší jednotka a může ho povolit podřízeným jednotkám.', 'warning');
            $this->redirect(':Unit:Settings:default', ['jednotka' => $jednotka]);
        }

        $state = Random::generate(32);
        $this->getSession('google')->set('unit', $jednotka, '20 minutes');
        $this->getSession('google')->set('state', $state, '20 minutes');
        $this->redirectUrl($this->google->consentUrl($state));
    }

    public function actionToken(?string $code = null, ?string $error = null, ?string $state = null): void
    {
        $session = $this->getSession('google');
        $unitId = $session->get('unit');
        $expectedState = $session->get('state');
        $session->remove('unit');
        $session->remove('state');
        if (! \is_int($unitId) || ! $this->authorizator->canEditUnit($unitId)) {
            $this->flashMessage('Propojení účtu vypršelo, zkuste to znovu.', 'warning');
            $this->redirect(':Unit:Settings:default');
        }
        // The state ties Google's callback to the consent this browser started; a foreign code is refused.
        if (! \is_string($expectedState) || $state === null || ! hash_equals($expectedState, $state)) {
            $this->flashMessage('Propojení účtu neodpovídá zahájenému požadavku, zkuste to znovu.', 'danger');
            $this->redirect(':Unit:Settings:default', ['jednotka' => $unitId]);
        }

        if ($code === null || $code === '') {
            $this->flashMessage('Google propojení nepotvrdil'.($error === null ? '.' : ' ('.$error.').'), 'warning');
            $this->redirect(':Unit:Settings:default', ['jednotka' => $unitId]);
        }

        try {
            $allowedDomains = $this->senderDomains->allowedFor($unitId, $this->currentUser->getUser()->personId);
            $account = $this->google->connect($unitId, $code, $allowedDomains);
            $this->flashMessage(\sprintf('Účet %s je propojený.', $account->getEmail()), 'success');
        } catch (Throwable $exception) {
            $this->flashMessage('Propojení se nezdařilo: '.$exception->getMessage(), 'danger');
        }

        $this->redirect(':Unit:Settings:default', ['jednotka' => $unitId]);
    }
}
