<?php

declare(strict_types=1);

namespace App\Presentation\Events\Overview;

use App\Model\Event\Event;
use App\Model\Event\EventRepository;
use App\Model\Event\EventState;
use App\Presentation\SecuredPresenter;

final class OverviewPresenter extends SecuredPresenter
{
    public function __construct(private readonly EventRepository $events)
    {
        parent::__construct();
    }

    public function renderDefault(?int $rok = null, ?string $stav = null): void
    {
        $access = $this->currentUser->getAccess();
        $unitIds = array_keys($access->read);
        $years = $this->events->findYears($unitIds);
        $currentYear = (int) date('Y');
        if (! \in_array($currentYear, $years, true)) {
            $years[] = $currentYear;
            rsort($years);
        }

        $year = $rok ?? $currentYear;
        $state = $stav === null ? null : EventState::tryFrom($stav);
        $events = $this->events->findByUnits($unitIds, $year, $state);

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
