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

it('localizes form controls and validates before saving without translating data', function (string $locale, string $create, string $error, string $saved): void {
    app()->setLocale($locale);

    $form = Livewire::test(ResourceForm::class, ['resource' => 'flat-form-users'])
        ->assertSee($create)
        ->assertSee('wire:submit="saveAndView"', false)
        ->assertSee('data-sf-form-leave-message="'.e(__('seviaflow-ui::panel.leave_this_form_unsaved_changes_will_be_lost')).'"', false)
        ->call('save')
        ->assertHasErrors(['fieldValues.name' => 'required'])
        ->assertSee($error);

    expect(FormUser::query()->count())->toBe(0);

    $form->set('fieldValues.name', 'Save')
        ->assertSet('isDirty', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('statusMessage', $saved)
        ->assertSet('isDirty', false);

    expect(FormUser::query()->sole()->name)->toBe('Save');
})->with([
    'English' => ['en', 'Create record', '1 validation error.', 'Record saved successfully.'],
    'Polish' => ['pl', 'Dodaj rekord', 'Błąd walidacji: 1.', 'Zapisano rekord.'],
]);

it('retains native edit and save-another behavior in both locales', function (string $locale): void {
    app()->setLocale($locale);
    $record = FormUser::query()->create(['name' => 'Original']);

    Livewire::test(ResourceForm::class, ['resource' => 'flat-form-users', 'record' => $record->getKey()])
        ->assertSee(__('seviaflow-ui::panel.edit_record'))
        ->set('fieldValues.name', '<script>Save</script>')
        ->assertSee('&lt;script&gt;Save&lt;/script&gt;', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($record->fresh()?->name)->toBe('<script>Save</script>');

    Livewire::test(ResourceForm::class, ['resource' => 'flat-form-users'])
        ->set('fieldValues.name', 'Create')
        ->call('saveAndCreateAnother')
        ->assertHasNoErrors()
        ->assertSet('isEditMode', false)
        ->assertSet('fieldValues.name', null)
        ->assertSet('statusMessage', __('seviaflow-ui::panel.record_saved_ready_for_another_record'));
})->with(['en', 'pl']);

it('keeps application field labels and configured validation translations', function (): void {
    app()->setLocale('pl');
    app('translator')->addLines(['validation.required' => 'Pole :attribute jest wymagane.'], 'pl');

    Livewire::test(ResourceForm::class, ['resource' => 'flat-form-users'])
        ->assertSee('Name')
        ->call('save')
        ->assertHasErrors(['fieldValues.name' => 'required'])
        ->assertSee('Pole name jest wymagane.');
});
