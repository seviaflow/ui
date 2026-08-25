<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Core\Auth\Permissions\PermissionAbilities;
use SeviaFlow\Core\Auth\Roles\Permission;
use SeviaFlow\Core\Auth\Roles\Role;
use SeviaFlow\Core\Auth\Roles\RoleAssignment;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Ui\Livewire\ResourceForm;
use SeviaFlow\Ui\Livewire\ResourceIndex;

beforeEach(function (): void {
    config()->set('seviaflow.resources', [
        'permission-users' => PermissionResourceUserResource::class,
        'permission-soft-users' => PermissionSoftUserResource::class,
    ]);

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Schema::create('permission_resource_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('permission_soft_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->softDeletes();
        $table->timestamps();
    });

    if (! Schema::hasTable('seviaflow_roles')) {
        Schema::create('seviaflow_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('seviaflow_roleables')) {
        Schema::create('seviaflow_roleables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('seviaflow_roles')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->timestamps();

            $table->index(['model_type', 'model_id'], 'seviaflow_roleables_model_lookup');
            $table->unique(['role_id', 'model_type', 'model_id'], 'seviaflow_roleables_unique_assignment');
        });
    }

    if (! Schema::hasTable('seviaflow_permissions')) {
        Schema::create('seviaflow_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('seviaflow_roles')->cascadeOnDelete();
            $table->string('resource');
            $table->string('ability');
            $table->boolean('allowed')->default(true);
            $table->timestamps();

            $table->unique(['role_id', 'resource', 'ability'], 'seviaflow_permissions_role_resource_ability_unique');
            $table->index(['resource', 'ability'], 'seviaflow_permissions_resource_ability_index');
        });
    }

    if (! Schema::hasTable('seviaflow_saved_views')) {
        Schema::create('seviaflow_saved_views', function (Blueprint $table): void {
            $table->id();
            $table->string('resource');
            $table->string('name');
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->string('visibility')->default('private');
            $table->json('state');
            $table->timestamps();
        });
    }
});

it('permissions disabled does not block existing access', function (): void {
    config()->set('seviaflow.permissions.enabled', false);

    $user = PermissionAuthUser::query()->create(['name' => 'No Roles Needed']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/permission-users')->assertOk();
});

it('permissions enabled blocks index without viewAny', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $user = PermissionAuthUser::query()->create(['name' => 'No ViewAny']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/permission-users')->assertForbidden();
});

it('viewAny grants index', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    PermissionResourceUser::query()->create(['name' => 'Visible Record']);
    $user = PermissionAuthUser::query()->create(['name' => 'Can ViewAny']);
    grantPermission($user, 'permission-users', PermissionAbilities::VIEW_ANY);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/permission-users')
        ->assertOk()
        ->assertSee('Visible Record');
});

it('view grants show', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $record = PermissionResourceUser::query()->create(['name' => 'Show Me']);
    $user = PermissionAuthUser::query()->create(['name' => 'Can View']);
    grantPermission($user, 'permission-users', PermissionAbilities::VIEW_ANY);
    grantPermission($user, 'permission-users', PermissionAbilities::VIEW);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/permission-users/'.$record->getKey())
        ->assertOk()
        ->assertSee('Show Me');
});

it('create grants create and save', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $user = PermissionAuthUser::query()->create(['name' => 'Creator']);
    grantPermission($user, 'permission-users', PermissionAbilities::CREATE);
    pest_test_case()->actingAs($user, 'web');

    Livewire::test(ResourceForm::class, ['resource' => 'permission-users'])
        ->set('fieldValues.name', 'Created Through Permission')
        ->call('save')
        ->assertSee('Record saved successfully.');

    expect(PermissionResourceUser::query()->where('name', 'Created Through Permission')->exists())->toBeTrue();
});

