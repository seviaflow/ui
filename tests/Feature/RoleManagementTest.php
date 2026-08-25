<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Core\Auth\Permissions\PermissionAbilities;
use SeviaFlow\Core\Auth\Roles\Permission;
use SeviaFlow\Core\Auth\Roles\Role;
use SeviaFlow\Core\Auth\Roles\RoleAssignment;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Ui\Livewire\RoleForm;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('seviaflow.resources', [
        'test-users' => RoleTestUserResource::class,
        'orders' => RoleOrderResource::class,
    ]);

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Schema::create('role_test_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('role_orders', function (Blueprint $table): void {
        $table->id();
        $table->string('number');
        $table->softDeletes();
        $table->timestamps();
    });
});

it('roles list renders', function (): void {
    $user = RoleManagementUser::query()->create(['name' => 'Admin']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/settings/roles')
        ->assertOk()
        ->assertSee('SeviaFlow')
        ->assertSee('/admin/resources/test-users', false)
        ->assertSee('/admin/settings/roles', false)
        ->assertSee('sf-sidebar-link-active', false)
        ->assertSee('Roles')
        ->assertSee('sf-table-wrapper', false)
        ->assertSee('sf-table', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.css', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.js', false)
        ->assertDontSee('@vite', false);
});

it('role create form renders', function (): void {
    $user = RoleManagementUser::query()->create(['name' => 'Admin']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/settings/roles/create')
        ->assertOk()
        ->assertSee('Resource permissions')
        ->assertSee('Role details')
        ->assertSee('sf-card', false)
        ->assertSee('sf-checkbox', false);
});

it('role can be created', function (): void {
    $user = RoleManagementUser::query()->create(['name' => 'Admin']);
    pest_test_case()->actingAs($user, 'web');

    Livewire::test(RoleForm::class)
        ->set('name', 'Support')
        ->set('slug', 'support')
        ->set('permissions.test-users.viewAny', true)
        ->set('permissions.test-users.view', true)
        ->call('save')
        ->assertSee('Role saved successfully.');

    $role = Role::query()->where('slug', 'support')->first();

    expect($role)->not->toBeNull()
        ->and($role?->permissions()->where('resource', 'test-users')->where('ability', PermissionAbilities::VIEW_ANY)->exists())
        ->toBeTrue()
        ->and($role?->permissions()->where('resource', 'test-users')->where('ability', PermissionAbilities::VIEW)->exists())
        ->toBeTrue();
});

it('role can be edited', function (): void {
    $user = RoleManagementUser::query()->create(['name' => 'Admin']);
    $role = Role::query()->create(['name' => 'Support', 'slug' => 'support']);
    pest_test_case()->actingAs($user, 'web');

    Livewire::test(RoleForm::class, ['role' => $role->getKey()])
        ->set('name', 'Support Team')
        ->set('slug', 'support-team')
        ->call('save')
        ->assertSee('Role saved successfully.');

    $role->refresh();
    expect($role->name)->toBe('Support Team')
        ->and($role->slug)->toBe('support-team');
});

it('permission matrix includes registered resources', function (): void {
    $user = RoleManagementUser::query()->create(['name' => 'Admin']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/settings/roles/create')
        ->assertOk()
        ->assertSee('Test Users')
        ->assertSee('Orders')
        ->assertSee('View list')
        ->assertSee('View details')
        ->assertSee('Create')
        ->assertSee('Edit')
        ->assertSee('Delete')
        ->assertSee('Restore')
        ->assertSee('Permanently delete');
});

it('permission checkboxes save correctly', function (): void {
    $user = RoleManagementUser::query()->create(['name' => 'Admin']);
    pest_test_case()->actingAs($user, 'web');

    Livewire::test(RoleForm::class)
        ->set('name', 'Ops')
        ->set('slug', 'ops')
        ->set('permissions.orders.restore', true)
        ->set('permissions.orders.forceDelete', true)
        ->set('permissions.orders.update', true)
        ->call('save');

    $role = Role::query()->where('slug', 'ops')->firstOrFail();

    expect($role->permissions()->where('resource', 'orders')->where('ability', PermissionAbilities::RESTORE)->exists())->toBeTrue()
        ->and($role->permissions()->where('resource', 'orders')->where('ability', PermissionAbilities::FORCE_DELETE)->exists())->toBeTrue()
        ->and($role->permissions()->where('resource', 'orders')->where('ability', PermissionAbilities::UPDATE)->exists())->toBeTrue();
});

it('unchecked permissions are removed consistently', function (): void {
    $user = RoleManagementUser::query()->create(['name' => 'Admin']);
    $role = Role::query()->create(['name' => 'Ops', 'slug' => 'ops']);
    $role->permissions()->create([
        'resource' => 'test-users',
        'ability' => PermissionAbilities::DELETE,
        'allowed' => true,
    ]);
    pest_test_case()->actingAs($user, 'web');

    Livewire::test(RoleForm::class, ['role' => $role->getKey()])
        ->set('permissions.test-users.delete', false)
        ->call('save');

    expect($role->fresh()?->permissions()->where('resource', 'test-users')->where('ability', PermissionAbilities::DELETE)->exists())
        ->toBeFalse();
});

it('unauthorized user cannot access role management when permissions are enabled', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $user = RoleManagementUser::query()->create(['name' => 'Unauthorized']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/settings/roles')->assertForbidden();
});

it('authorized user can access role management when permissions are enabled', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $user = RoleManagementUser::query()->create(['name' => 'Authorized']);
    grantSettingsPermission($user);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/settings/roles')->assertOk();
});

function grantSettingsPermission(RoleManagementUser $user): void
{
    $role = Role::query()->create([
        'name' => 'Settings Manager',
        'slug' => 'settings-manager-'.$user->getKey(),
    ]);

    RoleAssignment::query()->create([
        'role_id' => $role->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    Permission::query()->create([
        'role_id' => $role->id,
        'resource' => '__settings.roles',
        'ability' => PermissionAbilities::UPDATE,
        'allowed' => true,
    ]);
}

final class RoleTestUserResource extends Resource
{
    protected static string $model = RoleTestUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }
}

final class RoleOrderResource extends Resource
{
    protected static string $model = RoleOrder::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->label('Number'),
        ]);
    }
}

final class RoleTestUser extends Model
{
    protected $table = 'role_test_users';

    protected $guarded = [];
}

final class RoleOrder extends Model
{
    use SoftDeletes;

    protected $table = 'role_orders';

    protected $guarded = [];
}

final class RoleManagementUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}
