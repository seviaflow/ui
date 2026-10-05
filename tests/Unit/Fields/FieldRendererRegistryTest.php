<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Ui\Fields\FieldRendererDefinition;
use SeviaFlow\Ui\Fields\FieldRendererRegistry;

it('resolves all default edit show and cell adapters by field type', function (): void {
    $registry = app(FieldRendererRegistry::class);

    foreach (['text', 'email', 'url', 'phone', 'slug', 'number', 'date', 'datetime', 'time', 'password', 'textarea', 'toggle', 'select', 'multiselect', 'belongs-to', 'file', 'image', 'money', 'radio-group', 'checkbox-group', 'tags', 'key-value', 'json', 'color', 'rich-text', 'repeater'] as $type) {
        $field = RegistryField::make('value');
        $field->setType($type);

        expect($registry->resolve($field, 'edit'))->toBeInstanceOf(FieldRendererDefinition::class)
            ->and($registry->resolve($field, 'show'))->toBeInstanceOf(FieldRendererDefinition::class)
            ->and($registry->resolve($field, 'cell'))->toBeInstanceOf(FieldRendererDefinition::class);
    }
});

it('allows an extension to register an adapter without changing the form partial', function (): void {
    $registry = new FieldRendererRegistry;
    $registry->register(
        'tracking-code',
        'seviaflow-ui::fields.controls.input',
        'seviaflow-ui::fields.show.value',
        'seviaflow-ui::fields.show.value',
        ['inputType' => 'text']
    );

    $field = RegistryField::make('tracking_code');
    $field->setType('tracking-code');

    expect($registry->view($field, 'edit'))->toBe('seviaflow-ui::fields.controls.input')
        ->and($registry->view($field, 'show'))->toBe('seviaflow-ui::fields.show.value')
        ->and($registry->view($field, 'cell'))->toBe('seviaflow-ui::fields.show.value');
});

it('reports a missing adapter as an integration error', function (): void {
    $field = RegistryField::make('unknown');
    $field->setType('unknown');

    expect(fn () => (new FieldRendererRegistry)->resolve($field))
        ->toThrow(LogicException::class, 'No UI field renderer is registered');
});

final class RegistryField extends Field
{
    protected static string $type = 'text';

    public function setType(string $type): void
    {
        self::$type = $type;
    }
}
