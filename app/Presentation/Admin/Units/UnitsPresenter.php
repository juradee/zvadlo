<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Units;

use App\Model\Admin\Statistics;
use App\Presentation\Admin\AdminBasePresenter;

final class UnitsPresenter extends AdminBasePresenter
{
    public function __construct(private readonly Statistics $statistics)
    {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $this->template->units = $this->statistics->units();
    }
}
