<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceShow;

require __DIR__.'/../Support/ResourceShowSetup.php';

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceShowBootstrap(), 'web');
});

it('show page renders hasMany relation manager', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'User With Orders',
        'email' => 'orders@example.com',
    ]);

    ShowOrder::query()->create([
        'show_user_id' => $user->getKey(),
        'number' => 'ORD-001',
        'status' => 'paid',
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-orders/'.$user->getKey())
        ->assertOk()
        ->assertSee('Orders')
        ->assertSee('Order number')
        ->assertSee('sf-table-wrapper', false)
        ->assertSee('sf-table', false)
        ->assertSee('ORD-001');
});

it('hides hasMany manager when related resource viewAny is unauthorized', function (): void {
    Gate::policy(ShowOrder::class, DenyShowOrderViewPolicy::class);

    $user = ShowUser::query()->create([
        'name' => 'Hidden Relation User',
        'email' => 'hidden-relation@example.com',
    ]);

    ShowOrder::query()->create([
        'show_user_id' => $user->getKey(),
        'number' => 'ORD-HIDDEN',
        'status' => 'paid',
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-orders/'.$user->getKey())
        ->assertOk()
        ->assertDontSee('wire:click="openHasManyCreateModal(\'orders\')"', false)
        ->assertDontSee('ORD-HIDDEN');
});

it('hasMany create button is hidden by default', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'No Create Button User',
        'email' => 'no-create-button@example.com',
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-orders/'.$user->getKey())
        ->assertOk()
        ->assertDontSee('Add order');
});

it('hasMany create button appears when allowCreate is enabled', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Create Button User',
        'email' => 'create-button@example.com',
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-orders-manage/'.$user->getKey())
        ->assertOk()
        ->assertSee('Add order');
});

it('hasMany child can be created and linked to parent', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Create Child Parent',
        'email' => 'create-child@example.com',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-orders-manage', 'record' => $user->getKey()])
        ->call('openHasManyCreateModal', 'orders')
        ->set('relationModalValues.number', 'ORD-MANAGE-1')
        ->set('relationModalValues.status', 'paid')
        ->call('saveRelationModal')
        ->assertSee('Related record created successfully.');

    expect(ShowOrder::query()->where('show_user_id', $user->getKey())->where('number', 'ORD-MANAGE-1')->exists())->toBeTrue();
});

it('hasMany edit button hidden by default', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'No Edit Button User',
        'email' => 'no-edit-button@example.com',
    ]);
    ShowOrder::query()->create([
        'show_user_id' => $user->getKey(),
        'number' => 'ORD-NO-EDIT',
        'status' => 'new',
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-orders/'.$user->getKey())
        ->assertOk()
        ->assertDontSee('Edit order');
});

it('hasMany edit button appears when allowEdit is enabled', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Edit Button User',
        'email' => 'edit-button@example.com',
    ]);
    ShowOrder::query()->create([
        'show_user_id' => $user->getKey(),
        'number' => 'ORD-EDIT-BUTTON',
        'status' => 'new',
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-orders-manage/'.$user->getKey())
        ->assertOk()
        ->assertSee('Edit order');
});

it('hasMany child can be edited', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Edit Child Parent',
        'email' => 'edit-child@example.com',
    ]);
    $order = ShowOrder::query()->create([
        'show_user_id' => $user->getKey(),
        'number' => 'ORD-EDIT-1',
        'status' => 'new',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-orders-manage', 'record' => $user->getKey()])
        ->call('openHasManyEditModal', 'orders', $order->getKey())
        ->set('relationModalValues.status', 'paid')
        ->call('saveRelationModal')
        ->assertSee('Related record updated successfully.');

    expect($order->fresh()?->status)->toBe('paid');
});

