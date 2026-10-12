<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Statistics;

use App\Model\Admin\Statistics;
use App\Presentation\Admin\AdminBasePresenter;

final class StatisticsPresenter extends AdminBasePresenter
{
    public function __construct(private readonly Statistics $statistics)
    {
        parent::__construct();
    }

    public function renderDefault(?int $rok = null): void
    {
        $year = $rok ?? (int) date('Y');
        $this->template->year = $year;
        $this->template->years = range((int) date('Y'), 2026);
        $this->template->totals = $this->statistics->totals();
        $this->template->months = $this->statistics->perMonth($year);
    }
}
