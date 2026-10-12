<?php

declare(strict_types=1);

namespace App\Presentation\Accessory\Form;

use DateTimeImmutable;
use DateTimeInterface;
use Nette\Forms\Container;
use Nette\Forms\Control;
use Nette\Forms\Controls\TextInput;
use Nette\Forms\Form;
use Nette\Utils\Html;

/**
 * A date typed as dd.mm.rrrr. The native date input shows the browser's format (mm/dd/yyyy in an English
 * Chrome), which the page cannot change, while Zvadlo shows dates as dd.mm.rrrr everywhere (decision 32).
 * The value is a DateTimeImmutable at midnight, or null when empty or not a real date.
 */
final class DateInput extends TextInput
{
    public const string FORMAT = 'd.m.Y';
    public const string MESSAGE = 'Zadejte platné datum ve tvaru dd.mm.rrrr, například 05.02.2027.';
    private const string PATTERN = '\s*\d{1,2}\s*[.,]\s*\d{1,2}\s*[.,]\s*\d{4}\s*';

    public function __construct(?string $label = null)
    {
        parent::__construct($label, 10);
        $this->setHtmlAttribute('placeholder', 'dd.mm.rrrr')
            ->setHtmlAttribute('inputmode', 'decimal');
        // Checked on the server: the built-in pattern rule would read getValue(), which is a date, not the text.
        $this->addCondition(Form::Filled)
            ->addRule(static fn (Control $control): bool => $control instanceof self && $control->getValue() !== null, self::MESSAGE);
    }

    /**
     * The text field with a calendar button; the button opens the browser's own date picker on a hidden
     * native input (frontend/ts/datePicker.ts), which writes the picked day back as dd.mm.rrrr.
     */
    public function getControl(): Html
    {
        $input = parent::getControl();
        $picker = Html::el('div', ['class' => 'input-group', 'data-date-picker' => true]);
        $picker->addHtml($input);
        $picker->addHtml(Html::el('button', [
            'type' => 'button',
            'class' => 'btn btn-outline-secondary',
            'data-date-picker-open' => true,
            'title' => 'Vybrat v kalendáři',
            'aria-label' => 'Vybrat datum v kalendáři',
            'disabled' => $this->isDisabled(),
        ])->addHtml(Html::el('i', ['class' => 'bi bi-calendar3', 'aria-hidden' => 'true'])));
        $picker->addHtml(Html::el('input', [
            'type' => 'date',
            'class' => 'date-picker-native',
            'tabindex' => '-1',
            'aria-hidden' => 'true',
            'data-date-picker-native' => true,
        ]));

        return $picker;
    }

    public static function add(Container $container, string $name, string $label): self
    {
        $control = new self($label);
        $container->addComponent($control, $name);

        return $control;
    }

    /**
     * @param DateTimeInterface|string|null $value
     */
    public function setValue($value): static
    {
        return parent::setValue($value instanceof DateTimeInterface ? $value->format(self::FORMAT) : $value);
    }

    public function getValue(): ?DateTimeImmutable
    {
        $raw = parent::getValue();

        return \is_string($raw) ? self::parse($raw) : null;
    }

    /**
     * Something was typed, even an invalid date: "required" must not hide the format message.
     */
    public function isFilled(): bool
    {
        $raw = parent::getValue();

        return \is_string($raw) && trim($raw) !== '';
    }

    public static function parse(string $value): ?DateTimeImmutable
    {
        if (preg_match('~^'.self::PATTERN.'$~', $value) !== 1) {
            return null;
        }
        [$day, $month, $year] = array_map(intval(...), preg_split('~\s*[.,]\s*~', trim($value)) ?: []);
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return new DateTimeImmutable(\sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day));
    }
}
