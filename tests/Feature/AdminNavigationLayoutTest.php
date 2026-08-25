<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Table;

require __DIR__.'/Support/ResourceIndexSetup.php';

beforeEach(function (): void {
    Schema::create('test_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->foreignId('company_id')->nullable();
        $table->string('status')->default('draft');
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Schema::create('nav_hidden_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
    });

    Schema::create('seviaflow_roles', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->timestamps();
    });

    Schema::create('seviaflow_roleables', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('role_id')->nullable();
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->timestamps();
    });

    Schema::create('seviaflow_permissions', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('role_id')->nullable();
        $table->string('resource');
        $table->string('ability');
        $table->boolean('allowed')->default(true);
        $table->timestamps();
    });

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

    Schema::create('seviaflow_dashboard_layouts', function (Blueprint $table): void {
        $table->id();
        $table->string('dashboard');
        $table->string('user_type')->nullable();
        $table->string('user_id')->nullable();
        $table->json('layout');
        $table->timestamps();

        $table->unique(['dashboard', 'user_type', 'user_id'], 'seviaflow_dashboard_layouts_scope_unique');
        $table->index(['user_type', 'user_id'], 'seviaflow_dashboard_layouts_user_lookup_index');
    });
});

it('renders dashboard and resource links in sidebar', function (): void {
    config()->set('seviaflow.resources', [
        'test-users' => TestUserResource::class,
    ]);

    $user = LayoutNavUser::query()->create(['name' => 'Layout Nav User', 'email' => 'layout-nav@example.com']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/test-users')
        ->assertOk()
        ->assertSee(route('seviaflow.dashboard'), false)
        ->assertSee('/admin/resources/test-users', false);
});

it('renders system navigation group headings', function (): void {
    config()->set('seviaflow.resources', [
        'test-users' => TestUserResource::class,
    ]);

    $user = LayoutNavUser::query()->create(['name' => 'Group Heading User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/test-users')
        ->assertOk()
        ->assertSee('Main')
        ->assertSee('Resources')
        ->assertSee('Settings');
});

it('marks active resource link in sidebar', function (): void {
    config()->set('seviaflow.resources', [
        'test-users' => TestUserResource::class,
    ]);

    $user = LayoutNavUser::query()->create(['name' => 'Active Nav User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/test-users')
        ->assertOk()
        ->assertSee('sf-sidebar-link-active', false);
});

it('renders profile link and topbar identity for authenticated user', function (): void {
    $user = LayoutNavUser::query()->create([
        'name' => 'Operator Jane',
        'email' => 'jane.operator@example.com',
    ]);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('/admin/profile', false)
        ->assertSee('Operator Jane')
        ->assertSee('jane.operator@example.com');
});

it('renders logout form with post action', function (): void {
    $user = LayoutNavUser::query()->create(['name' => 'Logout Form User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('method="POST" action="'.route('seviaflow.logout').'"', false)
        ->assertSee('Sign out')
        ->assertDontSee('/admin/logout" class=', false);
});

it('hides unauthorized settings links', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $user = LayoutNavUser::query()->create(['name' => 'No Settings User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertDontSee('/admin/settings/roles', false)
        ->assertDontSee('/admin/settings/admin-users', false);
});

it('shows settings links when authorized', function (): void {
    config()->set('seviaflow.permissions.enabled', false);

    $user = LayoutNavUser::query()->create(['name' => 'Authorized Settings User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('/admin/settings/roles', false)
        ->assertSee('/admin/settings/admin-users', false);
});

it('does not show forbidden resources in sidebar', function (): void {
    config()->set('seviaflow.resources', [
        'test-users' => TestUserResource::class,
        'hidden-nav' => HiddenNavResource::class,
    ]);

    Gate::policy(HiddenNavRecord::class, HiddenNavPolicy::class);

    $user = LayoutNavUser::query()->create(['name' => 'No Forbidden Nav User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/test-users')
        ->assertOk()
        ->assertSee('/admin/resources/test-users', false)
        ->assertDontSee('/admin/resources/hidden-nav', false)
        ->assertDontSee('Hidden nav records');
});

it('allows direct access to authorized resources hidden from navigation', function (): void {
    config()->set('seviaflow.resources', [
        'hidden-visible-users' => HiddenVisibleNavResource::class,
    ]);

    $user = LayoutNavUser::query()->create(['name' => 'Direct Access User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/hidden-visible-users')
        ->assertOk()
        ->assertDontSee('/admin/resources/hidden-visible-users', false);
});

final class LayoutNavUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}

final class HiddenNavRecord extends Model
{
    protected $table = 'nav_hidden_records';

    protected $guarded = [];
}

final class HiddenNavResource extends Resource
{
    protected static string $model = HiddenNavRecord::class;

    public static function pluralLabel(): string
    {
        return 'Hidden nav records';
    }
}

final class HiddenVisibleNavResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function table(Table $table): Table
    {
        return TestUserResource::table($table);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}

final class HiddenNavPolicy
{
    public function viewAny(LayoutNavUser $user): bool
    {
        return false;
    }
}
