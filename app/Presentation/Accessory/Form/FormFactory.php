<?php

declare(strict_types=1);

namespace App\Presentation\Accessory\Form;

use Nette\Application\UI\Form;

final class FormFactory
{
    public function create(): Form
    {
        $form = new Form();
        $form->setRenderer(new BootstrapFormRenderer());

        return $form;
    }
}
