<?php

declare(strict_types=1);

namespace App\Presentation\Web\Public;

use App\Model\Event\Event;
use App\Model\Event\EventRepository;
use App\Model\Event\PublicRegistration;
use App\Model\Registration\DuplicateRegistrationException;
use App\Model\Registration\EventFullException;
use App\Model\Registration\PairingHint;
use App\Model\Registration\PublicMatch;
use App\Model\Registration\PublicRegistrationService;
use App\Model\Registration\RegistrationAnswer;
use App\Model\Registration\RegistrationNotAllowedException;
use App\Model\Registration\RegistrationRepository;
use App\Model\Registration\RegistrationService;
use App\Model\Registration\Sex;
use App\Model\Registration\SkautisParticipantLogin;
use App\Model\Registration\TooManyRegistrationsException;
use App\Model\Registration\VerificationSession;
use App\Model\Skautis\Dto\Person;
use App\Model\Skautis\SkautisGateway;
use App\Model\Unit\UnitSettingsRepository;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\BasePresenter;
use App\Presentation\Web\RegistrationFormBuilder;
use DateTimeImmutable;
use LogicException;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;
use Nette\Http\SessionSection;

/**
 * Sign-up through the shared event link in two steps: who the participant is (enough to recognise a member),
 * then only what is still missing. Contact and address are remembered for the next child.
 */
final class PublicPresenter extends BasePresenter
{
    #[Persistent]
    public ?int $unitId = null;

    #[Persistent]
    public ?string $slug = null;

    #[Persistent]
    public ?string $t = null;

    private ?Event $event = null;

    /** @var array<string, mixed>|null step one of the form, kept in the session until step two is sent */
    private ?array $draft = null;

    public function __construct(
        private readonly EventRepository $events,
        private readonly RegistrationRepository $registrations,
        private readonly PublicRegistrationService $service,
        private readonly RegistrationFormBuilder $builder,
        private readonly UnitSettingsRepository $unitSettings,
        private readonly FormFactory $forms,
        private readonly SkautisParticipantLogin $participantLogin,
        private readonly SkautisGateway $gateway,
        private readonly VerificationSession $verification,
        private readonly RegistrationService $registrationService,
    ) {
        parent::__construct();
    }

    public function actionDefault(): void
    {
        $event = $this->load();
        if (! $event->isPublicRegistrationOpen(new DateTimeImmutable()) && ! $event->acceptsSkautisLogin(new DateTimeImmutable())) {
            $this->setView('closed');
        }
    }

    /**
     * "Přihlásit se přes skautIS": skautIS logs the participant in and posts the token to Auth:skautIS with
     * our backlink, which brings them to skautis() below.
     */
    public function actionSkautisLogin(): void
    {
        $event = $this->load();
        if (! $event->acceptsSkautisLogin(new DateTimeImmutable())) {
            $this->redirect('default');
        }

        $this->redirectUrl($this->gateway->getLoginUrl(SkautisParticipantLogin::backlink($event->getUnitId(), $event->getSlug(), $event->getWebToken())));
    }

    /**
     * "Koho přihlašujete?" after the skautIS login: the account's person and their children.
     */
    public function actionSkautis(): void
    {
        $event = $this->load();
        if (! $event->acceptsSkautisLogin(new DateTimeImmutable())) {
            $this->setView('closed');

            return;
        }

        $candidates = $this->participantLogin->getCandidates();
        if ($candidates === []) {
            $this->flashMessage('Přihlášení přes skautIS vypršelo, zkuste to prosím znovu.', 'warning');
            $this->redirect('default');
        }

        $rows = [];
        foreach ($candidates as $candidate) {
            $rows[] = ['person' => $candidate, 'registration' => $this->registrations->findByPerson($event, $candidate->id)];
        }
        $this->template->candidates = $rows;
    }

    /**
     * The chosen person: an existing registration opens straight away (skautIS proved the identity),
     * otherwise step two with everything skautIS knows filled in.
     */
    public function handleChoose(int $personId): void
    {
        $event = $this->requireEvent();
        $person = $this->participantLogin->findCandidate($personId);
        if ($person === null || ! $event->acceptsSkautisLogin(new DateTimeImmutable())) {
            $this->redirect('default');
        }

        $existing = $this->registrations->findByPerson($event, $person->id);
        if ($existing !== null) {
            $this->verification->markVerified($existing);
            $this->redirect(':Web:Registration:default', ['unitId' => $event->getUnitId(), 'slug' => $event->getSlug(), 'token' => $this->registrationService->getToken($existing)]);
        }

        $this->section()->set('draft-'.$event->getId(), [
            'firstName' => $person->firstName,
            'lastName' => $person->lastName,
            'birthDate' => $person->birthday?->format('Y-m-d'),
            'personId' => $person->id,
            'personName' => $person->getDisplayName(),
            'hint' => null,
            'skautis' => true,
            'nickName' => $person->nickName,
            'sex' => $person->sex,
            'email' => $person->email,
            'phone' => $person->phone,
        ], '1 hour');
        $this->redirect('details');
    }

