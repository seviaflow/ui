<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceShow;

require __DIR__.'/../Support/ResourceShowSetup.php';

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceShowBootstrap(), 'web');
});

it('belongsToMany manager renders attached records', function (): void {
    $user = ShowUser::query()->create(['name' => 'Roles User', 'email' => 'roles-user@example.com']);
    $role = ShowRole::query()->create(['name' => 'Admin']);
    $user->roles()->attach($role->getKey());

    pest_test_case()->get('/admin/resources/show-users-with-roles/'.$user->getKey())
        ->assertOk()
        ->assertSee('Roles')
        ->assertSee('Role name')
        ->assertSee('sf-table-wrapper', false)
        ->assertSee('sf-table', false)
        ->assertSee('Admin')
        ->assertSee('Detach');
});

it('belongsToMany manager renders configured pivot values', function (): void {
    $user = ShowUser::query()->create(['name' => 'Pivot User', 'email' => 'pivot-user@example.com']);
    $role = ShowRole::query()->create(['name' => 'Pivot Role']);
    $user->roles()->attach($role->getKey(), [
        'quantity' => 3,
        'discount' => '10%',
        'internal_note' => 'secret',
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-roles/'.$user->getKey())
        ->assertOk()
        ->assertSee('Quantity')
        ->assertSee('Discount')
        ->assertSee('3')
        ->assertSeeInOrder(['10', '%'])
        ->assertDontSee('Internal note')
        ->assertDontSee('secret');
});

it('belongsToMany manager renders placeholder for missing pivot values', function (): void {
    $user = ShowUser::query()->create(['name' => 'Pivot Null User', 'email' => 'pivot-null@example.com']);
    $role = ShowRole::query()->create(['name' => 'Pivot Null Role']);
    $user->roles()->attach($role->getKey(), [
        'quantity' => null,
        'discount' => null,
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-roles/'.$user->getKey())
        ->assertOk()
        ->assertSee('Quantity')
        ->assertSee('Discount')
        ->assertSee('—');
});

it('belongsToMany attached records are paginated', function (): void {
    $user = ShowUser::query()->create(['name' => 'Paginated Roles User', 'email' => 'paginated-roles@example.com']);

    foreach (range(1, 4) as $index) {
        $role = ShowRole::query()->create(['name' => 'Role '.$index]);
        $user->roles()->attach($role->getKey());
    }

    pest_test_case()->get('/admin/resources/show-users-with-roles/'.$user->getKey())
        ->assertOk()
        ->assertSee('Role 1')
        ->assertSee('Role 2')
        ->assertDontSee('Role 3')
        ->assertDontSee('Role 4');
});

it('belongsToMany attach options are limited', function (): void {
    $user = ShowUser::query()->create(['name' => 'Attach Limit User', 'email' => 'attach-limit@example.com']);

    foreach (range(1, 4) as $index) {
        ShowRole::query()->create(['name' => 'Attach Role '.$index]);
    }

    pest_test_case()->get('/admin/resources/show-users-with-roles/'.$user->getKey())
        ->assertOk()
        ->assertSee('Attach Role 1')
        ->assertSee('Attach Role 2')
        ->assertDontSee('Attach Role 3')
        ->assertDontSee('Attach Role 4');
});

it('belongsToMany attach UI is hidden when attaching is disabled', function (): void {
    $user = ShowUser::query()->create(['name' => 'Attach Disabled User', 'email' => 'attach-disabled@example.com']);

    foreach (range(1, 2) as $index) {
        ShowRole::query()->create(['name' => 'Attach Disabled Role '.$index]);
    }

    pest_test_case()->get('/admin/resources/show-users-with-roles-attach-disabled/'.$user->getKey())
        ->assertOk()
        ->assertDontSee('Select record to attach')
        ->assertDontSee('wire:click="attachBelongsToMany(\'roles\')"', false);
});

it('belongsToMany attach adds relation', function (): void {
    $user = ShowUser::query()->create(['name' => 'Attach User', 'email' => 'attach-user@example.com']);
    $role = ShowRole::query()->create(['name' => 'Editor']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-roles-no-pivot', 'record' => $user->getKey()])
        ->set('attachSelection.roles', (string) $role->getKey())
        ->call('attachBelongsToMany', 'roles')
        ->assertSee('Related record attached successfully.');

    expect($user->roles()->whereKey($role->getKey())->exists())->toBeTrue();
});

it('belongsToMany duplicate attach is handled safely', function (): void {
    $user = ShowUser::query()->create(['name' => 'Duplicate Attach User', 'email' => 'duplicate-attach@example.com']);
    $role = ShowRole::query()->create(['name' => 'Operator']);
    $user->roles()->attach($role->getKey());

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-roles-no-pivot', 'record' => $user->getKey()])
        ->set('attachSelection.roles', (string) $role->getKey())
        ->call('attachBelongsToMany', 'roles')
        ->assertSee('already attached');
});

it('belongsToMany detach requires confirmation and then removes relation', function (): void {
    $user = ShowUser::query()->create(['name' => 'Detach User', 'email' => 'detach-user@example.com']);
    $role = ShowRole::query()->create(['name' => 'Reviewer']);
    $user->roles()->attach($role->getKey());

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-roles-no-pivot', 'record' => $user->getKey()])
        ->call('detachBelongsToMany', 'roles', $role->getKey())
        ->assertSee('Please confirm detaching this related record before continuing.');

    expect($user->roles()->whereKey($role->getKey())->exists())->toBeTrue();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-roles-no-pivot', 'record' => $user->getKey()])
        ->call('detachBelongsToMany', 'roles', $role->getKey(), true)
        ->assertSee('Related record detached successfully.');

    expect($user->roles()->whereKey($role->getKey())->exists())->toBeFalse();
});

it('cannot detach unrelated belongsToMany record', function (): void {
    $user = ShowUser::query()->create(['name' => 'Unrelated Detach User', 'email' => 'unrelated-detach@example.com']);
    $role = ShowRole::query()->create(['name' => 'Not Attached']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-roles-no-pivot', 'record' => $user->getKey()])
        ->call('detachBelongsToMany', 'roles', $role->getKey(), true)
        ->assertSee('is not attached');
});

it('belongsToMany detach action is hidden and blocked when detaching is disabled', function (): void {
    $user = ShowUser::query()->create(['name' => 'Detach Disabled User', 'email' => 'detach-disabled@example.com']);
    $role = ShowRole::query()->create(['name' => 'Detach Disabled Role']);
    $user->roles()->attach($role->getKey());

    pest_test_case()->get('/admin/resources/show-users-with-roles-detach-disabled/'.$user->getKey())
        ->assertOk()
        ->assertDontSee('wire:click="detachBelongsToMany(\'roles\')"', false);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-roles-detach-disabled', 'record' => $user->getKey()])
        ->call('detachBelongsToMany', 'roles', $role->getKey(), true)
        ->assertSee('Detaching related records is not allowed for this relation.');

    expect($user->roles()->whereKey($role->getKey())->exists())->toBeTrue();
});

it('belongsToMany attach and detach respect related resource policy when available', function (): void {
    Gate::policy(ShowRole::class, DenyShowRolePolicy::class);

    $user = ShowUser::query()->create(['name' => 'Policy Roles User', 'email' => 'policy-roles@example.com']);
    $role = ShowRole::query()->create(['name' => 'Blocked Role']);
    $user->roles()->attach($role->getKey());

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-roles-no-pivot', 'record' => $user->getKey()])
        ->set('attachSelection.roles', (string) $role->getKey())
        ->call('attachBelongsToMany', 'roles')
        ->assertForbidden();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-roles-no-pivot', 'record' => $user->getKey()])
        ->call('detachBelongsToMany', 'roles', $role->getKey(), true)
        ->assertForbidden();
});

it('belongsToMany manager still works without pivot columns configured', function (): void {
    $user = ShowUser::query()->create(['name' => 'No Pivot Config User', 'email' => 'no-pivot-config@example.com']);
    $role = ShowRole::query()->create(['name' => 'No Pivot Role']);
    $user->roles()->attach($role->getKey(), ['quantity' => 7, 'discount' => '5%']);

    pest_test_case()->get('/admin/resources/show-users-with-roles-no-pivot/'.$user->getKey())
        ->assertOk()
        ->assertSee('Roles')
        ->assertSee('No Pivot Role')
        ->assertDontSee('Quantity')
        ->assertDontSee('Discount');
});

it('belongsToMany attach with pivot values persists only defined pivot fields', function (): void {
    $user = ShowUser::query()->create(['name' => 'Pivot Attach Parent', 'email' => 'pivot-attach@example.com']);
    $role = ShowRole::query()->create(['name' => 'Pivot Attach Role']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-roles', 'record' => $user->getKey()])
        ->set('attachSelection.roles', (string) $role->getKey())
        ->call('openBelongsToManyAttachModal', 'roles')
        ->set('pivotModalValues.quantity', '4')
        ->set('pivotModalValues.status', 'active')
        ->set('pivotModalValues.internal_note', 'tampered')
        ->call('savePivotModal')
        ->assertSee('Related record attached successfully.');

    $pivot = ShowRoleUserPivot::query()
        ->where('show_user_id', $user->getKey())
        ->where('show_role_id', $role->getKey())
        ->first();

    expect($pivot)->not->toBeNull()
        ->and($pivot?->quantity)->toBe(4)
        ->and($pivot?->status)->toBe('active')
        ->and($pivot?->internal_note)->toBeNull();
});

it('belongsToMany pivot values can be edited', function (): void {
    $user = ShowUser::query()->create(['name' => 'Pivot Edit Parent', 'email' => 'pivot-edit@example.com']);
    $role = ShowRole::query()->create(['name' => 'Pivot Edit Role']);
    $user->roles()->attach($role->getKey(), ['quantity' => 1, 'status' => 'paused']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-roles', 'record' => $user->getKey()])
        ->call('openBelongsToManyEditPivotModal', 'roles', $role->getKey())
        ->set('pivotModalValues.quantity', '8')
        ->set('pivotModalValues.status', 'active')
        ->call('savePivotModal')
        ->assertSee('Pivot values updated successfully.');

    $pivot = ShowRoleUserPivot::query()
        ->where('show_user_id', $user->getKey())
        ->where('show_role_id', $role->getKey())
        ->first();

    expect($pivot?->quantity)->toBe(8)
        ->and($pivot?->status)->toBe('active');
});

it('belongsToMany pivot validation works', function (): void {
    $user = ShowUser::query()->create(['name' => 'Pivot Validation Parent', 'email' => 'pivot-validation@example.com']);
    $role = ShowRole::query()->create(['name' => 'Pivot Validation Role']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-roles', 'record' => $user->getKey()])
        ->set('attachSelection.roles', (string) $role->getKey())
        ->call('openBelongsToManyAttachModal', 'roles')
        ->set('pivotModalValues.quantity', '')
        ->call('savePivotModal')
        ->assertHasErrors(['pivotModalValues.quantity' => 'required']);
});

it('cannot edit pivot for unattached record', function (): void {
    $user = ShowUser::query()->create(['name' => 'Unattached Pivot Parent', 'email' => 'unattached-pivot@example.com']);
    $role = ShowRole::query()->create(['name' => 'Unattached Pivot Role']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-roles', 'record' => $user->getKey()])
        ->call('openBelongsToManyEditPivotModal', 'roles', $role->getKey())
        ->assertSee('The selected related record is not attached.');
});
