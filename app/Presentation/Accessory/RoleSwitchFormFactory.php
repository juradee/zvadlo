<?php

declare(strict_types=1);

namespace App\Presentation\Accessory;

use App\Model\User\CurrentUser;
use App\Model\User\LoginService;
use App\Model\User\UserPreferenceRepository;
use DateTimeImmutable;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;

/**
 * The role switch in the navigation, together with the login preferences. After switching, the leader goes back to the page they were on, which then
 * loads in the new role, so that page's own access checks decide whether they may still see it.
 */
final class RoleSwitchFormFactory
{
    public function __construct(
        private readonly LoginService $loginService,
        private readonly CurrentUser $currentUser,
        private readonly UserPreferenceRepository $preferences,
    ) {
    }

    /**
     * @param string $back path and query of the page the form is shown on
     */
    public function create(string $back): Form
    {
        $items = [];
        foreach ($this->currentUser->getRoles() as $role) {
            $items[$role->id] = $role->displayName;
        }

        $preference = $this->preferences->get($this->currentUser->getUser()->id);

        $form = new Form();
        $form->addSelect('role', 'Role ve skautISu', $items)
            ->setDefaultValue($this->currentUser->getRole()->id)
            ->setRequired();
        $form->addCheckbox('remember', 'Pamatovat si roli pro příští přihlášení')
            ->setDefaultValue($preference->shouldRememberRole());
        $form->addCheckbox('extend', 'Prodlužovat přihlášení, dokud mám Zvadlo otevřené')
            ->setDefaultValue($preference->shouldExtendLogin());
        $form->addHidden('back', $back);
        $form->addSubmit('switch', 'Přepnout roli');
        $form->onSuccess[] = function (Form $form, array $values): void {
            $preference = $this->preferences->get($this->currentUser->getUser()->id);
            $preference->edit((bool) $values['extend'], (bool) $values['remember'], new DateTimeImmutable());
            $this->preferences->save($preference);
            $this->loginService->switchRole((int) $values['role']);
            $presenter = $form->getPresenter();
            $presenter->flashMessage('Role byla přepnuta.', 'success');
            // Only a local path: the hidden field comes from the browser and must not redirect elsewhere.
            $back = (string) $values['back'];
            if (preg_match('~^/(?![/\\\\])~', $back) === 1) {
                // redirectUrl() does not carry the flash message over the way redirect() does.
                $flashKey = $presenter->getParameter(Presenter::FlashKey);
                $presenter->redirectUrl(\is_string($flashKey) ? $back.(str_contains($back, '?') ? '&' : '?').Presenter::FlashKey.'='.urlencode($flashKey) : $back);
            }
            $presenter->redirect(':Dashboard:default');
        };

        return $form;
    }

    /**
     * The page's own URL without the signal, so going back never repeats a form submission.
     */
    public static function backFor(Presenter $presenter): string
    {
        $url = $presenter->getHttpRequest()->getUrl();
        $query = $url->getQueryParameters();
        unset($query[Presenter::SignalKey], $query[Presenter::FlashKey]);

        return $url->getPath().($query === [] ? '' : '?'.http_build_query($query));
    }
}