    public function actionDetails(): void
    {
        $event = $this->load();
        $now = new DateTimeImmutable();
        if (! $event->isPublicRegistrationOpen($now) && ! $event->acceptsSkautisLogin($now)) {
            $this->setView('closed');

            return;
        }

        $draft = $this->section()->get('draft-'.$event->getId());
        if (! \is_array($draft)) {
            $this->redirect('default');
        }
        $this->draft = $draft;
        $this->template->draft = $draft;
        $this->template->isMember = $draft['personId'] !== null;
        $this->template->viaSkautis = ($draft['skautis'] ?? false) === true;
    }

    public function actionDone(int $id): void
    {
        $event = $this->load();
        $registration = $this->registrations->find($id);
        $allowed = $this->section()->get('done');
        if ($registration === null || $registration->getEvent() !== $event || $allowed !== $id) {
            $this->redirect('default');
        }

        $this->template->registration = $registration;
    }

    protected function beforeRender(): void
    {
        parent::beforeRender();

        $event = $this->requireEvent();
        $this->template->event = $event;
        $this->template->fields = $event->getVisibleFields();
        $this->template->now = new DateTimeImmutable();
        $this->template->skautisLogin = $event->acceptsSkautisLogin(new DateTimeImmutable());
        $this->template->skautisOnly = $event->getPublicRegistration() === PublicRegistration::Skautis;
    }

    /**
     * Step one: name and birth date, the same two things a member is recognised by.
     */
    protected function createComponentParticipantForm(): Form
    {
        $family = $this->family();
        $form = $this->forms->create();
        // Members are recognised by name and birth date alone; nobody has to say whether they are one.
        $this->builder->addIdentityFields($form, null);
        // Bots fill every field; people never see this one.
        $form->addText('website', 'Web')->setOption('class', 'd-none')->setHtmlAttribute('autocomplete', 'off')->setHtmlAttribute('tabindex', '-1');
        $form->addSubmit('next', 'Pokračovat')->setOption('class', 'btn btn-primary btn-lg w-100');
        $form->setDefaults([
            'lastName' => $family['lastName'] ?? null,
        ]);

        $form->onSuccess[] = function (Form $form, array $values): void {
            if (($values['website'] ?? '') !== '') {
                $this->redirect('done', ['id' => 0]);
            }
            $event = $this->requireEvent();
            $birthDate = $values['birthDate'];
            \assert($birthDate instanceof DateTimeImmutable);
            $firstName = (string) RegistrationFormBuilder::string($values, 'firstName');
            $lastName = (string) RegistrationFormBuilder::string($values, 'lastName');

            try {
                $match = $this->service->match($event, $firstName, $lastName, $birthDate, $this->getHttpRequest()->getRemoteAddress() ?? 'unknown');
            } catch (DuplicateRegistrationException|RegistrationNotAllowedException|TooManyRegistrationsException $exception) {
                $form->addError($exception->getMessage());

                return;
            }

            $this->section()->set('draft-'.$event->getId(), [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'birthDate' => $birthDate->format('Y-m-d'),
                'personId' => $match->member?->id,
                'personName' => $match->member?->getDisplayName(),
                'hint' => $match->hint?->value,
            ], '1 hour');
            $this->redirect('details');
        };

        return $form;
    }

