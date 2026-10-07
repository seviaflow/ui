<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceForm;

require __DIR__.'/../Support/ResourceFormSetup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceFormBootstrap(), 'web');
});

it('readonlyOnEdit field is not persisted when tampered', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Readonly Persist',
        'email' => 'readonly-persist@example.com',
        'slug' => 'original-slug',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'context-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.slug', 'tampered-slug')
        ->set('fieldValues.name', 'Updated Name')
        ->call('save');

    $user->refresh();

    expect($user->slug)->toBe('original-slug')
        ->and($user->name)->toBe('Updated Name');
});

it('disabledOnEdit field is not persisted when tampered', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Disabled Persist',
        'email' => 'disabled-persist@example.com',
        'status' => 'draft',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'context-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.status', 'published')
        ->set('fieldValues.name', 'Updated Name')
        ->call('save');

    $user->refresh();

    expect($user->status)->toBe('draft')
        ->and($user->name)->toBe('Updated Name');
});

it('hidden field is not persisted when tampered', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Hidden Persist',
        'email' => 'hidden-persist@example.com',
        'internal_notes' => 'original private note',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'context-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.internal_notes', 'tampered private note')
        ->set('fieldValues.name', 'Updated Name')
        ->call('save');

    $user->refresh();

    expect($user->internal_notes)->toBe('original private note')
        ->and($user->name)->toBe('Updated Name');
});

it('hidden password field is not persisted when tampered', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Password Hidden Persist',
        'email' => 'password-hidden@example.com',
        'password' => Hash::make('original-secret'),
    ]);
    $originalHash = (string) $user->password;

    Livewire::test(ResourceForm::class, ['resource' => 'context-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.password', 'tampered-secret')
        ->set('fieldValues.name', 'Updated Name')
        ->call('save');

    $user->refresh();

    expect((string) $user->password)->toBe($originalHash)
        ->and(Hash::check('tampered-secret', (string) $user->password))->toBeFalse();
});

it('hidden readonly and disabled password fields are not persisted when tampered', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Password Context Persist',
        'email' => 'password-context@example.com',
        'password' => Hash::make('original-secret'),
    ]);
    $originalHash = (string) $user->password;

    Livewire::test(ResourceForm::class, ['resource' => 'password-context-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.hidden_password', 'tampered-hidden')
        ->set('fieldValues.readonly_password', 'tampered-readonly')
        ->set('fieldValues.disabled_password', 'tampered-disabled')
        ->set('fieldValues.name', 'Password Context Persist Updated')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect((string) $user->password)->toBe($originalHash)
        ->and(Hash::check('tampered-hidden', (string) $user->password))->toBeFalse()
        ->and(Hash::check('tampered-readonly', (string) $user->password))->toBeFalse()
        ->and(Hash::check('tampered-disabled', (string) $user->password))->toBeFalse()
        ->and($user->name)->toBe('Password Context Persist Updated');
});

it('normal visible editable field still persists', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Normal Persist',
        'email' => 'normal-persist@example.com',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'context-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.name', 'Normal Updated')
        ->call('save');

    $user->refresh();

    expect($user->name)->toBe('Normal Updated');
});

it('distinguishes untouched create placeholders from explicit null and preserves omitted update fields', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'presence-form-users'])
        ->set('fieldValues.name', 'Database default')
        ->call('save')
        ->assertHasNoErrors();

    $created = FormUser::query()->where('name', 'Database default')->firstOrFail();

    expect($created->default_status)->toBe('database-default');

    Livewire::test(ResourceForm::class, ['resource' => 'presence-form-users'])
        ->set('fieldValues.name', 'Explicit null')
        ->set('fieldValues.default_status', null)
        ->call('save')
        ->assertHasNoErrors();

    $cleared = FormUser::query()->where('name', 'Explicit null')->firstOrFail();

    expect($cleared->default_status)->toBeNull();

    $existing = FormUser::query()->create([
        'name' => 'Partial update',
        'default_status' => 'keep-me',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'presence-form-users', 'record' => $existing->getKey()])
        ->set('fieldValues.name', 'Partial update changed')
        ->call('save')
        ->assertHasNoErrors();

    expect($existing->fresh()?->default_status)->toBe('keep-me');
});

it('empty optional password on edit does not overwrite existing password', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Optional Edit Password',
        'email' => 'optional-edit@example.com',
        'password' => Hash::make('keep-this'),
    ]);
    $originalHash = (string) $user->password;

    Livewire::test(ResourceForm::class, ['resource' => 'password-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.name', 'Optional Edit Password Updated')
        ->set('fieldValues.password', '')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect((string) $user->password)->toBe($originalHash)
        ->and(Hash::check('keep-this', (string) $user->password))->toBeTrue();
});

