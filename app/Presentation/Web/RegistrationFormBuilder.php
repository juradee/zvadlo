<?php

declare(strict_types=1);

namespace App\Presentation\Web;

use App\Model\Event\Event;
use App\Model\Event\EventFieldType;
use App\Model\Registration\Registration;
use App\Model\Registration\Sex;
use App\Presentation\Accessory\Form\DateInput;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Controls\RadioList;

/**
 * The parts of the participant form shared by the personal link and the public sign-up:
 * personal data of a non-member, contact, the event's custom fields and the agreement.
 */
final class RegistrationFormBuilder
{
    /**
     * Who the participant is: enough to recognise a member, nothing more.
     */
    public function addIdentityFields(Form $form, ?RadioList $attending): void
    {
        $this->required($form->addText('firstName', 'Jméno')->addRule($form::MaxLength, 'Nejvýše %d znaků.', 100), $attending, 'Zadejte jméno.');
        $this->required($form->addText('lastName', 'Příjmení')->addRule($form::MaxLength, 'Nejvýše %d znaků.', 100), $attending, 'Zadejte příjmení.');
        $this->required(DateInput::add($form, 'birthDate', 'Datum narození'), $attending, 'Zadejte datum narození.');
    }

    /**
     * What skautIS needs to create a person for a non-member; members have it in skautIS already.
     */
    public function addNonMemberFields(Form $form, ?RadioList $attending): void
    {
        $form->addText('nickName', 'Přezdívka (nepovinné)')->setNullable()->addRule($form::MaxLength, 'Nejvýše %d znaků.', 100);
        $this->required($form->addRadioList('sex', 'Pohlaví', [Sex::Male->value => 'muž', Sex::Female->value => 'žena']), $attending, 'Vyberte pohlaví.');
        $this->required($form->addText('street', 'Ulice a číslo')->addRule($form::MaxLength, 'Nejvýše %d znaků.', 255), $attending, 'Zadejte ulici.');
        $this->required($form->addText('city', 'Město')->addRule($form::MaxLength, 'Nejvýše %d znaků.', 100), $attending, 'Zadejte město.');
        $this->required($form->addText('postcode', 'PSČ')->addRule($form::MaxLength, 'Nejvýše %d znaků.', 20), $attending, 'Zadejte PSČ.');
    }

    public function addPersonFields(Form $form, ?RadioList $attending): void
    {
        $this->addIdentityFields($form, $attending);
        $this->addNonMemberFields($form, $attending);
    }

    /**
     * @param bool $withEmail false for an invited member, whose mail goes to the addresses the leader chose
     */
    public function addContactFields(Form $form, ?RadioList $attending, bool $withEmail = true): void
    {
        if ($withEmail) {
            $this->required($form->addEmail('contactEmail', 'Kontaktní e-mail'), $attending, 'Zadejte e-mail.')
                ->setOption('description', 'Sem přijde potvrzení a další zprávy k akci.');
        }
        $form->addText('phone', 'Telefon (nepovinné)')->setNullable()->addRule($form::MaxLength, 'Nejvýše %d znaků.', 50);
        $form->addTextArea('note', 'Poznámka pro vedoucí (nepovinné)', null, 3)->setNullable();
    }

    /**
     * @return bool whether any field was added
     */
    public function addCustomFields(Form $form, Event $event, ?RadioList $attending): bool
    {
        $fields = $event->getVisibleFields();
        foreach ($fields as $field) {
            $name = 'f'.$field->getId();
            $options = [];
            foreach ($field->getOptions() as $option) {
                $options[$option->getId()] = $option->getLabel();
            }
            $control = match ($field->getType()) {
                EventFieldType::Text => $form->addText($name, $field->getLabel())->setNullable(),
                EventFieldType::Boolean => $form->addCheckbox($name, $field->getLabel()),
                EventFieldType::Choice => $form->addRadioList($name, $field->getLabel(), $options),
                EventFieldType::MultiChoice => $form->addCheckboxList($name, $field->getLabel(), $options),
            };
            if ($field->getHelp() !== null) {
                $control->setOption('description', $field->getHelp());
            }
            if ($field->isRequired()) {
                // A required yes/no field is a confirmation: it has to be ticked.
                $this->required($control, $attending, $field->getType() === EventFieldType::Boolean ? 'Toto pole je třeba zaškrtnout.' : 'Toto pole je povinné.');
            }
        }

        return $fields !== [];
    }

    public function addAgreement(Form $form, ?RadioList $attending, string $text): void
    {
        $this->required(
            $form->addCheckbox('agreement', $text),
            $attending,
            'Bez potvrzení nelze přihlášku odeslat.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function customFieldDefaults(Registration $registration): array
    {
        $defaults = [];
        foreach ($registration->getEvent()->getVisibleFields() as $field) {
            $defaults['f'.$field->getId()] = match ($field->getType()) {
                EventFieldType::Text => $registration->getTextValue($field),
                EventFieldType::Boolean => $registration->getBooleanValue($field) ?? false,
                EventFieldType::Choice => ($registration->getSelectedOptions($field)[0] ?? null)?->getId(),
                EventFieldType::MultiChoice => array_map(static fn ($option): int => $option->getId(), $registration->getSelectedOptions($field)),
            };
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<int, mixed> custom field values keyed by field id
     */
    public function customFieldValues(Event $event, array $values): array
    {
        $fieldValues = [];
        foreach ($event->getVisibleFields() as $field) {
            $name = 'f'.$field->getId();
            if (\array_key_exists($name, $values)) {
                $fieldValues[$field->getId()] = $values[$name];
            }
        }

        return $fieldValues;
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function string(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return \is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function required(BaseControl $control, ?RadioList $attending, string $message): BaseControl
    {
        if ($attending === null) {
            $control->setRequired($message);
        } else {
            $control->addConditionOn($attending, Form::Equal, 'yes')->setRequired($message);
        }

        return $control;
    }
}
