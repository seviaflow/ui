<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Fields\CheckboxGroupField;
use SeviaFlow\Core\Resources\Fields\ColorField;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\JsonField;
use SeviaFlow\Core\Resources\Fields\KeyValueField;
use SeviaFlow\Core\Resources\Fields\MoneyField;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\RadioGroupField;
use SeviaFlow\Core\Resources\Fields\RepeaterField;
use SeviaFlow\Core\Resources\Fields\RichTextField;
use SeviaFlow\Core\Resources\Fields\SelectField;
use SeviaFlow\Core\Resources\Fields\TagsField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Ui\Fields\FieldRendererRegistry;

it('renders simple field edit and display adapters without unsafe interpolation', function (): void {
    /** @var array<int, Field> $fields */
    $fields = [
        RadioGroupField::make('kind')->options(['person' => 'Person']),
        CheckboxGroupField::make('channels')->options(['email' => 'Email']),
        TagsField::make('tags'),
        KeyValueField::make('metadata'),
        JsonField::make('payload'),
        ColorField::make('color'),
        RichTextField::make('description'),
        RepeaterField::make('items')->fields([
            TextField::make('label'),
            NumberField::make('quantity'),
            MoneyField::make('amount'),
            SelectField::make('kind')->options(['service' => 'Service']),
            ToggleField::make('enabled'),
        ]),
    ];
    $registry = app(FieldRendererRegistry::class);

    foreach ($fields as $field) {
        $inputValue = match (true) {
            $field instanceof RadioGroupField => '',
            $field instanceof CheckboxGroupField => [],
            $field instanceof TagsField => '',
            $field instanceof KeyValueField => '{}',
            $field instanceof JsonField => '{}',
            default => '#AABBCC',
        };
        if ($field instanceof RichTextField) {
            $inputValue = '<div data-wrapper="unsafe"><span onclick="alert(1)"><p onmouseover="alert(2)">'
                .'Zażółć <strong>gęślą</strong></p><script>alert(3)</script></span></div>';
        }
        if ($field instanceof RepeaterField) {
            $inputValue = [[
                'label' => 'Usługa',
                'quantity' => 2,
                'amount' => '9.50',
                'kind' => 'service',
                'enabled' => true,
            ]];
        }
        $data = [
            'field' => $field,
            'context' => ResourceContext::Create,
            'fieldPath' => 'fieldValues.'.$field->name(),
            'fieldId' => 'field-'.$field->name(),
            'inputValue' => $inputValue,
            'modelValues' => [$field->name() => $inputValue],
            'repeaterUiKeys' => ['fieldValues' => ['items' => ['repeater-test-row']]],
            'describedBy' => '',
            'hasError' => false,
            'isRequired' => false,
            'isDisabled' => false,
            'isReadonly' => false,
        ];

        $editHtml = view($registry->view($field, 'edit'), $data)->render();
        $showHtml = view($registry->view($field, 'show'), $data + ['value' => $inputValue])->render();

        expect($editHtml)->not->toContain('Array')
            ->and($showHtml)->not->toContain('<script>')
            ->and($showHtml)->not->toContain('onclick')
            ->and($showHtml)->not->toContain('onmouseover');
    }
});