it('non-empty password is hashed on create', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'password-form-users'])
        ->set('fieldValues.name', 'Password Create')
        ->set('fieldValues.email', 'password-create@example.com')
        ->set('fieldValues.password', 'plain-secret')
        ->call('save')
        ->assertHasNoErrors();

    $created = FormUser::query()->where('email', 'password-create@example.com')->first();

    expect($created)->not->toBeNull()
        ->and((string) $created?->password)->not->toBe('plain-secret')
        ->and(Hash::check('plain-secret', (string) $created?->password))->toBeTrue();
});

it('non-empty password is hashed on edit', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Password Edit',
        'email' => 'password-edit@example.com',
        'password' => Hash::make('old-secret'),
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'password-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.password', 'new-secret')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect((string) $user->password)->not->toBe('new-secret')
        ->and(Hash::check('new-secret', (string) $user->password))->toBeTrue();
});

it('money field renders currency hint', function (): void {
    pest_test_case()->get('/admin/resources/money-form-users/create')
        ->assertOk()
        ->assertSee('PLN')
        ->assertSee('Price');
});

it('money field validation rejects non numeric value', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'money-form-users'])
        ->set('fieldValues.name', 'Money Invalid')
        ->set('fieldValues.price', 'abc')
        ->call('save')
        ->assertHasErrors(['fieldValues.price' => 'numeric']);
});

it('valid decimal money value can be saved predictably', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'money-form-users'])
        ->set('fieldValues.name', 'Money Valid')
        ->set('fieldValues.price', '1234.56')
        ->call('save')
        ->assertHasNoErrors();

    $created = FormUser::query()->where('name', 'Money Valid')->first();

    expect($created)->not->toBeNull()
        ->and((string) $created?->price)->toBe('1234.56');
});

it('hidden readonly and disabled money fields are not persisted when tampered', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Money Context Persist',
        'price' => '10.00',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'money-context-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.hidden_price', '999.99')
        ->set('fieldValues.readonly_price', '999.99')
        ->set('fieldValues.disabled_price', '999.99')
        ->set('fieldValues.name', 'Money Context Persist Updated')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect((string) $user->price)->toBe('10.00')
        ->and($user->name)->toBe('Money Context Persist Updated');
});

it('datetime field renders as datetime local input', function (): void {
    pest_test_case()->get('/admin/resources/schedule-form-users/create')
        ->assertOk()
        ->assertSee('type="datetime-local"', false)
        ->assertSee('Starts at');
});

it('time field renders as time input', function (): void {
    pest_test_case()->get('/admin/resources/schedule-form-users/create')
        ->assertOk()
        ->assertSee('type="time"', false)
        ->assertSee('Opening time');
});

it('valid datetime and time pass validation and persist', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'schedule-form-users'])
        ->set('fieldValues.name', 'Schedule Valid')
        ->set('fieldValues.starts_at', '2026-06-01T09:30')
        ->set('fieldValues.opening_time', '08:45')
        ->call('save')
        ->assertHasNoErrors();

    $created = FormUser::query()->where('name', 'Schedule Valid')->first();

    expect($created)->not->toBeNull()
        ->and((string) $created?->starts_at)->toBe('2026-06-01T09:30')
        ->and((string) $created?->opening_time)->toBe('08:45');
});

it('invalid datetime fails validation', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'schedule-form-users'])
        ->set('fieldValues.name', 'Schedule Invalid Datetime')
        ->set('fieldValues.starts_at', 'not-a-datetime')
        ->set('fieldValues.opening_time', '08:45')
        ->call('save')
        ->assertHasErrors(['fieldValues.starts_at' => 'date']);
});

it('invalid time fails validation', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'schedule-form-users'])
        ->set('fieldValues.name', 'Schedule Invalid Time')
        ->set('fieldValues.starts_at', '2026-06-01T09:30')
        ->set('fieldValues.opening_time', '25:99')
        ->call('save')
        ->assertHasErrors(['fieldValues.opening_time' => 'date_format']);
});

it('hidden readonly and disabled datetime and time fields are not persisted when tampered', function (): void {
    $user = FormUser::query()->create([
        'name' => 'Schedule Context Persist',
        'starts_at' => '2026-01-01T10:00',
        'opening_time' => '09:00',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'schedule-context-form-users', 'record' => $user->getKey()])
        ->set('fieldValues.hidden_starts_at', '2026-12-31T22:00')
        ->set('fieldValues.readonly_starts_at', '2026-12-31T22:00')
        ->set('fieldValues.disabled_starts_at', '2026-12-31T22:00')
        ->set('fieldValues.hidden_opening_time', '22:00')
        ->set('fieldValues.readonly_opening_time', '22:00')
        ->set('fieldValues.disabled_opening_time', '22:00')
        ->set('fieldValues.name', 'Schedule Context Persist Updated')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect((string) $user->starts_at)->toBe('2026-01-01T10:00')
        ->and((string) $user->opening_time)->toBe('09:00')
        ->and($user->name)->toBe('Schedule Context Persist Updated');
});
