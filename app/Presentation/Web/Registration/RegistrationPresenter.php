<?php

declare(strict_types=1);

namespace App\Presentation\Web\Registration;

use App\Model\Event\EventRepository;
use App\Model\Mailing\EditCodeNotSentException;
use App\Model\Mailing\EditCodeService;
use App\Model\Mailing\EditCodeThrottledException;
use App\Model\Registration\EventFullException;
use App\Model\Registration\Registration;
use App\Model\Registration\RegistrationAnswer;
use App\Model\Registration\RegistrationNotAllowedException;
use App\Model\Registration\RegistrationService;
use App\Model\Registration\RegistrationState;
use App\Model\Registration\Sex;
use App\Model\Registration\VerificationMethod;
use App\Model\Registration\VerificationSession;
use App\Model\Unit\UnitSettingsRepository;
use App\Presentation\Accessory\Form\DateInput;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\BasePresenter;
use App\Presentation\Web\RegistrationFormBuilder;
use DateTimeImmutable;
use LogicException;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;

/**
 * The participant's side: the personal link opens the verification, the form and the summary.
 * Unknown links answer 404 so nothing reveals whether an event or a person exists.
 */
final class RegistrationPresenter extends BasePresenter
{
    // The three parts of the personal link stay in every link and redirect of this presenter.
    #[Persistent]
    public ?int $unitId = null;

    #[Persistent]
    public ?string $slug = null;

    #[Persistent]
    public ?string $token = null;

    private ?Registration $registration = null;

    private DateTimeImmutable $now;

    public function __construct(
        private readonly EventRepository $events,
        private readonly RegistrationService $service,
        private readonly VerificationSession $verification,
        private readonly RegistrationFormBuilder $builder,
        private readonly EditCodeService $codes,
        private readonly UnitSettingsRepository $unitSettings,
        private readonly FormFactory $forms,
    ) {
        parent::__construct();
        $this->now = new DateTimeImmutable();
    }

    public function actionDefault(): void
    {
        $registration = $this->load();
        $this->service->markSeen($registration);

        if ($registration->isBlocked($this->now)) {
            $this->setView('blocked');

            return;
        }
        if (! $this->isVerified($registration)) {
            // Filled-in data are shown only after a one-time code sent to the contact e-mail (non-members).
            $this->setView($registration->getVerificationMethod() === VerificationMethod::Code ? 'code' : 'verify');

            return;
        }

        if ($registration->canBeChangedByParticipant($this->now)) {
            $this->setView('form');
        } else {
            $this->setView($registration->getState()->isPending() ? 'closed' : 'summary');
        }
    }

    public function actionSummary(): void
    {
        $registration = $this->load();
        $this->requireVerified($registration);

        if ($registration->getState()->isPending()) {
            $this->redirect('default');
        }
    }

    public function actionCancel(): void
    {
        $registration = $this->load();
        $this->requireVerified($registration);

        if (! $registration->canBeCancelledByParticipant($this->now)) {
            $this->redirect('summary');
        }
    }

    protected function beforeRender(): void
    {
        parent::beforeRender();

        $registration = $this->requireRegistration();
        $event = $registration->getEvent();

        $this->template->registration = $registration;
        $this->template->event = $event;
        $this->template->fields = $event->getVisibleFields();
        $this->template->now = $this->now;
        $this->template->canChange = $registration->canBeChangedByParticipant($this->now);
        $this->template->canCancel = $registration->canBeCancelledByParticipant($this->now);
        $this->template->verificationMethod = $registration->getVerificationMethod();
    }

    protected function createComponentVerifyForm(): Form
    {
        $registration = $this->requireRegistration();
        $this->requireVerificationStep($registration, [VerificationMethod::Email, VerificationMethod::BirthDate]);
        $form = $this->forms->create();

        if ($registration->getVerificationMethod() === VerificationMethod::Email) {
            $form->addEmail('email', 'E-mail, na který přišla pozvánka')
                ->setRequired('Zadejte e-mail.');
        } else {
            DateInput::add($form, 'birthDate', 'Datum narození účastníka')
                ->setRequired('Zadejte datum narození.');
        }
        $form->addSubmit('verify', 'Pokračovat');

        $form->onSuccess[] = function (Form $form, array $values): void {
            $registration = $this->requireRegistration();
            $this->requireVerificationStep($registration, [VerificationMethod::Email, VerificationMethod::BirthDate]);

            if (isset($values['email'])) {
                $verified = $this->service->verifyEmail($registration, (string) $values['email']);
                $message = 'E-mail nesouhlasí. Zadejte adresu, na kterou pozvánka přišla.';
            } else {
                \assert($values['birthDate'] instanceof DateTimeImmutable);
                $verified = $this->service->verifyBirthDate($registration, $values['birthDate']);
                $message = 'Datum narození nesouhlasí. Zadejte datum narození účastníka, ne své.';
            }

            if ($verified) {
                $this->verification->markVerified($registration);
            } else {
                $this->flashMessage($message, 'danger');
            }

            $this->redirect('default');
        };

        return $form;
    }

