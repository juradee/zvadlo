<?php

declare(strict_types=1);

namespace App\Presentation\Accessory\Form;

use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Controls\Button;
use Nette\Forms\Controls\Checkbox;
use Nette\Forms\Controls\CheckboxList;
use Nette\Forms\Controls\DateTimeControl;
use Nette\Forms\Controls\MultiSelectBox;
use Nette\Forms\Controls\RadioList;
use Nette\Forms\Controls\SelectBox;
use Nette\Forms\Controls\TextBase;
use Nette\Forms\Controls\UploadControl;
use Nette\Forms\Form;
use Nette\Forms\Rendering\DefaultFormRenderer;

/**
 * Renders Nette forms with Bootstrap 5 markup.
 */
final class BootstrapFormRenderer extends DefaultFormRenderer
{
    public function __construct()
    {
        $this->wrappers['controls']['container'] = null;
        $this->wrappers['pair']['container'] = 'div class="mb-3"';
        $this->wrappers['pair']['.error'] = 'has-error';
        $this->wrappers['control']['container'] = null;
        $this->wrappers['label']['container'] = null;
        $this->wrappers['control']['description'] = 'div class="form-text"';
        $this->wrappers['control']['errorcontainer'] = 'div class="invalid-feedback d-block"';
        $this->wrappers['control']['erroritem'] = '';
        $this->wrappers['error']['container'] = 'div class="alert alert-danger"';
        $this->wrappers['error']['item'] = 'p class="mb-0"';
        $this->wrappers['hidden']['container'] = null;
    }

    public function render(Form $form, ?string $mode = null): string
    {
        foreach ($form->getControls() as $control) {
            if (! $control instanceof BaseControl) {
                continue;
            }

            if ($control instanceof Button) {
                $class = $control->getOption('class');
                $control->getControlPrototype()->addClass(\is_string($class) ? $class : 'btn btn-primary');
            } elseif ($control instanceof Checkbox) {
                $control->getControlPrototype()->addClass('form-check-input');
                $control->getLabelPrototype()->addClass('form-check-label');
                $control->getSeparatorPrototype()->setName('div')->addClass('form-check');
            } elseif ($control instanceof CheckboxList || $control instanceof RadioList) {
                $control->getControlPrototype()->addClass('form-check-input');
                $control->getItemLabelPrototype()->addClass('form-check-label');
                $control->getSeparatorPrototype()->setName('div')->addClass('form-check');
                $control->getLabelPrototype()->addClass('form-label');
            } elseif ($control instanceof SelectBox || $control instanceof MultiSelectBox) {
                $control->getControlPrototype()->addClass('form-select');
                $control->getLabelPrototype()->addClass('form-label');
            } elseif ($control instanceof TextBase || $control instanceof DateTimeControl || $control instanceof UploadControl) {
                $control->getControlPrototype()->addClass('form-control');
                $control->getLabelPrototype()->addClass('form-label');
            }

            if ($control->hasErrors()) {
                $control->getControlPrototype()->addClass('is-invalid');
            }
        }

        return parent::render($form, $mode);
    }
}
