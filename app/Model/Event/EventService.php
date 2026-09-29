<?php

declare(strict_types=1);

namespace App\Model\Event;

use DateTimeImmutable;

/**
 * Use cases of a leader around one event; presenters call this, never the entities directly.
 */
final class EventService
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly SlugGenerator $slugs,
    ) {
    }

    public function create(int $unitId, EventInput $input, int $userId): Event
    {
        $event = new Event($unitId, $this->slugs->generate($unitId, $input->name), $input, self::token(), $userId, new DateTimeImmutable());
        $this->events->add($event);

        return $event;
    }

    public function update(Event $event, EventInput $input): void
    {
        $event->update($input, new DateTimeImmutable());
        $this->events->save();
    }

    /**
     * @throws InvalidStateTransitionException
     */
    public function changeState(Event $event, EventState $state): void
    {
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

    public function updateField(EventField $field, EventFieldInput $input): void
    {
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

    public function removeField(EventField $field): void
    {
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
