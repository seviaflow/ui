<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Core\Auth\Permissions\PermissionAbilities;
use SeviaFlow\Core\Auth\Roles\Permission;
use SeviaFlow\Core\Auth\Roles\Role;
use SeviaFlow\Core\Auth\Roles\RoleAssignment;
use SeviaFlow\Core\Resources\Action;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Ui\Livewire\ResourceForm;
use SeviaFlow\Ui\Livewire\ResourceIndex;
use SeviaFlow\Ui\Livewire\ResourceShow;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    config()->set('seviaflow.resources', [
        'policy-users' => PolicyUserResource::class,
    ]);

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->boolean('can_view_any')->default(false);
        $table->boolean('can_view')->default(false);
        $table->boolean('can_create')->default(false);
        $table->boolean('can_update')->default(false);
        $table->boolean('can_delete')->default(false);
        $table->boolean('can_run_action')->default(false);
        $table->timestamps();
    });

    Schema::create('policy_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });

    if (! Schema::hasTable('seviaflow_saved_views')) {
        Schema::create('seviaflow_saved_views', function (Blueprint $table): void {
            $table->id();
            $table->string('resource');
            $table->string('name');
            $table->string('visibility');
            $table->string('user_type');
            $table->unsignedBigInteger('user_id');
            $table->json('state');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

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
        });
    }

    if (! Schema::hasTable('seviaflow_permissions')) {
        Schema::create('seviaflow_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('seviaflow_roles')->cascadeOnDelete();
            $table->string('resource');
            $table->string('ability');
            $table->boolean('allowed')->default(false);
            $table->timestamps();
        });
    }

    Gate::policy(PolicyUser::class, PolicyUserPolicy::class);
});

