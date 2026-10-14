<?php

declare(strict_types=1);

namespace App\Presentation\Unit\Settings;

use App\Model\Mailing\EmailTemplate;
use App\Model\Mailing\EmailType;
use App\Model\Mailing\GoogleAccounts;
use App\Model\Mailing\GoogleOAuth;
use App\Model\Mailing\GoogleOAuthRepository;
use App\Model\Mailing\MailAccounts;
use App\Model\Mailing\SenderDomains;
use App\Model\Mailing\TemplateRenderer;
use App\Model\Mailing\UnitEmailTemplateRepository;
use App\Model\Unit\UnitSettingsRepository;
use App\Model\User\UnitHierarchy;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\SecuredPresenter;
use LogicException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;

/**
 * What a unit sets once: Gmail accounts, default e-mail texts, the agreement text and the default lock.
 */
final class SettingsPresenter extends SecuredPresenter
{
    private ?int $unitId = null;

    public function __construct(
        private readonly UnitSettingsRepository $settings,
        private readonly UnitEmailTemplateRepository $templates,
        private readonly GoogleOAuthRepository $accounts,
        private readonly GoogleAccounts $google,
        private readonly MailAccounts $mailAccounts,
        private readonly SenderDomains $senderDomains,
        private readonly UnitHierarchy $units,
        private readonly FormFactory $forms,
    ) {
        parent::__construct();
    }

    public function actionDefault(?int $jednotka = null): void
    {
        $access = $this->currentUser->getAccess();
        if ($access->edit === []) {
            $this->error('Vaše role neumožňuje spravovat nastavení jednotky. Zkuste přepnout roli.', 403);
        }

        $unitId = $jednotka ?? $this->currentUser->getRole()->unitId;
        if (! $access->canEdit($unitId)) {
            $unitId = (int) array_key_first($access->edit);
        }
        $this->unitId = $unitId;
    }

    public function renderDefault(): void
    {
        $unitId = $this->requireUnitId();
        $access = $this->currentUser->getAccess();
        $own = $this->templates->findByUnit($unitId);

        $templates = [];
        foreach (EmailType::unitTemplates() as $type) {
            $templates[$type->value] = ['type' => $type, 'custom' => isset($own[$type->value])];
        }

        $unit = $access->edit[$unitId];
        $canOwnAccounts = SenderDomains::canOwnAccounts($unit->typeId);
        $googleConfigured = $this->google->isConfigured();
        $this->template->unit = $unit;
        $this->template->units = $access->edit;
        $this->template->settings = $this->settings->get($unitId);
        $this->template->accounts = $this->accounts->findByUnits([$unitId]);
        $this->template->sharedAccounts = array_filter($this->mailAccounts->availableFor($unitId), static fn (GoogleOAuth $account): bool => $account->getUnitId() !== $unitId);
        $this->template->canOwnAccounts = $canOwnAccounts;
        $this->template->googleConfigured = $googleConfigured;
        // Read from skautIS on every visit, never cached; only where an account can be connected.
        $this->template->allowedDomains = $googleConfigured && $canOwnAccounts
            ? $this->senderDomains->allowedFor($unitId, $this->currentUser->getUser()->personId)
            : [];
        $this->template->templates = $templates;
        $this->template->symbols = TemplateRenderer::SYMBOLS;
    }

    protected function createComponentSettingsForm(): Form
    {
        $unitId = $this->requireUnitId();
        $settings = $this->settings->get($unitId);
        $accounts = [];
        foreach ($this->mailAccounts->availableFor($unitId) as $account) {
            $accounts[$account->getId()] = $this->accountLabel($account, $unitId);
        }

        $form = $this->forms->create();
        $form->addTextArea('agreementText', 'Text souhlasu v přihlášce', null, 3)
            ->setNullable()
            ->setOption('description', 'Zaškrtávátko na konci přihlášky. Prázdné = výchozí text.');
        $form->addSelect('defaultMailAccount', 'Výchozí odesílací účet nových akcí', $accounts)
            ->setPrompt($accounts === [] ? 'Žádný účet není propojený' : 'Vyberte');
        $form->addSubmit('save', 'Uložit nastavení');
        $form->setDefaults([
            'agreementText' => $settings->getAgreementText() === $settings::DEFAULT_AGREEMENT ? null : $settings->getAgreementText(),
            'defaultMailAccount' => $settings->getDefaultMailAccount()?->getId(),
        ]);

        $form->onSuccess[] = function (Form $form, array $values): void {
            $settings = $this->settings->get($this->requireUnitId());
            $account = $values['defaultMailAccount'] === null ? null : $this->accounts->find((string) $values['defaultMailAccount']);
            if ($account !== null && ! $this->mailAccounts->isAvailableFor($account, $this->requireUnitId())) {
                $this->error('Tento účet jednotka nesmí používat.', 403);
            }
            $settings->edit(
                $values['agreementText'] === null ? null : (string) $values['agreementText'],
                $account,
            );
            $this->settings->save($settings);
            $this->flashMessage('Nastavení jednotky bylo uloženo.', 'success');
            $this->redirect('this');
        };

        return $form;
    }

