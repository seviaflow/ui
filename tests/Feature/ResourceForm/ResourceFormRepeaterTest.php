<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceForm;

require __DIR__.'/../Support/ResourceFormSetup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceFormBootstrap(), 'web');
});

it('keeps row errors and values attached to stable rows after reorder and removal', function (): void {
    $component = Livewire::test(ResourceForm::class, ['resource' => 'repeater-form-users'])
        ->call('addRepeaterItem', 'items')
        ->call('addRepeaterItem', 'items')
        ->set('fieldValues.items.0.label', 'First row')
        ->set('fieldValues.items.0.quantity', 'invalid')
        ->set('fieldValues.items.1.label', '')
        ->set('fieldValues.items.1.quantity', '2')
        ->call('save')
        ->assertHasErrors([
            'fieldValues.items.0.quantity',
            'fieldValues.items.1.label',
        ]);

    $firstRowKey = $component->instance()->repeaterUiKeys['fieldValues']['items'][0];
    $secondRowKey = $component->instance()->repeaterUiKeys['fieldValues']['items'][1];

    $component
        ->call('moveRepeaterItemUp', 'items', $secondRowKey)
        ->assertHasErrors([
            'fieldValues.items.0.label',
            'fieldValues.items.1.quantity',
        ])
        ->assertSet('fieldValues.items.0.label', '')
        ->assertSet('fieldValues.items.1.label', 'First row')
        ->assertSet('fieldValues.items.1.quantity', 'invalid');

    expect($component->instance()->repeaterUiKeys['fieldValues']['items'])
        ->toBe([$secondRowKey, $firstRowKey]);

    $component
        ->call('removeRepeaterItem', 'items', $secondRowKey)
        ->assertHasErrors(['fieldValues.items.0.quantity'])
        ->assertSet('fieldValues.items.0.label', 'First row')
        ->assertSet('fieldValues.items.0.quantity', 'invalid');

    expect($component->instance()->repeaterUiKeys['fieldValues']['items'])
        ->toBe([$firstRowKey])
        ->and($component->instance()->fieldValues['items'][0])
        ->not->toHaveKey('_ui_key');
});

it('keeps repeater rows bounded and dispatches focus after keyboard actions', function (): void {
    $component = Livewire::test(ResourceForm::class, ['resource' => 'repeater-form-users'])
        ->call('addRepeaterItem', 'items')
        ->call('addRepeaterItem', 'items')
        ->call('addRepeaterItem', 'items');

    expect($component->instance()->fieldValues['items'])->toHaveCount(3);

    $lastKey = $component->instance()->repeaterUiKeys['fieldValues']['items'][2];
    $component->call('addRepeaterItem', 'items')
        ->assertNotDispatched('seviaflow:focus-field');

    $component->call('moveRepeaterItemUp', 'items', $lastKey)
        ->assertDispatched('seviaflow:focus-field', static fn (string $event, array $params): bool => $event === 'seviaflow:focus-field'
            && str_contains((string) ($params['fieldId'] ?? ''), $lastKey)
        );
});