    /**
     * Step two: a member adds only contact, answers and the agreement; a non-member also what skautIS needs.
     */
    protected function createComponentDetailsForm(): Form
    {
        $draft = $this->draft ?? throw new LogicException('The draft is loaded in the action.');
        $isMember = $draft['personId'] !== null;
        $family = $this->family();
        $event = $this->requireEvent();

        $form = $this->forms->create();
        if (! $isMember) {
            $form->addGroup('Údaje účastníka');
            $this->builder->addNonMemberFields($form, null);
        }
        $form->addGroup('Kontakt');
        $this->builder->addContactFields($form, null);
        $form->addGroup('Doplňující otázky');
        $this->builder->addCustomFields($form, $event, null);
        $form->addGroup();
        $this->builder->addAgreement($form, null, $this->unitSettings->get($event->getUnitId())->getAgreementText());
        $form->setCurrentGroup(null);
        $form->addSubmit('send', 'Odeslat přihlášku')->setOption('class', 'btn btn-primary btn-lg w-100');

        $form->setDefaults(array_filter([
            'contactEmail' => $draft['email'] ?? $family['contactEmail'] ?? null,
            'phone' => $draft['phone'] ?? $family['phone'] ?? null,
            'street' => $family['street'] ?? null,
            'city' => $family['city'] ?? null,
            'postcode' => $family['postcode'] ?? null,
        ], static fn (mixed $value): bool => $value !== null));

        $form->onSuccess[] = function (Form $form, array $values) use ($draft, $isMember): void {
            $event = $this->requireEvent();
            $answer = $this->answerFromValues($draft, $values, $isMember);
            $ip = $this->getHttpRequest()->getRemoteAddress() ?? 'unknown';

            try {
                if (($draft['skautis'] ?? false) === true) {
                    $registration = $this->service->registerViaSkautis($event, $answer, $this->personFromDraft($draft), $ip);
                } else {
                    $match = new PublicMatch(
                        $draft['personId'] === null ? null : $this->matchedPerson($event, $draft),
                        \is_string($draft['hint']) ? PairingHint::tryFrom($draft['hint']) : null,
                    );
                    $registration = $this->service->register($event, $answer, $match, $ip);
                }
            } catch (DuplicateRegistrationException|TooManyRegistrationsException|RegistrationNotAllowedException|EventFullException $exception) {
                $form->addError($exception->getMessage());

                return;
            }

            // The next child of the same family starts with these filled in.
            $this->section()->set('family', [
                'lastName' => $answer->lastName,
                'contactEmail' => $answer->contactEmail,
                'phone' => $answer->phone,
                'street' => $answer->street,
                'city' => $answer->city,
                'postcode' => $answer->postcode,
            ], '4 hours');
            $this->section()->remove('draft-'.$event->getId());
            $this->section()->set('done', $registration->getId(), '10 minutes');
            $this->redirect('done', ['id' => $registration->getId()]);
        };

        return $form;
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $values
     */
    private function answerFromValues(array $draft, array $values, bool $isMember): RegistrationAnswer
    {
        return new RegistrationAnswer(
            attending: true,
            contactEmail: RegistrationFormBuilder::string($values, 'contactEmail'),
            phone: RegistrationFormBuilder::string($values, 'phone'),
            note: RegistrationFormBuilder::string($values, 'note'),
            firstName: (string) $draft['firstName'],
            lastName: (string) $draft['lastName'],
            nickName: $isMember ? null : RegistrationFormBuilder::string($values, 'nickName'),
            sex: ! $isMember && isset($values['sex']) && \is_string($values['sex']) ? Sex::tryFrom($values['sex']) : null,
            birthDate: \is_string($draft['birthDate']) ? new DateTimeImmutable($draft['birthDate']) : null,
            street: $isMember ? null : RegistrationFormBuilder::string($values, 'street'),
            city: $isMember ? null : RegistrationFormBuilder::string($values, 'city'),
            postcode: $isMember ? null : RegistrationFormBuilder::string($values, 'postcode'),
            fieldValues: $this->builder->customFieldValues($this->requireEvent(), $values),
        );
    }

    /**
     * @param array<string, mixed> $draft
     */
    private function personFromDraft(array $draft): Person
    {
        return new Person(
            (int) $draft['personId'],
            (string) $draft['firstName'],
            (string) $draft['lastName'],
            (string) ($draft['nickName'] ?? ''),
            \is_string($draft['birthDate']) ? new DateTimeImmutable($draft['birthDate']) : null,
            \is_string($draft['sex'] ?? null) ? $draft['sex'] : null,
            null,
        );
    }

    /**
     * The member matched in step one, without asking skautIS (the public page has no token).
     *
     * @param array<string, mixed> $draft
     */
    private function matchedPerson(Event $event, array $draft): Person
    {
        return $this->service->findMember($event, (int) $draft['personId'], (string) $draft['firstName'], (string) $draft['lastName'], new DateTimeImmutable((string) $draft['birthDate']));
    }

    /**
     * @return array<string, mixed>
     */
    private function family(): array
    {
        $family = $this->section()->get('family');

        return \is_array($family) ? $family : [];
    }

    private function section(): SessionSection
    {
        return $this->getSession('registration.public');
    }

    private function load(): Event
    {
        if ($this->unitId === null || $this->slug === null || $this->t === null) {
            $this->error('Odkaz je neplatný.');
        }

        $event = $this->events->findBySlug($this->unitId, $this->slug);
        if ($event === null || ! hash_equals($event->getWebToken(), $this->t) || ! $event->getPublicRegistration()->isEnabled()) {
            $this->error('Odkaz je neplatný.');
        }

        return $this->event = $event;
    }

    private function requireEvent(): Event
    {
        return $this->event ?? throw new LogicException('The event is loaded in the action.');
    }
}
