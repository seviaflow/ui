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

it('redirects create to the canonical record route', function (): void {
    $component = Livewire::test(ResourceForm::class, ['resource' => 'form-users'])
        ->set('fieldValues.name', 'Canonical Create')
        ->call('saveAndView');

    $record = FormUser::query()->where('name', 'Canonical Create')->firstOrFail();

    $component->assertRedirect(route('seviaflow.resources.show', [
        'resource' => 'form-users',
        'record' => $record->getKey(),
    ]));
});

it('keeps dirty state for real changes and clears it after save', function (): void {
    $component = Livewire::test(ResourceForm::class, ['resource' => 'form-users'])
        ->assertSet('isDirty', false)
        ->set('fieldValues.name', 'Dirty value')
        ->assertSet('isDirty', true)
        ->call('save')
        ->assertSet('isDirty', false)
        ->assertSee('Record saved successfully.');

    $component->set('fieldValues.name', '')->call('save')->assertSet('isDirty', true);
});

it('generates a slug until the operator overrides it', function (): void {
    $component = Livewire::test(ResourceForm::class, ['resource' => 'slug-form-users'])
        ->set('fieldValues.name', 'A New Company')
        ->assertSet('fieldValues.slug', 'a-new-company')
        ->set('fieldValues.slug', 'manual-slug')
        ->set('fieldValues.name', 'Another Company')
        ->assertSet('fieldValues.slug', 'manual-slug');
});
