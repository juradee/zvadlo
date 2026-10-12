<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation;

use App\Presentation\Accessory\Form\DateInput;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Nette\Forms\Form;

final class DateInputTest extends Unit
{
    public function testCzechFormatIsParsedAndRendered(): void
    {
        self::assertEquals(new DateTimeImmutable('2027-02-05'), DateInput::parse('05.02.2027'));
        self::assertEquals(new DateTimeImmutable('2027-02-05'), DateInput::parse(' 5. 2. 2027 '), 'single digits and spaces are fine');
        self::assertEquals(new DateTimeImmutable('2027-02-05'), DateInput::parse('5,2,2027'), 'a comma from the decimal keypad of a phone');
        foreach (['31.02.2027', '02/05/2027', '2027-02-05', '5.2.27', 'zítra', ''] as $invalid) {
            self::assertNull(DateInput::parse($invalid), $invalid);
        }

        $form = new Form();
        $date = DateInput::add($form, 'date', 'Datum');
        $date->setValue(new DateTimeImmutable('2027-02-05 15:30'));
        $html = (string) $date->getControl();
        self::assertStringContainsString('value="05.02.2027"', $html);
        self::assertStringContainsString('placeholder="dd.mm.rrrr"', $html);
        self::assertStringContainsString('data-date-picker-open', $html, 'a calendar button next to the field');
        self::assertStringContainsString('type="date"', $html, 'the hidden native picker');
        self::assertStringNotContainsString('autocomplete="off"', $html, 'the browser may suggest earlier values');
        self::assertEquals(new DateTimeImmutable('2027-02-05'), $date->getValue(), 'midnight, the time is dropped');
    }

    public function testInvalidDateReportsTheFormatNotRequired(): void
    {
        $form = new Form();
        $date = DateInput::add($form, 'date', 'Datum')->setRequired('Zadejte datum.');
        $date->setValue('31.02.2027');

        self::assertTrue($date->isFilled());
        self::assertNull($date->getValue());
        $date->validate();
        self::assertSame([DateInput::MESSAGE], $date->getErrors());

        $date->cleanErrors();
        $date->setValue('');
        $date->validate();
        self::assertSame(['Zadejte datum.'], $date->getErrors());
    }
}
