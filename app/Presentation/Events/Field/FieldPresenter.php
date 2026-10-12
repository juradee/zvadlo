<?php

declare(strict_types=1);

namespace App\Presentation\Events\Field;

use App\Model\Event\Event;
use App\Model\Event\EventField;
use App\Model\Event\EventFieldInput;
use App\Model\Event\EventFieldType;
use App\Model\Event\EventRepository;
use App\Model\Event\EventService;
use App\Model\Registration\FieldInUseException;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\SecuredPresenter;
use LogicException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Nette\Forms\Controls\SubmitButton;

/**
 * Custom fields of the registration form of one event.
 */
final class FieldPresenter extends SecuredPresenter
{
    private ?Event $event = null;

    private ?EventField $editedField = null;

    public function __construct(
        private readonly EventRepository $events,
        private readonly EventService $service,
        private readonly FormFactory $forms,
    ) {
        parent::__construct();
    }

    public function actionDefault(int $id, ?int $pole = null): void
    {
        $event = $this->events->find($id);
        if ($event === null) {
            $this->error('Akce nebyla nalezena.');
        }
        if (! $this->authorizator->canEditUnit($event->getUnitId())) {
            $this->error('K této akci nemáte oprávnění.', 403);
        }

        $this->event = $event;
        $this->editedField = $pole === null ? null : $this->findField($pole);
    }

    public function renderDefault(): void
    {
        $this->template->event = $this->requireEvent();
        $this->template->fields = $this->requireEvent()->getFields();
        $this->template->editedField = $this->editedField;
    }

    protected function createComponentFieldForm(): Form
    {
        $edited = $this->editedField;
        $types = [];
        foreach (EventFieldType::cases() as $type) {
            $types[$type->value] = $type->label();
        }

        $form = $this->forms->create();
        $form->addGroup();
        $typeSelect = $form->addSelect('type', 'Typ pole', $types)->setRequired();
        if ($edited !== null) {
            $typeSelect->setDisabled()->setDefaultValue($edited->getType()->value);
        }
        $form->addText('label', 'Popisek')
            ->setRequired('Zadejte popisek pole.')
            ->addRule($form::MaxLength, 'Popisek může mít nejvýše %d znaků.', 255);
        $form->addText('help', 'Nápověda')
            ->setNullable()
            ->addRule($form::MaxLength, 'Nápověda může mít nejvýše %d znaků.', 500);
        $form->addCheckbox('required', 'Povinné pole')
            ->setOption('description', 'Účastník musí odpovědět; u pole ano / ne musí být zaškrtnuté (potvrzení).');
        // The options box shows only for the two choice types.
        $typeSelect->addCondition($form::IsIn, [EventFieldType::Choice->value, EventFieldType::MultiChoice->value])->toggle('options-box');
        $form->addGroup()->setOption('id', 'options-box');
        $options = $form->addTextArea('options', 'Možnosti', null, 4)
            ->setNullable()
            ->setOption('description', 'Každá možnost na samostatný řádek.');
        $form->setCurrentGroup(null);
        $form->addSubmit('save', $edited === null ? 'Přidat pole' : 'Uložit pole');

        if ($edited !== null) {
            $form->setDefaults([
                'label' => $edited->getLabel(),
                'help' => $edited->getHelp(),
                'required' => $edited->isRequired(),
                'options' => implode("\n", $edited->getOptionLabels()),
            ]);
        }

        $form->onValidate[] = static function (Form $form) use ($edited, $options): void {
            $type = $edited?->getType() ?? EventFieldType::from((string) $form->getValues()['type']);
            if ($type->hasOptions() && self::parseOptions($form->getValues()['options']) === []) {
                $options->addError('Zadejte alespoň jednu možnost.');
            }
        };

        $form->onSuccess[] = function (Form $form, array $values) use ($edited): void {
            $type = $edited?->getType() ?? EventFieldType::from((string) $values['type']);
            $input = new EventFieldInput(
                type: $type,
                label: (string) $values['label'],
                help: $values['help'] === null ? null : (string) $values['help'],
                required: (bool) $values['required'],
                options: self::parseOptions($values['options']),
            );

            try {
                if ($edited === null) {
                    $this->service->addField($this->requireEvent(), $input);
                    $this->flashMessage('Pole bylo přidáno.', 'success');
                } else {
                    $this->service->updateField($edited, $input);
                    $this->flashMessage('Pole bylo uloženo.', 'success');
                }
            } catch (FieldInUseException $exception) {
                $this->flashMessage($exception->getMessage(), 'danger');
            }

            $this->redirect('default', ['id' => $this->requireEvent()->getId()]);
        };

        return $form;
    }

    /**
     * One small form per field with the row actions; buttons carry the intent, so nothing changes on GET.
     */
    /**
     * @return Multiplier<Form>
     */
    protected function createComponentFieldActions(): Multiplier
    {
        return new Multiplier(function (string $fieldId): Form {
            $field = $this->findField((int) $fieldId);
            $form = $this->forms->create();
            $form->addSubmit('up', '↑')->setOption('class', 'btn btn-sm btn-outline-secondary');
            $form->addSubmit('down', '↓')->setOption('class', 'btn btn-sm btn-outline-secondary');
            $form->addSubmit('toggle', $field->isHidden() ? 'Zobrazit' : 'Skrýt')->setOption('class', 'btn btn-sm btn-outline-secondary');
            $form->addSubmit('remove', 'Smazat')->setOption('class', 'btn btn-sm btn-outline-danger');

            $form->onSuccess[] = function (Form $form) use ($field): void {
                $button = $form->isSubmitted();
                $action = $button instanceof SubmitButton ? (string) $button->getName() : '';
                try {
                    match ($action) {
                        'up' => $this->service->moveField($field, -1),
                        'down' => $this->service->moveField($field, 1),
                        'toggle' => $this->service->setFieldHidden($field, ! $field->isHidden()),
                        'remove' => $this->service->removeField($field),
                        default => null,
                    };
                } catch (FieldInUseException $exception) {
                    $this->flashMessage($exception->getMessage(), 'danger');
                }
                $this->redirect('default', ['id' => $this->requireEvent()->getId()]);
            };

            return $form;
        });
    }

    /**
     * @return list<string>
     */
    private static function parseOptions(mixed $text): array
    {
        if (! \is_string($text)) {
            return [];
        }

        $options = array_map(trim(...), explode("\n", $text));

        return array_values(array_filter($options, static fn (string $option): bool => $option !== ''));
    }

    private function findField(int $fieldId): EventField
    {
        foreach ($this->requireEvent()->getFields() as $field) {
            if ($field->getId() === $fieldId) {
                return $field;
            }
        }

        $this->error('Pole nebylo nalezeno.');
    }

    private function requireEvent(): Event
    {
        return $this->event ?? throw new LogicException('The event is loaded in the action.');
    }
}