it('cannot edit child that does not belong to parent', function (): void {
    $firstUser = ShowUser::query()->create(['name' => 'First Parent Edit Guard', 'email' => 'first-guard@example.com']);
    $secondUser = ShowUser::query()->create(['name' => 'Second Parent Edit Guard', 'email' => 'second-guard@example.com']);
    $secondOrder = ShowOrder::query()->create([
        'show_user_id' => $secondUser->getKey(),
        'number' => 'ORD-OUTSIDE',
        'status' => 'new',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-orders-manage', 'record' => $firstUser->getKey()])
        ->call('openHasManyEditModal', 'orders', $secondOrder->getKey())
        ->assertSee('not available');
});

it('hasMany relation table refreshes after save', function (): void {
    $user = ShowUser::query()->create(['name' => 'Refresh Parent', 'email' => 'refresh-parent@example.com']);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-orders-manage', 'record' => $user->getKey()])
        ->call('openHasManyCreateModal', 'orders')
        ->set('relationModalValues.number', 'ORD-REFRESH-1')
        ->set('relationModalValues.status', 'new')
        ->call('saveRelationModal')
        ->assertSee('ORD-REFRESH-1');
});

it('hasMany create and edit respects authorization when policy exists', function (): void {
    Gate::policy(ShowOrder::class, DenyShowOrderPolicy::class);

    $user = ShowUser::query()->create(['name' => 'Denied Parent', 'email' => 'denied-parent@example.com']);
    $order = ShowOrder::query()->create([
        'show_user_id' => $user->getKey(),
        'number' => 'ORD-DENIED',
        'status' => 'new',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-orders-manage', 'record' => $user->getKey()])
        ->call('openHasManyCreateModal', 'orders')
        ->assertForbidden();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-orders-manage', 'record' => $user->getKey()])
        ->call('openHasManyEditModal', 'orders', $order->getKey())
        ->assertForbidden();
});

it('hasMany direct saveRelationModal calls are blocked when create or edit is not allowed', function (): void {
    $user = ShowUser::query()->create(['name' => 'Bypass Guard Parent', 'email' => 'bypass-guard@example.com']);
    $order = ShowOrder::query()->create([
        'show_user_id' => $user->getKey(),
        'number' => 'ORD-BYPASS',
        'status' => 'new',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-orders', 'record' => $user->getKey()])
        ->set('relationModalRelationship', 'orders')
        ->set('relationModalMode', 'create')
        ->set('relationModalValues.number', 'ORD-SHOULD-NOT-CREATE')
        ->set('relationModalValues.status', 'new')
        ->call('saveRelationModal')
        ->assertSee('Creating related records is not allowed for this relation.');

    expect(ShowOrder::query()->where('number', 'ORD-SHOULD-NOT-CREATE')->exists())->toBeFalse();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-orders', 'record' => $user->getKey()])
        ->set('relationModalRelationship', 'orders')
        ->set('relationModalMode', 'edit')
        ->set('relationModalRecordId', (string) $order->getKey())
        ->set('relationModalValues.number', 'ORD-BYPASS')
        ->set('relationModalValues.status', 'paid')
        ->call('saveRelationModal')
        ->assertSee('Editing related records is not allowed for this relation.');

    expect($order->fresh()?->status)->toBe('new');
});

it('hasMany delete hidden by default', function (): void {
    $user = ShowUser::query()->create(['name' => 'No Delete Parent', 'email' => 'no-delete-parent@example.com']);
    ShowOrder::query()->create([
        'show_user_id' => $user->getKey(),
        'number' => 'ORD-NO-DELETE',
        'status' => 'new',
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-orders-manage/'.$user->getKey())
        ->assertOk()
        ->assertDontSee('Move to trash');
});

it('hasMany delete appears when allowDelete', function (): void {
    $user = ShowUser::query()->create(['name' => 'Delete Visible Parent', 'email' => 'delete-visible@example.com']);
    ShowSoftOrder::query()->create([
        'show_user_id' => $user->getKey(),
        'number' => 'SOFT-DELETE-1',
        'status' => 'new',
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-soft-orders-manage/'.$user->getKey())
        ->assertOk()
        ->assertSee('Move to trash');
});

it('hasMany child can be soft deleted with confirmation', function (): void {
    $user = ShowUser::query()->create(['name' => 'Soft Delete Parent', 'email' => 'soft-delete-parent@example.com']);
    $order = ShowSoftOrder::query()->create([
        'show_user_id' => $user->getKey(),
        'number' => 'SOFT-DELETE-2',
        'status' => 'new',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-soft-orders-manage', 'record' => $user->getKey()])
        ->call('deleteHasManyRecord', 'softOrders', $order->getKey())
        ->assertSee('You are about to move this related record to trash.');

    expect(ShowSoftOrder::withTrashed()->find($order->getKey())?->trashed())->toBeFalse();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-soft-orders-manage', 'record' => $user->getKey()])
        ->call('deleteHasManyRecord', 'softOrders', $order->getKey(), true)
        ->assertSee('Related record moved to trash successfully.');

    expect(ShowSoftOrder::withTrashed()->find($order->getKey())?->trashed())->toBeTrue();
});

it('cannot delete child outside parent relation', function (): void {
    $firstUser = ShowUser::query()->create(['name' => 'First Delete Guard', 'email' => 'first-delete-guard@example.com']);
    $secondUser = ShowUser::query()->create(['name' => 'Second Delete Guard', 'email' => 'second-delete-guard@example.com']);
    $secondOrder = ShowSoftOrder::query()->create([
        'show_user_id' => $secondUser->getKey(),
        'number' => 'SOFT-OUTSIDE',
        'status' => 'new',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-soft-orders-manage', 'record' => $firstUser->getKey()])
        ->call('deleteHasManyRecord', 'softOrders', $secondOrder->getKey(), true)
        ->assertSee('not available');
});

it('restore appears for trashed soft-deletable child and can restore', function (): void {
    $user = ShowUser::query()->create(['name' => 'Restore Parent', 'email' => 'restore-parent@example.com']);
    $order = ShowSoftOrder::query()->create([
        'show_user_id' => $user->getKey(),
        'number' => 'SOFT-RESTORE-1',
        'status' => 'new',
    ]);
    $order->delete();

    pest_test_case()->get('/admin/resources/show-users-with-soft-orders-manage/'.$user->getKey())
        ->assertOk()
        ->assertSee('Restore');

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-soft-orders-manage', 'record' => $user->getKey()])
        ->call('restoreHasManyRecord', 'softOrders', $order->getKey(), true)
        ->assertSee('Related record restored successfully.');

    expect(ShowSoftOrder::withTrashed()->find($order->getKey())?->trashed())->toBeFalse();
});

it('hasMany delete and restore respect policies', function (): void {
    Gate::policy(ShowSoftOrder::class, DenyShowSoftOrderPolicy::class);

    $user = ShowUser::query()->create(['name' => 'Denied Soft Parent', 'email' => 'denied-soft-parent@example.com']);
    $order = ShowSoftOrder::query()->create([
        'show_user_id' => $user->getKey(),
        'number' => 'SOFT-DENIED',
        'status' => 'new',
    ]);

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-soft-orders-manage', 'record' => $user->getKey()])
        ->call('deleteHasManyRecord', 'softOrders', $order->getKey(), true)
        ->assertForbidden();

    $order->delete();

    Livewire::test(ResourceShow::class, ['resource' => 'show-users-with-soft-orders-manage', 'record' => $user->getKey()])
        ->call('restoreHasManyRecord', 'softOrders', $order->getKey(), true)
        ->assertForbidden();
});

it('show page relation manager displays related records only', function (): void {
    $firstUser = ShowUser::query()->create([
        'name' => 'First Parent',
        'email' => 'first-parent@example.com',
    ]);
    $secondUser = ShowUser::query()->create([
        'name' => 'Second Parent',
        'email' => 'second-parent@example.com',
    ]);

    ShowOrder::query()->create([
        'show_user_id' => $firstUser->getKey(),
        'number' => 'ORD-A',
        'status' => 'paid',
    ]);
    ShowOrder::query()->create([
        'show_user_id' => $secondUser->getKey(),
        'number' => 'ORD-B',
        'status' => 'new',
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-orders/'.$firstUser->getKey())
        ->assertOk()
        ->assertSee('ORD-A')
        ->assertDontSee('ORD-B');
});

it('empty hasMany relation shows safe empty state', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'No Orders User',
        'email' => 'no-orders@example.com',
    ]);

    pest_test_case()->get('/admin/resources/show-users-with-orders/'.$user->getKey())
        ->assertOk()
        ->assertSee('Orders')
        ->assertSee('sf-table-empty', false)
        ->assertSee('No related records to display.');
});

it('relation records are paginated', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Paginated Parent',
        'email' => 'paginated-parent@example.com',
    ]);

    foreach (range(1, 4) as $index) {
        ShowOrder::query()->create([
            'show_user_id' => $user->getKey(),
            'number' => 'ORD-P'.$index,
            'status' => 'paid',
        ]);
    }

    pest_test_case()->get('/admin/resources/show-users-with-orders/'.$user->getKey())
        ->assertOk()
        ->assertSee('ORD-P1')
        ->assertSee('ORD-P2')
        ->assertDontSee('ORD-P3')
        ->assertDontSee('ORD-P4');
});

it('relation manager does not load all related records', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Large Relation Parent',
        'email' => 'large-relation@example.com',
    ]);

    foreach (range(1, 25) as $index) {
        ShowOrder::query()->create([
            'show_user_id' => $user->getKey(),
            'number' => 'ORD-L'.$index,
            'status' => 'new',
        ]);
    }

    pest_test_case()->get('/admin/resources/show-users-with-orders/'.$user->getKey())
        ->assertOk()
        ->assertSee('ORD-L1')
        ->assertSee('ORD-L2')
        ->assertDontSee('ORD-L25');
});

it('invalid hasMany relation fails clearly', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Invalid Relation Parent',
        'email' => 'invalid-relation@example.com',
    ]);

    pest_test_case()->withoutExceptionHandling();

    expect(fn () => pest_test_case()->get('/admin/resources/show-users-invalid-orders/'.$user->getKey()))
        ->toThrow(InvalidArgumentException::class, 'must be an instance');
});
