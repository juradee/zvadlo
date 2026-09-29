<?php

declare(strict_types=1);

namespace App\Presentation\Home;

use App\Presentation\BasePresenter;

final class HomePresenter extends BasePresenter
{
    public function actionDefault(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect(':Dashboard:default');
        }
    }
}