    protected function createComponentTemplateForm(): Form
    {
        $unitId = $this->requireUnitId();
        $own = $this->templates->findByUnit($unitId);

        $form = $this->forms->create();
        foreach (EmailType::unitTemplates() as $type) {
            $current = $own[$type->value] ?? null;
            $default = EmailTemplate::default($type);
            $container = $form->addContainer($type->value);
            $container->addText('subject', 'Předmět')
                ->setRequired('Zadejte předmět.')
                ->addRule($form::MaxLength, 'Předmět může mít nejvýše %d znaků.', 255)
                ->setDefaultValue($current?->getSubject() ?? $default->subject);
            $container->addTextArea('body', 'Text', null, 8)
                ->setRequired('Zadejte text.')
                ->setDefaultValue($current?->getBody() ?? $default->body);
        }
        $form->addSubmit('save', 'Uložit výchozí texty');
        foreach (EmailType::unitTemplates() as $type) {
            $form->addSubmit('reset_'.$type->value, 'Obnovit výchozí text')
                ->setValidationScope([])
                ->setHtmlAttribute('data-confirm', 'Vrátit text e-mailu „'.$type->label().'“ do výchozího stavu? Neuložené změny ostatních textů se zahodí.')
                ->onClick[] = function () use ($type): void {
                    $this->templates->reset($this->requireUnitId(), $type);
                    $this->flashMessage(\sprintf('Text e-mailu „%s“ je zase výchozí.', $type->label()), 'success');
                    $this->redirect('this');
                };
        }

        // A reset button redirects in its own handler, so only the save button gets here.
        $form->onSuccess[] = function (Form $form, array $values): void {
            $unitId = $this->requireUnitId();
            foreach (EmailType::unitTemplates() as $type) {
                $container = $values[$type->value];
                \assert(\is_array($container));
                $this->templates->save($unitId, $type, (string) $container['subject'], (string) $container['body']);
            }
            $this->flashMessage('Výchozí texty e-mailů byly uloženy. Použijí se u nově zakládaných akcí; zamítnutí platí hned.', 'success');
            $this->redirect('this');
        };

        return $form;
    }

    /**
     * @return Multiplier<Form>
     */
    protected function createComponentRemoveAccount(): Multiplier
    {
        // The key is the account UUID without dashes: '-' separates component names.
        return new Multiplier(function (string $key): Form {
            $form = $this->forms->create();
            $form->addSubmit('remove', 'Odebrat')
                ->setOption('class', 'btn btn-sm btn-outline-danger')
                ->setHtmlAttribute('data-test', 'remove-account')
                ->setHtmlAttribute('data-confirm', 'Odebrat účet? Akce, které z něj posílají, budou potřebovat jiný účet.');
            $form->onSuccess[] = function () use ($key): void {
                $account = $this->ownAccount($key);
                if ($account !== null) {
                    $this->google->remove($account);
                    $this->flashMessage('Účet byl odebrán.', 'success');
                }
                $this->redirect('this');
            };

            return $form;
        });
    }

    /**
     * @return Multiplier<Form>
     */
    protected function createComponentShareAccount(): Multiplier
    {
        // Keyed like removeAccount: the account UUID without dashes.
        return new Multiplier(function (string $key): Form {
            $account = $this->ownAccount($key);
            $form = $this->forms->create();
            $shared = $account?->isAllowedForSubunits() ?? false;
            $form->addSubmit('toggle', $shared ? 'Zakázat podřízeným' : 'Povolit podřízeným')
                ->setOption('class', 'btn btn-sm btn-outline-secondary')
                ->setHtmlAttribute('data-test', 'share-account')
                ->setHtmlAttribute('data-confirm', $shared ? 'Zakázat účet podřízeným jednotkám? Jejich akce, které z něj posílají, budou potřebovat jiný účet.' : null);
            $form->onSuccess[] = function () use ($key): void {
                $account = $this->ownAccount($key);
                if ($account !== null && $account->isAllowedForSubunits()) {
                    $this->google->disallowForSubunits($account);
                    $this->flashMessage(\sprintf('Účet %s už podřízené jednotky nepoužívají.', $account->getEmail()), 'success');
                } elseif ($account !== null) {
                    $this->google->allowForSubunits($account);
                    $this->flashMessage(\sprintf('Účet %s mohou používat i podřízené jednotky.', $account->getEmail()), 'success');
                }
                $this->redirect('this');
            };

            return $form;
        });
    }

    private function ownAccount(string $key): ?GoogleOAuth
    {
        foreach ($this->accounts->findByUnits([$this->requireUnitId()]) as $account) {
            if (str_replace('-', '', $account->getId()) === $key) {
                return $account;
            }
        }

        return null;
    }

    private function accountLabel(GoogleOAuth $account, int $unitId): string
    {
        return $account->getUnitId() === $unitId
            ? $account->getEmail()
            : $account->getEmail().' ('.($this->units->unit($account->getUnitId())->displayName ?? $account->getUnitId()).')';
    }

    private function requireUnitId(): int
    {
        return $this->unitId ?? throw new LogicException('The unit is resolved in the action.');
    }
}
