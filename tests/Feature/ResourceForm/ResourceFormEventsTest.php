<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceForm;

require __DIR__.'/../Support/ResourceFormSetup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceFormBootstrap(), 'web');
    ResourceFormEventTracker::reset();
});

it('creating handler runs before persist and created runs after persist', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'resource-event-form-users'])
        ->set('fieldValues.name', 'Event Create User')
        ->set('fieldValues.email', 'event-create@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $created = FormUser::query()->where('email', 'event-create@example.com')->first();

    expect($created)->not->toBeNull()
        ->and(ResourceFormEventTracker::$events)->toHaveCount(2)
        ->and(ResourceFormEventTracker::$events[0]['event'])->toBe('creating')
        ->and(ResourceFormEventTracker::$events[0]['exists'])->toBeFalse()
        ->and(ResourceFormEventTracker::$events[1]['event'])->toBe('created')
        ->and(ResourceFormEventTracker::$events[1]['exists'])->toBeTrue();
});

it('updating handler runs before persist and updated runs after persist', function (): void {
    $record = FormUser::query()->create([
        'name' => 'Before Update',
        'email' => 'before-update@example.com',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'resource-event-form-users', 'record' => $record->getKey()])
        ->set('fieldValues.name', 'After Update')
        ->call('save')
        ->assertHasNoErrors();

    $record->refresh();

    expect($record->name)->toBe('After Update')
        ->and(ResourceFormEventTracker::$events)->toHaveCount(2)
        ->and(ResourceFormEventTracker::$events[0]['event'])->toBe('updating')
        ->and(ResourceFormEventTracker::$events[0]['exists'])->toBeTrue()
        ->and(ResourceFormEventTracker::$events[1]['event'])->toBe('updated')
        ->and(ResourceFormEventTracker::$events[1]['exists'])->toBeTrue();
});

it('creating exception prevents record creation and shows safe message', function (): void {
    ResourceFormEventTracker::$throwOnCreating = true;

    Livewire::test(ResourceForm::class, ['resource' => 'resource-event-form-users'])
        ->set('fieldValues.name', 'Blocked Create')
        ->set('fieldValues.email', 'blocked-create@example.com')
        ->call('save')
        ->assertSet('errorMessage', 'Create blocked by event.');

    expect(FormUser::query()->where('email', 'blocked-create@example.com')->exists())->toBeFalse()
        ->and(ResourceFormEventTracker::$creatingCalls)->toBe(1)
        ->and(ResourceFormEventTracker::$createdCalls)->toBe(0);
});

it('updating exception prevents record update and shows safe message', function (): void {
    $record = FormUser::query()->create([
        'name' => 'Before Blocked Update',
        'email' => 'blocked-update@example.com',
    ]);

    ResourceFormEventTracker::$throwOnUpdating = true;

    Livewire::test(ResourceForm::class, ['resource' => 'resource-event-form-users', 'record' => $record->getKey()])
        ->set('fieldValues.name', 'Should Not Persist')
        ->call('save')
        ->assertSet('errorMessage', 'Update blocked by event.');

    $record->refresh();

    expect($record->name)->toBe('Before Blocked Update')
        ->and(ResourceFormEventTracker::$updatingCalls)->toBe(1)
        ->and(ResourceFormEventTracker::$updatedCalls)->toBe(0);
});

it('context source is form and includes resource and changes', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'resource-event-form-users'])
        ->set('fieldValues.name', 'Context User')
        ->set('fieldValues.email', 'context-user@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $creating = ResourceFormEventTracker::$events[0];

    expect($creating['source'])->toBe('form')
        ->and($creating['resource'])->toBe(ResourceEventFormUserResource::class)
        ->and($creating['slug'])->toBe('resource-event-form-users')
        ->and(array_key_exists('name', $creating['changes']))->toBeTrue()
        ->and(array_key_exists('email', $creating['changes']))->toBeTrue()
        ->and($creating['user']?->email)->toBe('resource-form@example.com');
});

it('update context includes original values', function (): void {
    $record = FormUser::query()->create([
        'name' => 'Original Name',
        'email' => 'original-context@example.com',
    ]);

    Livewire::test(ResourceForm::class, ['resource' => 'resource-event-form-users', 'record' => $record->getKey()])
        ->set('fieldValues.name', 'Updated Context Name')
        ->call('save')
        ->assertHasNoErrors();

    $updating = ResourceFormEventTracker::$events[0];

    expect($updating['event'])->toBe('updating')
        ->and($updating['original']['name'] ?? null)->toBe('Original Name')
        ->and($updating['changes']['name'] ?? null)->toBe('Updated Context Name');
});

it('handlers run once per operation', function (): void {
    Livewire::test(ResourceForm::class, ['resource' => 'resource-event-form-users'])
        ->set('fieldValues.name', 'Once Create')
        ->set('fieldValues.email', 'once-create@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $record = FormUser::query()->where('email', 'once-create@example.com')->firstOrFail();

    Livewire::test(ResourceForm::class, ['resource' => 'resource-event-form-users', 'record' => $record->getKey()])
        ->set('fieldValues.name', 'Once Update')
        ->call('save')
        ->assertHasNoErrors();

    expect(ResourceFormEventTracker::$creatingCalls)->toBe(1)
        ->and(ResourceFormEventTracker::$createdCalls)->toBe(1)
        ->and(ResourceFormEventTracker::$updatingCalls)->toBe(1)
        ->and(ResourceFormEventTracker::$updatedCalls)->toBe(1);
});

it('unexpected handler exception shows generic safe error message', function (): void {
    ResourceFormEventTracker::$throwUnexpectedOnCreated = true;

    Livewire::test(ResourceForm::class, ['resource' => 'resource-event-form-users'])
        ->set('fieldValues.name', 'Unexpected Failure')
        ->set('fieldValues.email', 'unexpected-failure@example.com')
        ->call('save')
        ->assertSet('errorMessage', 'Saving failed. Please try again.');

    expect(FormUser::query()->where('email', 'unexpected-failure@example.com')->exists())->toBeTrue();
});
