<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceForm;

require __DIR__.'/../Support/ResourceFormSetup.php';

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceFormBootstrap(), 'web');
});

it('keeps a selected belongsTo record visible outside the first option page', function (): void {
    $companies = [];
    foreach (range(1, 5) as $index) {
        $companies[] = BelongsToFormCompany::query()->create(['name' => 'Catalog company '.$index]);
    }

    $user = BelongsToFormUser::query()->create([
        'name' => 'Selected outside page',
        'company_id' => $companies[4]->getKey(),
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'belongs-to-users', 'record' => $user->getKey()])
        ->assertSee('Catalog company 5')
        ->assertSee('role="combobox"', false)
        ->assertSee('role="listbox"', false)
        ->assertDontSee('Catalog company 3');
});

it('renders multiselect selections as removable chips without a native multiple select', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'multiselect-form-users'])
        ->set('fieldValues.name', 'Chip user')
        ->set('fieldValues.channels', ['email', 'sms'])
        ->assertSee('Email')
        ->assertSee('SMS')
        ->assertSee('Remove Email')
        ->assertSee('role="listbox"', false)
        ->assertDontSee('<select', false);
});

it('shows existing file metadata and image preview in the edit form', function (): void {
    Storage::fake('public');
    $documentPath = 'documents/report.pdf';
    $avatarPath = 'avatars/avatar.jpg';
    Storage::disk('public')->put($documentPath, 'pdf contents');
    Storage::disk('public')->put($avatarPath, 'image contents');

    $user = FormUser::query()->create([
        'name' => 'Existing files',
        'document_path' => $documentPath,
        'avatar_path' => $avatarPath,
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'upload-form-users', 'record' => $user->getKey()])
        ->assertSee('report.pdf')
        ->assertSee('application/pdf')
        ->assertSee('Remove current file')
        ->assertSee('sf-file-preview', false);
});
