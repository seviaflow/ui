<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Core\Auth\Permissions\PermissionAbilities;
use SeviaFlow\Core\Auth\Permissions\PermissionChecker;
use SeviaFlow\Core\Auth\Roles\Permission;
use SeviaFlow\Core\Auth\Roles\Role;
use SeviaFlow\Core\Auth\Roles\RoleAssignment;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Ui\Livewire\AdminUserRolesForm;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('seviaflow.resources', [
        'users' => AssignmentUsersResource::class,
    ]);

    config()->set('seviaflow.permissions.user_model', null);
    config()->set('auth.guards.web.provider', 'users');
    config()->set('auth.providers.users.model', AssignmentAdminUser::class);

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Schema::create('assignment_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

it('admin users list renders when user model is resolved', function (): void {
    $admin = AssignmentAdminUser::query()->create(['name' => 'Admin']);
    $target = AssignmentAdminUser::query()->create(['name' => 'Target']);
    pest_test_case()->actingAs($admin, 'web');

    pest_test_case()->get('/admin/settings/admin-users')
        ->assertOk()
        ->assertSee('Admin users')
        ->assertSee('sf-table-wrapper', false)
        ->assertSee('sf-table', false)
        ->assertSee((string) $target->name);
});

it('role assignment page renders', function (): void {
    $admin = AssignmentAdminUser::query()->create(['name' => 'Admin']);
    $target = AssignmentAdminUser::query()->create(['name' => 'Target']);
    $role = Role::query()->create(['name' => 'Support', 'slug' => 'support']);
    pest_test_case()->actingAs($admin, 'web');

    pest_test_case()->get('/admin/settings/admin-users/'.$target->getKey().'/roles')
        ->assertOk()
        ->assertSee('Assign roles')
        ->assertSee('sf-checkbox', false)
        ->assertSee('sf-button sf-button-primary', false)
        ->assertSee($role->name);
});

it('roles can be assigned to user', function (): void {
    $admin = AssignmentAdminUser::query()->create(['name' => 'Admin']);
    $target = AssignmentAdminUser::query()->create(['name' => 'Target']);
    $role = Role::query()->create(['name' => 'Support', 'slug' => 'support']);
    pest_test_case()->actingAs($admin, 'web');

    Livewire::test(AdminUserRolesForm::class, ['user' => $target->getKey()])
        ->set('selectedRoleIds', [(int) $role->getKey()])
        ->call('save')
        ->assertSee('Roles updated successfully.');

    expect(RoleAssignment::query()
        ->where('model_type', $target->getMorphClass())
        ->where('model_id', $target->getKey())
        ->where('role_id', $role->getKey())
        ->exists())->toBeTrue();
});

it('roles can be removed from user', function (): void {
    $admin = AssignmentAdminUser::query()->create(['name' => 'Admin']);
    $target = AssignmentAdminUser::query()->create(['name' => 'Target']);
    $role = Role::query()->create(['name' => 'Support', 'slug' => 'support']);

    RoleAssignment::query()->create([
        'role_id' => $role->getKey(),
        'model_type' => $target->getMorphClass(),
        'model_id' => $target->getKey(),
    ]);
    pest_test_case()->actingAs($admin, 'web');

    Livewire::test(AdminUserRolesForm::class, ['user' => $target->getKey()])
        ->set('selectedRoleIds', [])
        ->call('save')
        ->assertSee('Roles updated successfully.');

    expect(RoleAssignment::query()
        ->where('model_type', $target->getMorphClass())
        ->where('model_id', $target->getKey())
        ->exists())->toBeFalse();
});

it('multiple roles can be assigned', function (): void {
    $admin = AssignmentAdminUser::query()->create(['name' => 'Admin']);
    $target = AssignmentAdminUser::query()->create(['name' => 'Target']);
    $roleA = Role::query()->create(['name' => 'Support', 'slug' => 'support']);
    $roleB = Role::query()->create(['name' => 'Orders', 'slug' => 'orders']);
    pest_test_case()->actingAs($admin, 'web');

    Livewire::test(AdminUserRolesForm::class, ['user' => $target->getKey()])
        ->set('selectedRoleIds', [(int) $roleA->getKey(), (int) $roleB->getKey()])
        ->call('save');

    expect(RoleAssignment::query()
        ->where('model_type', $target->getMorphClass())
        ->where('model_id', $target->getKey())
        ->count())->toBe(2);
});

it('assigned roles affect permission checker', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $target = AssignmentAdminUser::query()->create(['name' => 'Target']);
    $role = Role::query()->create(['name' => 'Reader', 'slug' => 'reader']);
    Permission::query()->create([
        'role_id' => $role->getKey(),
        'resource' => 'users',
        'ability' => PermissionAbilities::VIEW_ANY,
        'allowed' => true,
    ]);

    $checker = new PermissionChecker;

    expect($checker->allows($target, 'users', PermissionAbilities::VIEW_ANY))->toBeFalse();

    RoleAssignment::query()->create([
        'role_id' => $role->getKey(),
        'model_type' => $target->getMorphClass(),
        'model_id' => $target->getKey(),
    ]);

    expect($checker->allows($target, 'users', PermissionAbilities::VIEW_ANY))->toBeTrue();
});

it('unauthorized user cannot access role assignment ui when permissions are enabled', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $admin = AssignmentAdminUser::query()->create(['name' => 'Unauthorized']);
    $target = AssignmentAdminUser::query()->create(['name' => 'Target']);
    pest_test_case()->actingAs($admin, 'web');

    pest_test_case()->get('/admin/settings/admin-users')->assertForbidden();
    pest_test_case()->get('/admin/settings/admin-users/'.$target->getKey().'/roles')->assertForbidden();
});

it('authorized user can access role assignment ui when permissions are enabled', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $admin = AssignmentAdminUser::query()->create(['name' => 'Authorized']);
    $target = AssignmentAdminUser::query()->create(['name' => 'Target']);
    grantAdminUsersSettingsPermission($admin);
    pest_test_case()->actingAs($admin, 'web');

    pest_test_case()->get('/admin/settings/admin-users')->assertOk();
    pest_test_case()->get('/admin/settings/admin-users/'.$target->getKey().'/roles')->assertOk();
});

function grantAdminUsersSettingsPermission(AssignmentAdminUser $admin): void
{
    $role = Role::query()->create([
        'name' => 'Admin Users Manager',
        'slug' => 'admin-users-manager-'.$admin->getKey(),
    ]);

    RoleAssignment::query()->create([
        'role_id' => $role->id,
        'model_type' => $admin->getMorphClass(),
        'model_id' => $admin->getKey(),
    ]);

    Permission::query()->create([
        'role_id' => $role->id,
        'resource' => '__settings.admin-users',
        'ability' => PermissionAbilities::UPDATE,
        'allowed' => true,
    ]);
}

final class AssignmentUsersResource extends Resource
{
    protected static string $model = AssignmentRecord::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }
}

final class AssignmentRecord extends Model
{
    protected $table = 'assignment_records';

    protected $guarded = [];
}

/**
 * @property string $name
 */
final class AssignmentAdminUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}