    protected function createComponentCodeRequestForm(): Form
    {
        $this->requireVerificationStep($this->requireRegistration(), [VerificationMethod::Code]);
        $form = $this->forms->create();
        $form->addSubmit('send', 'Poslat kód na e-mail')->setOption('class', 'btn btn-outline-primary');
        $form->onSuccess[] = function (): void {
            $registration = $this->requireRegistration();
            $this->requireVerificationStep($registration, [VerificationMethod::Code]);
            try {
                $this->flashMessage(
                    $this->codes->issue($registration) ? 'Kód jsme poslali na kontaktní e-mail přihlášky. Platí 15 minut.' : 'Přihláška nemá žádný e-mail, obraťte se na vedoucího.',
                    'info',
                );
            } catch (EditCodeThrottledException|EditCodeNotSentException $exception) {
                $this->flashMessage($exception->getMessage(), 'warning');
            }
            $this->redirect('default');
        };

        return $form;
    }

    protected function createComponentCodeForm(): Form
    {
        $this->requireVerificationStep($this->requireRegistration(), [VerificationMethod::Code]);
        $form = $this->forms->create();
        $form->addText('code', 'Kód z e-mailu')
            ->setRequired('Zadejte kód.')
            ->addRule($form::Pattern, 'Kód má čtyři číslice.', '\d{4}')
            ->setHtmlAttribute('inputmode', 'numeric')
            ->setHtmlAttribute('autocomplete', 'one-time-code');
        $form->addSubmit('verify', 'Otevřít přihlášku');
        $form->onSuccess[] = function (Form $form, array $values): void {
            $registration = $this->requireRegistration();
            $this->requireVerificationStep($registration, [VerificationMethod::Code]);
            if ($this->codes->verify($registration, (string) $values['code'])) {
                $this->verification->markVerified($registration);
            } else {
                $this->flashMessage('Kód nesouhlasí nebo vypršel.', 'danger');
            }
            $this->redirect('default');
        };

        return $form;
    }

    protected function createComponentRegistrationForm(): Form
    {
        $registration = $this->requireRegistration();
        // The form exists (and accepts a submit) only for a verified link; actionDefault alone does not stop a signal.
        $this->requireVerified($registration);
        $event = $registration->getEvent();

        $form = $this->forms->create();
        // Its own group renders it first: the renderer puts controls outside any group after all the groups.
        $form->addGroup();
        $attending = $form->addRadioList('attending', 'Účast na akci', ['yes' => 'Zúčastní se', 'no' => 'Nezúčastní se'])
            ->setRequired('Vyberte, zda se účastník zúčastní.');
        $attending->addCondition($form::Equal, 'no')->toggle('decline-box');
        $attending->addCondition($form::Equal, 'yes')->toggle('attend-box')->toggle('fields-box')->toggle('agreement-box');

        $form->addGroup()->setOption('id', 'decline-box');
        $form->addTextArea('declineReason', 'Důvod (nepovinné)', null, 2)->setNullable();

        $form->addGroup('Údaje účastníka')->setOption('id', 'attend-box');
        if (! $registration->isMember()) {
            $this->builder->addPersonFields($form, $attending);
        }
        $this->builder->addContactFields($form, $attending, $registration->asksForContactEmail());

        $form->addGroup('Doplňující otázky')->setOption('id', 'fields-box');
        $this->builder->addCustomFields($form, $event, $attending);

        $form->addGroup()->setOption('id', 'agreement-box');
        $this->builder->addAgreement($form, $attending, $this->unitSettings->get($event->getUnitId())->getAgreementText());

        $form->setCurrentGroup(null);
        $form->addSubmit('send', $registration->getState()->isAttending() ? 'Uložit změny' : 'Odeslat přihlášku')
            ->setOption('class', 'btn btn-primary btn-lg w-100');

        $form->setDefaults($this->defaults($registration));

        $form->onSuccess[] = function (Form $form, array $values): void {
            $registration = $this->requireRegistration();
            $this->requireVerified($registration);
            $answer = $this->answerFromValues($registration, $values);
            $previous = $registration->getState();

            try {
                $this->service->respond($registration, $answer);
            } catch (EventFullException $exception) {
                // The last place went to someone who sent the form a moment earlier; the answers stay in the form.
                $form->addError($exception->getMessage());

                return;
            } catch (RegistrationNotAllowedException $exception) {
                $this->flashMessage($exception->getMessage(), 'danger');
                $this->redirect('default');
            }

            if ($registration->getState() === RegistrationState::Waitlisted && $previous !== RegistrationState::Waitlisted) {
                $this->flashMessage('Kapacita akce je naplněná, účastník je proto zapsaný jako náhradník na čekací listině. Když se uvolní místo, dáme vám vědět e-mailem.', 'warning');
            } else {
                $this->flashMessage($answer->attending ? 'Přihláška byla uložena.' : 'Děkujeme za odpověď, účast je zaznamenána jako odmítnutá.', 'success');
            }
            $this->redirect('summary');
        };

        return $form;
    }

