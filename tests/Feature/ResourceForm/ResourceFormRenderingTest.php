<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceForm;

require __DIR__.'/../Support/ResourceFormSetup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceFormBootstrap(), 'web');
});

it('create form renders', function (): void {
    pest_test_case()->get('/admin/resources/form-users/create')
        ->assertOk()
        ->assertSeeLivewire(ResourceForm::class)
        ->assertSee('sf-card', false)
        ->assertSee('sf-card-header', false)
        ->assertSee('sf-card-body', false)
        ->assertSee('sf-input', false)
        ->assertSee('Create')
        ->assertSee('Cancel')
        ->assertSee('sf-button sf-button-primary', false)
        ->assertSee('sf-button sf-button-secondary', false);
});

it('renders section labels and descriptions', function (): void {
    pest_test_case()->get('/admin/resources/form-users/create')
        ->assertOk()
        ->assertSee('Basic information')
        ->assertSee('Main information visible to operators.')
        ->assertSee('Status')
        ->assertSee('Controls whether the account is active.');
});

it('edit form renders with existing values', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'age' => 30,
        'bio' => 'Existing bio',
        'is_active' => true,
        'status' => 'published',
        'published_at' => '2026-05-01',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'form-users', 'record' => $user->getKey()])
        ->assertSet('isEditMode', true)
        ->assertSet('fieldValues.name', 'Existing User')
        ->assertSet('fieldValues.email', 'existing@example.com')
        ->assertSet('fieldValues.age', '30')
        ->assertSet('fieldValues.bio', 'Existing bio')
        ->assertSet('fieldValues.is_active', true)
        ->assertSet('fieldValues.status', 'published')
        ->assertSet('fieldValues.published_at', '2026-05-01 00:00:00');
});

it('required validation works', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'form-users'])
        ->set('fieldValues.name', '')
        ->call('save')
        ->assertHasErrors(['fieldValues.name' => 'required'])
        ->assertSee('Please correct the highlighted fields.')
        ->assertSee('The name field is required.')
        ->assertSee('sf-input-error', false);
});

it('email validation works', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'form-users'])
        ->set('fieldValues.name', 'Test User')
        ->set('fieldValues.email', 'invalid-email')
        ->call('save')
        ->assertHasErrors(['fieldValues.email' => 'email'])
        ->assertSee('The email address field must be a valid email address.')
        ->assertSee('sf-input-error', false);
});

it('record can be created', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'form-users'])
        ->set('fieldValues.name', 'Created User')
        ->set('fieldValues.email', 'created@example.com')
        ->set('fieldValues.age', '27')
        ->set('fieldValues.bio', 'Created bio')
        ->set('fieldValues.is_active', true)
        ->set('fieldValues.status', 'published')
        ->set('fieldValues.published_at', '2026-05-05')
        ->call('save')
        ->assertSee('Record saved successfully.');

    $created = FormUser::query()->where('email', 'created@example.com')->first();

    expect($created)
        ->not->toBeNull()
        ->and($created?->name)->toBe('Created User')
        ->and($created?->age)->toBe(27)
        ->and($created?->is_active)->toBeTrue()
        ->and($created?->status)->toBe('published')
        ->and($created?->published_at?->format('Y-m-d'))->toBe('2026-05-05');
});

it('record can be updated', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Before Update',
        'email' => 'before@example.com',
        'age' => 22,
        'bio' => 'Old bio',
        'is_active' => false,
        'status' => 'draft',
        'published_at' => '2026-05-01',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'form-users', 'record' => $user->getKey()])
        ->set('fieldValues.name', 'After Update')
        ->set('fieldValues.email', 'after@example.com')
        ->set('fieldValues.age', '31')
        ->set('fieldValues.bio', 'New bio')
        ->set('fieldValues.is_active', true)
        ->set('fieldValues.status', 'published')
        ->set('fieldValues.published_at', '2026-05-04')
        ->call('save')
        ->assertSee('Record saved successfully.');

    $user->refresh();

    expect($user->name)->toBe('After Update')
        ->and($user->email)->toBe('after@example.com')
        ->and($user->age)->toBe(31)
        ->and($user->bio)->toBe('New bio')
        ->and($user->is_active)->toBeTrue()
        ->and($user->status)->toBe('published')
        ->and($user->published_at?->format('Y-m-d'))->toBe('2026-05-04');
});

it('handles unknown resource slug safely', function (): void {
    pest_test_case()->get('/admin/resources/unknown-slug/create')
        ->assertNotFound();
});

it('handles unknown record in edit mode safely', function (): void {
    pest_test_case()->get('/admin/resources/form-users/999999/edit')
        ->assertNotFound();
});

it('flat forms still work', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'flat-form-users'])
        ->set('fieldValues.name', 'Flat User')
        ->call('save')
        ->assertSee('Record saved successfully.');

    expect(FormUser::query()->where('name', 'Flat User')->exists())->toBeTrue();
});

it('respects custom route prefix and name prefix', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice-admin.');

    resourceFormReloadRoutes();

    pest_test_case()->get('/backoffice/resources/form-users/create')
        ->assertOk()
        ->assertSeeLivewire(ResourceForm::class);

    expect(Route::has('backoffice-admin.resources.create'))->toBeTrue()
        ->and(Route::has('backoffice-admin.resources.edit'))->toBeTrue();
});

it('does not render hiddenOnCreate field on create', function (): void {
    pest_test_case()->get('/admin/resources/context-form-users/create')
        ->assertOk()
        ->assertDontSee('Internal notes')
        ->assertSee('Password');
});

it('does not render hiddenOnEdit field on edit', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Context Edit',
        'email' => 'context-edit@example.com',
        'password' => 'secret',
        'slug' => 'existing-slug',
        'internal_notes' => 'private note',
    ]);

    pest_test_case()->get('/admin/resources/context-form-users/'.$user->getKey().'/edit')
        ->assertOk()
        ->assertDontSee('field-password', false)
        ->assertSee('Slug');
});

it('requiredOnCreate validates on create', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'context-form-users'])
        ->set('fieldValues.name', 'Create Missing Password')
        ->set('fieldValues.password', '')
        ->call('save')
        ->assertHasErrors(['fieldValues.password' => 'required']);
});

it('requiredOnCreate does not validate on edit', function (): void {
    $user = FormUser::query()->create([
        'name' => 'No Password Edit',
        'email' => 'no-password-edit@example.com',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'context-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.name', 'Edited Name')
        ->set('fieldValues.password', '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDontSee('The Password field is required.');
});

it('readonlyOnEdit field renders as readonly', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Readonly User',
        'email' => 'readonly@example.com',
        'slug' => 'readonly-slug',
    ]);

    pest_test_case()->get('/admin/resources/context-form-users/'.$user->getKey().'/edit')
        ->assertOk()
        ->assertSee('readonly', false)
        ->assertSee('sf-control-readonly', false);
});

it('password input renders as password type', function (): void {
    pest_test_case()->get('/admin/resources/password-form-users/create')
        ->assertOk()
        ->assertSee('type="password"', false);
});

it('edit form does not prefill stored password hash', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Prefill Guard',
        'email' => 'prefill-guard@example.com',
        'password' => Hash::make('existing-secret'),
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'password-form-users', 'record' => $user->getKey()])
        ->assertSet('fieldValues.password', '');
});