it('blocks index when user cannot view any records', function (): void {
    $user = PolicyAuthUser::query()->create([
        'name' => 'No ViewAny',
        'can_view_any' => false,
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/policy-users')->assertForbidden();
});

it('blocks show page when user cannot view record', function (): void {
    $record = PolicyUser::query()->create([
        'name' => 'Policy Record',
    ]);
    $user = PolicyAuthUser::query()->create([
        'name' => 'No View',
        'can_view_any' => true,
        'can_view' => false,
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/policy-users/'.$record->getKey())->assertForbidden();
});

it('blocks create form when user cannot create', function (): void {
    $user = PolicyAuthUser::query()->create([
        'name' => 'No Create',
        'can_view_any' => true,
        'can_create' => false,
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/policy-users/create')->assertForbidden();
});

it('blocks edit form when user cannot update', function (): void {
    $record = PolicyUser::query()->create([
        'name' => 'No Update Record',
    ]);
    $user = PolicyAuthUser::query()->create([
        'name' => 'No Update',
        'can_view_any' => true,
        'can_update' => false,
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/policy-users/'.$record->getKey().'/edit')->assertForbidden();
});

it('blocks save when user cannot update', function (): void {
    $record = PolicyUser::query()->create([
        'name' => 'Before Save',
    ]);
    $user = PolicyAuthUser::query()->create([
        'name' => 'No Update Save',
        'can_view_any' => true,
        'can_view' => true,
        'can_update' => false,
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->withoutExceptionHandling();

    expect(fn () => Livewire::test(ResourceForm::class, ['resource' => 'policy-users', 'record' => $record->getKey()])
        ->set('fieldValues.name', 'After Save')
        ->call('save'))
        ->toThrow(HttpException::class);
});

it('blocks delete when user cannot delete', function (): void {
    $record = PolicyUser::query()->create([
        'name' => 'Delete Protected',
    ]);
    $user = PolicyAuthUser::query()->create([
        'name' => 'No Delete',
        'can_view_any' => true,
        'can_delete' => false,
    ]);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(ResourceIndex::class, ['resource' => 'policy-users'])
        ->call('deleteRecord', (string) $record->getKey())
        ->assertForbidden();
});

it('hides create, edit and delete buttons when unauthorized', function (): void {
    $record = PolicyUser::query()->create([
        'name' => 'UI Restricted',
    ]);
    $user = PolicyAuthUser::query()->create([
        'name' => 'No Create Update Delete',
        'can_view_any' => true,
        'can_view' => true,
        'can_create' => false,
        'can_update' => false,
        'can_delete' => false,
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/policy-users')
        ->assertOk()
        ->assertDontSee('/admin/resources/policy-users/create', false)
        ->assertDontSee('/admin/resources/policy-users/'.$record->getKey().'/edit', false)
        ->assertDontSee('wire:click="deleteRecord"', false);
});

it('allows authorized user to create update delete and view', function (): void {
    $user = PolicyAuthUser::query()->create([
        'name' => 'All Allowed',
        'can_view_any' => true,
        'can_view' => true,
        'can_create' => true,
        'can_update' => true,
        'can_delete' => true,
        'can_run_action' => true,
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/policy-users')->assertOk();

    Livewire::test(ResourceForm::class, ['resource' => 'policy-users'])
        ->set('fieldValues.name', 'Created By Policy User')
        ->call('save')
        ->assertSee('Record saved successfully.');

    $record = PolicyUser::query()->where('name', 'Created By Policy User')->first();

    expect($record)->not->toBeNull();

    Livewire::test(ResourceForm::class, ['resource' => 'policy-users', 'record' => $record?->getKey()])
        ->set('fieldValues.name', 'Updated By Policy User')
        ->call('save')
        ->assertSee('Record saved successfully.');

    $record?->refresh();
    expect($record?->name)->toBe('Updated By Policy User');

    Livewire::test(ResourceIndex::class, ['resource' => 'policy-users'])
        ->call('deleteRecord', (string) $record?->getKey())
        ->assertSee('Record deleted successfully.');

    expect(PolicyUser::query()->find($record?->getKey()))->toBeNull();
});

it('hides and blocks unauthorized actions', function (): void {
    $record = PolicyUser::query()->create([
        'name' => 'Action Restricted',
        'is_active' => false,
    ]);
    $user = PolicyAuthUser::query()->create([
        'name' => 'No Action',
        'can_view_any' => true,
        'can_view' => true,
        'can_update' => true,
        'can_run_action' => false,
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/policy-users')
        ->assertOk()
        ->assertDontSee('Activate account');

    Livewire::test(ResourceShow::class, ['resource' => 'policy-users', 'record' => $record->getKey()])
        ->call('executeAction', 'activate')
        ->assertForbidden();
});

it('allows when policy allows and permission allows', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $user = PolicyAuthUser::query()->create([
        'name' => 'Policy And Permission Allow',
        'can_view_any' => true,
    ]);
    grantPolicyUserPermission($user, PermissionAbilities::VIEW_ANY);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/policy-users')->assertOk();
});

it('denies when policy denies and permission allows', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $user = PolicyAuthUser::query()->create([
        'name' => 'Policy Deny Permission Allow',
        'can_view_any' => false,
    ]);
    grantPolicyUserPermission($user, PermissionAbilities::VIEW_ANY);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/policy-users')->assertForbidden();
});

it('denies when policy allows and permission denies', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $user = PolicyAuthUser::query()->create([
        'name' => 'Policy Allow Permission Deny',
        'can_view_any' => true,
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/policy-users')->assertForbidden();
});

it('uses policy only when permissions are disabled and policy allows', function (): void {
    config()->set('seviaflow.permissions.enabled', false);

    $user = PolicyAuthUser::query()->create([
        'name' => 'Policy Only Allow',
        'can_view_any' => true,
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/policy-users')->assertOk();
});

it('uses policy only when permissions are disabled and policy denies', function (): void {
    config()->set('seviaflow.permissions.enabled', false);

    $user = PolicyAuthUser::query()->create([
        'name' => 'Policy Only Deny',
        'can_view_any' => false,
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/policy-users')->assertForbidden();
});

it('enforces combined authorization on backend operations', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $record = PolicyUser::query()->create([
        'name' => 'Combined Backend Protected',
    ]);
    $user = PolicyAuthUser::query()->create([
        'name' => 'Backend Combined',
        'can_view_any' => true,
        'can_delete' => false,
    ]);
    grantPolicyUserPermission($user, PermissionAbilities::VIEW_ANY);
    grantPolicyUserPermission($user, PermissionAbilities::DELETE);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(ResourceIndex::class, ['resource' => 'policy-users'])
        ->call('deleteRecord', (string) $record->getKey())
        ->assertForbidden();
});

it('enforces combined authorization in ui visibility', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $record = PolicyUser::query()->create([
        'name' => 'UI Combined Protected',
    ]);
    $user = PolicyAuthUser::query()->create([
        'name' => 'UI Combined',
        'can_view_any' => true,
        'can_view' => true,
        'can_create' => true,
        'can_update' => true,
        'can_delete' => true,
    ]);

    grantPolicyUserPermission($user, PermissionAbilities::VIEW_ANY);
    grantPolicyUserPermission($user, PermissionAbilities::VIEW);
    // Intentionally not granting create/update/delete to verify UI hides these controls.

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/policy-users')
        ->assertOk()
        ->assertDontSee('Create')
        ->assertSee('/admin/resources/policy-users/'.$record->getKey(), false)
        ->assertDontSee('/admin/resources/policy-users/'.$record->getKey().'/edit', false)
        ->assertDontSee('wire:click="deleteRecord"', false);
});

it('enforces combined authorization for action execution', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $record = PolicyUser::query()->create([
        'name' => 'Action Combined Protected',
        'is_active' => false,
    ]);
    $user = PolicyAuthUser::query()->create([
        'name' => 'Action Combined',
        'can_view_any' => true,
        'can_view' => true,
        'can_update' => false,
        'can_run_action' => true,
    ]);

    grantPolicyUserPermission($user, PermissionAbilities::VIEW_ANY);
    grantPolicyUserPermission($user, PermissionAbilities::VIEW);
    grantPolicyUserPermission($user, PermissionAbilities::UPDATE);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(ResourceShow::class, ['resource' => 'policy-users', 'record' => $record->getKey()])
        ->call('executeAction', 'activate')
        ->assertForbidden();
});

function grantPolicyUserPermission(PolicyAuthUser $user, string $ability): void
{
    $slug = sprintf('policy-%s-%s', $user->getKey(), $ability);
    $role = Role::query()->create([
        'name' => 'Policy Role '.$slug,
        'slug' => $slug,
    ]);

    RoleAssignment::query()->create([
        'role_id' => $role->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    Permission::query()->create([
        'role_id' => $role->id,
        'resource' => 'policy-users',
        'ability' => $ability,
        'allowed' => true,
    ]);
}

final class PolicyUserResource extends Resource
{
    protected static string $model = PolicyUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
            TextColumn::make('is_active')->label('Active'),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->label('Name'),
        ]);
    }

    public static function actions(): array
    {
        return [
            Action::make('activate')
                ->label('Activate account')
                ->successMessage('Account activated.')
                ->authorize(fn (PolicyAuthUser $user, PolicyUser $record): bool => (bool) $user->can_run_action)
                ->handle(function (PolicyUser $record): void {
                    $record->is_active = true;
                    $record->save();
                }),
        ];
    }
}

/**
 * @property string $name
 * @property bool $is_active
 */
final class PolicyUser extends Model
{
    protected $table = 'policy_users';

    protected $guarded = [];
}

/**
 * @property bool $can_run_action
 * @property bool $can_view_any
 * @property bool $can_view
 * @property bool $can_create
 * @property bool $can_update
 * @property bool $can_delete
 */
final class PolicyAuthUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}

final class PolicyUserPolicy
{
    public function viewAny(PolicyAuthUser $user): bool
    {
        return (bool) $user->can_view_any;
    }

    public function view(PolicyAuthUser $user, PolicyUser $record): bool
    {
        return (bool) $user->can_view;
    }

    public function create(PolicyAuthUser $user): bool
    {
        return (bool) $user->can_create;
    }

    public function update(PolicyAuthUser $user, PolicyUser $record): bool
    {
        return (bool) $user->can_update;
    }

    public function delete(PolicyAuthUser $user, PolicyUser $record): bool
    {
        return (bool) $user->can_delete;
    }
}
