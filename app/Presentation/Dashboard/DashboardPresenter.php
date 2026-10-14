<?php

declare(strict_types=1);

namespace App\Presentation\Dashboard;

use App\Model\Event\EventRepository;
use App\Presentation\SecuredPresenter;
use DateTimeImmutable;

final class DashboardPresenter extends SecuredPresenter
{
    public function __construct(
        private readonly EventRepository $events,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $access = $this->currentUser->getAccess();

        $this->template->account = $this->currentUser->getUser();
        $this->template->role = $this->currentUser->getRole();
        $this->template->readUnits = $access->read;
        $this->template->editUnits = $access->edit;
        $this->template->upcoming = $this->events->findUpcoming(array_keys($access->read), new DateTimeImmutable(), 5);
    }
}
