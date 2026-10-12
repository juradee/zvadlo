<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Dashboard;

use App\Presentation\Admin\AdminBasePresenter;

final class DashboardPresenter extends AdminBasePresenter
{
    protected function hasAccess(): bool
    {
        return $this->adminAccess->canAccessAdministration();
    }
}
