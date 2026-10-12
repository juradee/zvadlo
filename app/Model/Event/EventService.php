<?php

declare(strict_types=1);

namespace App\Model\Event;

use App\Model\Mailing\EmailTemplateProvider;
use App\Model\Mailing\GoogleOAuth;
use App\Model\Mailing\MailerFactory;
use App\Model\Registration\FieldInUseException;
use App\Model\Registration\RegistrationRepository;
use App\Model\Skautis\Dto\Camp;
use DateTimeImmutable;

/**
 * Use cases of a leader around one event; presenters call this, never the entities directly.
 */
final class EventService
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly SlugGenerator $slugs,
        private readonly RegistrationRepository $registrations,
        private readonly EmailTemplateProvider $templates,
        private readonly MailerFactory $mailers,
    ) {
    }

    public function create(int $unitId, EventInput $input, int $userId, ?GoogleOAuth $mailAccount = null): Event
    {
        $now = new DateTimeImmutable();
        $event = new Event($unitId, $this->slugs->generate($unitId, $input->name), $input, self::token(), $userId, $now);
        $event->seedEmails($this->templates->forUnit($unitId));
        $event->setMailAccount($mailAccount, $now);
        $this->events->add($event);

        return $event;
    }

    public function createFromCamp(Camp $camp, EventInput $input, int $userId, ?GoogleOAuth $mailAccount = null): Event
    {
        $now = new DateTimeImmutable();
        $event = new Event($camp->unitId, $this->slugs->generate($camp->unitId, $camp->name), $input, self::token(), $userId, $now);
        $event->linkSkautisCamp($camp, $now);
        $event->seedEmails($this->templates->forUnit($camp->unitId));
        $event->setMailAccount($mailAccount, $now);
        $this->events->add($event);

        return $event;
    }

    public function update(Event $event, EventInput $input, ?GoogleOAuth $mailAccount = null): void
    {
        $now = new DateTimeImmutable();
        $event->update($input, $now);
        $event->setMailAccount($mailAccount, $now);
        $this->events->save();
    }

    public function updateEmail(EventEmail $email, bool $enabled, string $subject, string $body): void
    {
        $email->edit($enabled, $subject, $body);
        $this->events->save();
    }

    public function refreshFromCamp(Event $event, Camp $camp): void
    {
        $event->updateFromCamp($camp, new DateTimeImmutable());
        $this->events->save();
    }

    /**
     * @throws InvalidStateTransitionException
     * @throws MissingMailAccountException     when opening an event that has no Gmail account to send from
     */
    public function changeState(Event $event, EventState $state): void
    {
        if ($state === EventState::Open && $event->getMailAccount() === null && $this->mailers->requiresAccount()) {
            throw new MissingMailAccountException('Akci nelze otevřít bez odesílacího e-mailového účtu. Propojte Gmail účet v nastavení jednotky a vyberte ho u akce.');
        }

        $event->changeState($state, new DateTimeImmutable());
        $this->events->save();
    }

    public function regenerateWebToken(Event $event): void
    {
        $event->regenerateWebToken(self::token(), new DateTimeImmutable());
        $this->events->save();
    }

    public function addField(Event $event, EventFieldInput $input): EventField
    {
        $position = \count($event->getFields()) + 1;
        $field = new EventField($event, $input->type, $input->label, $input->help, $input->required, $position, $input->options);
        $event->addField($field);
        $this->events->save();

        return $field;
    }

    /**
     * @throws FieldInUseException when the options would change under existing answers
     */
    public function updateField(EventField $field, EventFieldInput $input): void
    {
        if ($field->getType()->hasOptions() && $field->getOptionLabels() !== $input->options && $this->registrations->hasValuesForField($field)) {
            throw new FieldInUseException('Pole už má vyplněné odpovědi, možnosti nelze měnit. Pole můžete skrýt a přidat nové.');
        }

        $field->edit($input->label, $input->help, $input->required, $input->options);
        $this->events->save();
    }

    public function setFieldHidden(EventField $field, bool $hidden): void
    {
        $field->setHidden($hidden);
        $this->events->save();
    }

    /**
     * Moves the field one step up (-1) or down (+1) by swapping positions with its neighbour.
     */
    public function moveField(EventField $field, int $direction): void
    {
        $fields = $field->getEvent()->getFields();
        $index = array_search($field, $fields, true);
        $neighbour = $fields[$index + $direction] ?? null;
        if ($index === false || $neighbour === null) {
            return;
        }

        $position = $field->getPosition();
        $field->setPosition($neighbour->getPosition());
        $neighbour->setPosition($position);
        $this->events->save();
    }

    /**
     * @throws FieldInUseException when the field already holds answers
     */
    public function removeField(EventField $field): void
    {
        if ($this->registrations->hasValuesForField($field)) {
            throw new FieldInUseException('Pole už má vyplněné odpovědi, nelze ho smazat. Místo toho ho skryjte.');
        }

        $field->getEvent()->removeField($field);
        $this->events->save();
    }

    /**
     * 32 random bytes as base64url, 43 characters.
     */
    public static function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
