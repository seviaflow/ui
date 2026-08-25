<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceIndex;

require __DIR__.'/../Support/ResourceIndexSetup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceIndexBootstrap(), 'web');
});

it('deletes a single record', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Delete Me',
        'email' => 'delete@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->call('deleteRecord', $user->getKey())
        ->assertSee('Record deleted successfully.');

    expect(TestUser::query()->find($user->getKey()))->toBeNull();
});

it('respects soft delete for models using soft deletes', function (): void {
    $user = SoftDeleteUser::query()->create([
        'name' => 'Soft User',
        'email' => 'soft@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-delete-users'])
        ->call('deleteRecord', $user->getKey())
        ->assertSee('Record moved to trash successfully.');

    expect(SoftDeleteUser::withTrashed()->find($user->getKey()))
        ->not->toBeNull()
        ->and(SoftDeleteUser::withTrashed()->find($user->getKey())?->trashed())->toBeTrue();
});

it('filters only active records by default for soft-deletable resources', function (): void {
    $active = SoftDeleteUser::query()->create([
        'name' => 'Active Soft',
        'email' => 'active-soft@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    $trashed = SoftDeleteUser::query()->create([
        'name' => 'Trashed Soft',
        'email' => 'trashed-soft@example.com',
        'status' => 'draft',
        'is_active' => false,
    ]);
    $trashed->delete();

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-delete-users'])
        ->assertSee($active->name)
        ->assertDontSee($trashed->name);
});

it('can scope to trashed records for soft-deletable resources', function (): void {
    $active = SoftDeleteUser::query()->create([
        'name' => 'Active For Scope',
        'email' => 'active-for-scope@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    $trashed = SoftDeleteUser::query()->create([
        'name' => 'Trashed For Scope',
        'email' => 'trashed-for-scope@example.com',
        'status' => 'draft',
        'is_active' => false,
    ]);
    $trashed->delete();

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-delete-users'])
        ->set('trashedScope', 'trashed')
        ->assertSee($trashed->name)
        ->assertDontSee($active->name);
});

it('can scope to all records including trashed for soft-deletable resources', function (): void {
    $active = SoftDeleteUser::query()->create([
        'name' => 'Active With Trashed',
        'email' => 'active-with-trashed@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    $trashed = SoftDeleteUser::query()->create([
        'name' => 'Trashed With Trashed',
        'email' => 'trashed-with-trashed@example.com',
        'status' => 'draft',
        'is_active' => false,
    ]);
    $trashed->delete();

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-delete-users'])
        ->set('trashedScope', 'with_trashed')
        ->assertSee($active->name)
        ->assertSee($trashed->name);
});

it('restores a trashed record for soft-deletable resources', function (): void {
    $trashed = SoftDeleteUser::query()->create([
        'name' => 'Restore Me',
        'email' => 'restore-me@example.com',
        'status' => 'draft',
        'is_active' => false,
    ]);
    $trashed->delete();

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-delete-users'])
        ->call('restoreRecord', $trashed->getKey())
        ->assertSee('Record restored successfully.');

    expect(SoftDeleteUser::withTrashed()->find($trashed->getKey()))
        ->not->toBeNull()
        ->and(SoftDeleteUser::withTrashed()->find($trashed->getKey())?->trashed())->toBeFalse();
});

it('does not allow restore for non-soft-deletable resources', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Cannot Restore',
        'email' => 'cannot-restore@example.com',
        'status' => 'draft',
        'is_active' => false,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->call('restoreRecord', $user->getKey())
        ->assertSee('Restore is not available for this resource.');
});

it('shows move to trash label for soft-deletable resources', function (): void {
    SoftDeleteUser::query()->create([
        'name' => 'Trash Label',
        'email' => 'trash-label@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    pest_test_case()->get('/admin/resources/soft-delete-users')
        ->assertOk()
        ->assertSee('Move to trash');
});

it('does not show restore controls for non-soft-deletable resources', function (): void {
    TestUser::query()->create([
        'name' => 'No Restore Control',
        'email' => 'no-restore-control@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    pest_test_case()->get('/admin/resources/filter-users')
        ->assertOk()
        ->assertDontSee('wire:click="restoreRecord"', false);
});

it('handles invalid record delete safely', function (): void {
    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->call('deleteRecord', 999_999)
        ->assertSee('The selected record could not be found.');
});

it('delete events run in order for single delete', function (): void {
    $user = SoftDeleteUser::query()->create([
        'name' => 'Event Delete',
        'email' => 'event-delete@example.com',
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-delete-event-users'])
        ->call('deleteRecord', $user->getKey())
        ->assertSee('Record moved to trash successfully.');

    expect(SoftDeleteEventTracker::$events)->toHaveCount(2)
        ->and(SoftDeleteEventTracker::$events[0]['event'])->toBe('deleting')
        ->and(SoftDeleteEventTracker::$events[1]['event'])->toBe('deleted')
        ->and(SoftDeleteEventTracker::$events[0]['source'])->toBe('index')
        ->and(SoftDeleteEventTracker::$events[1]['source'])->toBe('index');
});

it('deleting event can block single delete with safe error', function (): void {
    $user = SoftDeleteUser::query()->create([
        'name' => 'Blocked Delete',
        'email' => 'blocked-delete@example.com',
    ]);

    SoftDeleteEventTracker::$throwOnDeleting = true;

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-delete-event-users'])
        ->call('deleteRecord', $user->getKey())
        ->assertSee('Delete blocked by event.');

    expect(SoftDeleteUser::withTrashed()->find($user->getKey())?->trashed())->toBeFalse();
});

it('restore events run in order for single restore', function (): void {
    $user = SoftDeleteUser::query()->create([
        'name' => 'Event Restore',
        'email' => 'event-restore@example.com',
    ]);
    $user->delete();

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-delete-event-users'])
        ->call('restoreRecord', $user->getKey())
        ->assertSee('Record restored successfully.');

    expect(SoftDeleteEventTracker::$events)->toHaveCount(2)
        ->and(SoftDeleteEventTracker::$events[0]['event'])->toBe('restoring')
        ->and(SoftDeleteEventTracker::$events[1]['event'])->toBe('restored')
        ->and(SoftDeleteEventTracker::$events[0]['source'])->toBe('index')
        ->and(SoftDeleteEventTracker::$events[1]['source'])->toBe('index');
});

it('restoring event can block restore with safe error', function (): void {
    $user = SoftDeleteUser::query()->create([
        'name' => 'Blocked Restore',
        'email' => 'blocked-restore@example.com',
    ]);
    $user->delete();

    SoftDeleteEventTracker::$throwOnRestoring = true;

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-delete-event-users'])
        ->call('restoreRecord', $user->getKey())
        ->assertSee('Restore blocked by event.');

    expect(SoftDeleteUser::withTrashed()->find($user->getKey())?->trashed())->toBeTrue();
});

it('unexpected delete event exception shows generic safe error', function (): void {
    $user = SoftDeleteUser::query()->create([
        'name' => 'Unexpected Delete',
        'email' => 'unexpected-delete@example.com',
    ]);

    SoftDeleteEventTracker::$throwUnexpectedOnDeleted = true;

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-delete-event-users'])
        ->call('deleteRecord', $user->getKey())
        ->assertSee('Delete failed. Please try again.');

    expect(SoftDeleteUser::withTrashed()->find($user->getKey())?->trashed())->toBeFalse();
});