    protected function createComponentCancelForm(): Form
    {
        $this->requireVerified($this->requireRegistration());
        $form = $this->forms->create();
        $form->addSubmit('cancel', 'Ano, zrušit účast')->setOption('class', 'btn btn-danger');

        $form->onSuccess[] = function (): void {
            $registration = $this->requireRegistration();
            $this->requireVerified($registration);
            try {
                $this->service->cancelByParticipant($registration);
                $this->flashMessage('Účast byla zrušena.', 'success');
            } catch (RegistrationNotAllowedException $exception) {
                $this->flashMessage($exception->getMessage(), 'danger');
            }

            $this->redirect('summary');
        };

        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(Registration $registration): array
    {
        $state = $registration->getState();
        $defaults = [
            'attending' => $state->isAttending() ? 'yes' : ($state === RegistrationState::Declined ? 'no' : null),
            'declineReason' => $registration->getDeclineReason(),
            'contactEmail' => $registration->asksForContactEmail() ? ($registration->getContactEmail() ?? ($registration->getSelectedRecipientEmails()[0] ?? null)) : null,
            'phone' => $registration->getPhone(),
            'note' => $registration->getNote(),
        ];

        if (! $registration->isMember()) {
            $defaults += [
                'firstName' => $registration->getFirstName(),
                'lastName' => $registration->getLastName(),
                'nickName' => $registration->getNickName(),
                'sex' => $registration->getSex()?->value,
                'birthDate' => $registration->getBirthDate(),
                'street' => $registration->getStreet(),
                'city' => $registration->getCity(),
                'postcode' => $registration->getPostcode(),
            ];
        }

        return $defaults + $this->builder->customFieldDefaults($registration);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function answerFromValues(Registration $registration, array $values): RegistrationAnswer
    {
        $attending = $values['attending'] === 'yes';
        $birthDate = $values['birthDate'] ?? null;

        return new RegistrationAnswer(
            attending: $attending,
            declineReason: $attending ? null : RegistrationFormBuilder::string($values, 'declineReason'),
            contactEmail: RegistrationFormBuilder::string($values, 'contactEmail'),
            phone: RegistrationFormBuilder::string($values, 'phone'),
            note: RegistrationFormBuilder::string($values, 'note'),
            firstName: RegistrationFormBuilder::string($values, 'firstName'),
            lastName: RegistrationFormBuilder::string($values, 'lastName'),
            nickName: RegistrationFormBuilder::string($values, 'nickName'),
            sex: isset($values['sex']) && \is_string($values['sex']) ? Sex::tryFrom($values['sex']) : null,
            birthDate: $birthDate instanceof DateTimeImmutable ? $birthDate : null,
            street: RegistrationFormBuilder::string($values, 'street'),
            city: RegistrationFormBuilder::string($values, 'city'),
            postcode: RegistrationFormBuilder::string($values, 'postcode'),
            fieldValues: $this->builder->customFieldValues($registration->getEvent(), $values),
        );
    }

    private function load(): Registration
    {
        if ($this->unitId === null || $this->slug === null || $this->token === null) {
            $this->error('Odkaz je neplatný.');
        }

        $event = $this->events->findBySlug($this->unitId, $this->slug);
        $registration = $this->service->findByToken($this->token);
        if ($event === null || $registration === null || $registration->getEvent() !== $event || $registration->getState() === RegistrationState::Rejected) {
            $this->error('Odkaz je neplatný.');
        }

        return $this->registration = $registration;
    }

    private function isVerified(Registration $registration): bool
    {
        return $registration->getVerificationMethod() === VerificationMethod::None || $this->verification->isVerified($registration);
    }

    private function requireVerified(Registration $registration): void
    {
        if ($registration->isBlocked($this->now) || ! $this->isVerified($registration)) {
            $this->redirect('default');
        }
    }

    /**
     * A verification form is usable only while the link is unverified, not blocked, and the registration
     * really is in that verification method (a non-member must not be unlocked by a guessed birth date).
     *
     * @param list<VerificationMethod> $methods
     */
    private function requireVerificationStep(Registration $registration, array $methods): void
    {
        if ($registration->isBlocked($this->now) || $this->isVerified($registration) || ! \in_array($registration->getVerificationMethod(), $methods, true)) {
            $this->redirect('default');
        }
    }

    private function requireRegistration(): Registration
    {
        return $this->registration ?? throw new LogicException('The registration is loaded in the action.');
    }
}
