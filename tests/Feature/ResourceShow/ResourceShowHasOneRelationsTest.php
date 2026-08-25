<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceShow;

require __DIR__.'/../Support/ResourceShowSetup.php';

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceShowBootstrap(), 'web');
});

it('show page renders existing hasOne related record', function (): void {
    $user = ShowUser::query()->create(['name' => 'HasOne Parent', 'email' => 'hasone-parent@example.com']);
    ShowProfile::query()->create(['show_user_id' => $user->getKey(), 'bio' => 'Profile bio']);

    pest_test_case()->get('/admin/resources/show-users-with-profile/'.$user->getKey())
        ->assertOk()
        ->assertSee('Profile')
        ->assertSee('Profile bio');
});

it('missing hasOne relation shows empty state', function (): void {
    $user = ShowUser::query()->create(['name' => 'No Profile Parent', 'email' => 'no-profile@example.com']);

    pest_test_case()->get('/admin/resources/show-users-with-profile/'.$user->getKey())
        ->assertOk()
        ->assertSee('Profile')
        ->assertSee('No related records to display.');
});

it('hasOne create button appears only when allowed and authorized', function (): void {
    $user = ShowUser::query()->create(['name' => 'Create Allowed', 'email' => 'create-allowed@example.com']);

    pest_test_case()->get('/admin/resources/show-users-with-profile-manage/'.$user->getKey())
        ->assertOk()
        ->assertSee('Add profile');

    Gate::policy(ShowProfile::class, DenyShowProfilePolicy::class);

    pest_test_case()->get('/admin/resources/show-users-with-profile-manage/'.$user->getKey())
        ->assertOk()
        ->assertDontSee('Add profile');
});

it('hasOne record can be created and linked to parent', function (): void {
    $user = ShowUser::query()->create(['name' => 'Create Profile Parent', 'email' => 'create-profile@example.com']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-profile-manage', 'record' => $user->getKey()])
        ->call('openHasOneCreateModal', 'profile')
        ->set('relationModalValues.bio', 'Created bio')
        ->call('saveRelationModal')
        ->assertSee('Related record created successfully.');

    expect(ShowProfile::query()->where('show_user_id', $user->getKey())->where('bio', 'Created bio')->exists())->toBeTrue();
});

it('hasOne record can be edited only when it belongs to parent', function (): void {
    $firstUser = ShowUser::query()->create(['name' => 'First Parent', 'email' => 'first-parent@example.com']);
    $secondUser = ShowUser::query()->create(['name' => 'Second Parent', 'email' => 'second-parent@example.com']);
    $firstProfile = ShowProfile::query()->create(['show_user_id' => $firstUser->getKey(), 'bio' => 'First bio']);
    $secondProfile = ShowProfile::query()->create(['show_user_id' => $secondUser->getKey(), 'bio' => 'Second bio']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-profile-manage', 'record' => $firstUser->getKey()])
        ->call('openHasOneEditModal', 'profile', $firstProfile->getKey())
        ->set('relationModalValues.bio', 'Updated bio')
        ->call('saveRelationModal')
        ->assertSee('Related record updated successfully.');

    expect($firstProfile->fresh()?->bio)->toBe('Updated bio');

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-profile-manage', 'record' => $firstUser->getKey()])
        ->call('openHasOneEditModal', 'profile', $secondProfile->getKey())
        ->assertSee('not available');
});

it('hasOne delete works only for related record', function (): void {
    $firstUser = ShowUser::query()->create(['name' => 'Delete First Parent', 'email' => 'delete-first@example.com']);
    $secondUser = ShowUser::query()->create(['name' => 'Delete Second Parent', 'email' => 'delete-second@example.com']);
    $firstProfile = ShowProfile::query()->create(['show_user_id' => $firstUser->getKey(), 'bio' => 'First delete']);
    $secondProfile = ShowProfile::query()->create(['show_user_id' => $secondUser->getKey(), 'bio' => 'Second delete']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-profile-manage', 'record' => $firstUser->getKey()])
        ->call('deleteHasOneRecord', 'profile', $firstProfile->getKey(), true)
        ->assertSee('Related record deleted successfully.');

    expect(ShowProfile::query()->find($firstProfile->getKey()))->toBeNull();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-profile-manage', 'record' => $firstUser->getKey()])
        ->call('deleteHasOneRecord', 'profile', $secondProfile->getKey(), true)
        ->assertSee('not available');
});

it('hasOne create edit and delete actions are blocked by policy', function (): void {
    Gate::policy(ShowProfile::class, DenyShowProfilePolicy::class);

    $user = ShowUser::query()->create(['name' => 'Denied Parent', 'email' => 'denied-parent@example.com']);
    $profile = ShowProfile::query()->create(['show_user_id' => $user->getKey(), 'bio' => 'Denied bio']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-profile-manage', 'record' => $user->getKey()])
        ->call('openHasOneCreateModal', 'profile')
        ->assertForbidden();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-profile-manage', 'record' => $user->getKey()])
        ->call('openHasOneEditModal', 'profile', $profile->getKey())
        ->assertForbidden();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-profile-manage', 'record' => $user->getKey()])
        ->call('deleteHasOneRecord', 'profile', $profile->getKey(), true)
        ->assertForbidden();
});

it('hasOne direct saveRelationModal calls are blocked when create or edit is not allowed', function (): void {
    $user = ShowUser::query()->create(['name' => 'HasOne Bypass', 'email' => 'hasone-bypass@example.com']);
    $profile = ShowProfile::query()->create(['show_user_id' => $user->getKey(), 'bio' => 'Initial']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-profile', 'record' => $user->getKey()])
        ->set('relationModalRelationship', 'profile')
        ->set('relationModalMode', 'create')
        ->set('relationModalValues.bio', 'Should not create')
        ->call('saveRelationModal')
        ->assertSee('Creating related records is not allowed for this relation.');

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-profile', 'record' => $user->getKey()])
        ->set('relationModalRelationship', 'profile')
        ->set('relationModalMode', 'edit')
        ->set('relationModalRecordId', (string) $profile->getKey())
        ->set('relationModalValues.bio', 'Should not edit')
        ->call('saveRelationModal')
        ->assertSee('Editing related records is not allowed for this relation.');

    expect($profile->fresh()?->bio)->toBe('Initial');
});