it('update grants edit and save', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $record = PermissionResourceUser::query()->create(['name' => 'Before Update']);
    $user = PermissionAuthUser::query()->create(['name' => 'Editor']);
    grantPermission($user, 'permission-users', PermissionAbilities::UPDATE);
    pest_test_case()->actingAs($user, 'web');

    Livewire::test(ResourceForm::class, ['resource' => 'permission-users', 'record' => $record->getKey()])
        ->set('fieldValues.name', 'After Update')
        ->call('save')
        ->assertSee('Record saved successfully.');

    $record->refresh();
    expect($record->name)->toBe('After Update');
});

it('delete grants delete', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $record = PermissionResourceUser::query()->create(['name' => 'Delete Me']);
    $user = PermissionAuthUser::query()->create(['name' => 'Deleter']);
    grantPermission($user, 'permission-users', PermissionAbilities::VIEW_ANY);
    grantPermission($user, 'permission-users', PermissionAbilities::DELETE);
    pest_test_case()->actingAs($user, 'web');

    Livewire::test(ResourceIndex::class, ['resource' => 'permission-users'])
        ->call('deleteRecord', (string) $record->getKey())
        ->assertSee('Record deleted successfully.');

    expect(PermissionResourceUser::query()->find($record->getKey()))->toBeNull();
});

it('restore grants restore for soft deletable resources', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $record = PermissionSoftUser::query()->create(['name' => 'Restore Me']);
    $record->delete();

    $user = PermissionAuthUser::query()->create(['name' => 'Restorer']);
    grantPermission($user, 'permission-soft-users', PermissionAbilities::VIEW_ANY);
    grantPermission($user, 'permission-soft-users', PermissionAbilities::RESTORE);
    pest_test_case()->actingAs($user, 'web');

    Livewire::test(ResourceIndex::class, ['resource' => 'permission-soft-users'])
        ->set('trashedScope', 'with')
        ->call('restoreRecord', (string) $record->getKey())
        ->assertSee('Record restored successfully.');

    expect(PermissionSoftUser::withTrashed()->find($record->getKey())?->trashed())->toBeFalse();
});

it('hides buttons when permission missing', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $record = PermissionResourceUser::query()->create(['name' => 'Hidden Controls']);
    $user = PermissionAuthUser::query()->create(['name' => 'ViewAny Only']);
    grantPermission($user, 'permission-users', PermissionAbilities::VIEW_ANY);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/permission-users')
        ->assertOk()
        ->assertDontSee('Create')
        ->assertDontSee('/admin/resources/permission-users/'.$record->getKey(), false)
        ->assertDontSee('/admin/resources/permission-users/'.$record->getKey().'/edit', false)
        ->assertDontSee('wire:click="deleteRecord"', false);
});

it('backend rejects unauthorized crafted operations', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $record = PermissionResourceUser::query()->create(['name' => 'Protected']);
    $user = PermissionAuthUser::query()->create(['name' => 'Crafted Request']);
    grantPermission($user, 'permission-users', PermissionAbilities::VIEW_ANY);
    pest_test_case()->actingAs($user, 'web');

    Livewire::test(ResourceIndex::class, ['resource' => 'permission-users'])
        ->call('deleteRecord', (string) $record->getKey())
        ->assertForbidden();
});

function grantPermission(PermissionAuthUser $user, string $resource, string $ability): void
{
    $slug = sprintf('%s-%s-%s', $user->getKey(), $resource, $ability);

    $role = Role::query()->create([
        'name' => 'Role '.$slug,
        'slug' => $slug,
    ]);

    RoleAssignment::query()->create([
        'role_id' => $role->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    Permission::query()->create([
        'role_id' => $role->id,
        'resource' => $resource,
        'ability' => $ability,
        'allowed' => true,
    ]);
}

final class PermissionResourceUserResource extends Resource
{
    protected static string $model = PermissionResourceUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name'),
        ]);
    }
}

final class PermissionSoftUserResource extends Resource
{
    protected static string $model = PermissionSoftUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }
}

/**
 * @property string $name
 */
final class PermissionResourceUser extends Model
{
    protected $table = 'permission_resource_users';

    protected $guarded = [];
}

final class PermissionSoftUser extends Model
{
    use SoftDeletes;

    protected $table = 'permission_soft_users';

    protected $guarded = [];
}

final class PermissionAuthUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}
