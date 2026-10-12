<?php

declare(strict_types=1);

namespace App\Presentation\Events\Overview;

use App\Model\Event\Event;
use App\Model\Event\EventRepository;
use App\Model\Event\EventState;
use App\Presentation\SecuredPresenter;
use DateTimeImmutable;

final class OverviewPresenter extends SecuredPresenter
{
    public function __construct(private readonly EventRepository $events)
    {
        parent::__construct();
    }

    /**
     * Without a year: the upcoming and running events, whatever year they fall in (an event on 1 January must
     * not hide behind the year switch); a year shows that year's events including the past ones.
     */
    public function renderDefault(?string $rok = null, ?string $stav = null): void
    {
        // The year switch sends rok= for "nadcházející"; anything but a year means the upcoming view.
        $rok = $rok !== null && ctype_digit($rok) ? (int) $rok : null;
        $access = $this->currentUser->getAccess();
        $unitIds = array_keys($access->read);
        $years = $this->events->findYears($unitIds);

        $state = $stav === null ? null : EventState::tryFrom($stav);
        $events = $rok === null
            ? array_values(array_filter(
                $this->events->findUpcoming($unitIds, new DateTimeImmutable()),
                static fn (Event $event): bool => $state === null || $event->getState() === $state,
            ))
            : $this->events->findByUnits($unitIds, $rok, $state);
        $year = $rok;

        $this->template->events = $events;
        $this->template->units = $access->read;
        $this->template->editableUnits = $access->edit;
        $this->template->years = $years;
        $this->template->year = $year;
        $this->template->state = $state;
        $this->template->states = EventState::cases();
        $this->template->overlaps = $this->findOverlaps($events);
    }

    /**
     * Names of other events of the same units that share days with the event, keyed by event id.
     *
     * @param list<Event> $events
     *
     * @return array<int, list<string>>
     */
    private function findOverlaps(array $events): array
    {
        $overlaps = [];
        foreach ($events as $event) {
            foreach ($events as $other) {
                if ($other !== $event && $event->overlaps($other)) {
                    $overlaps[$event->getId()][] = $other->getName();
                }
            }
        }

        return $overlaps;
    }
}
