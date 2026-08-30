<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Ui\Navigation\NavigationMetadataCache;
use SeviaFlow\Ui\Navigation\NavigationMetadataResolver;

require __DIR__.'/Support/ResourceIndexSetup.php';

/**
 * @param  array<string, class-string<resource>>  $resources
 */
function configureLayoutNavigationResources(array $resources): void
{
    config()->set('seviaflow.resources', $resources);
    app()->forgetInstance(ResourceRegistry::class);
    app()->forgetInstance(NavigationMetadataResolver::class);
    app()->forgetInstance(NavigationMetadataCache::class);
}

/**
 * @return array<int, array<string,mixed>>
 */
function extractLayoutCommandItems(string $html): array
{
    preg_match('/<script type="application\/json" data-sf-command-items>(.*?)<\/script>/s', $html, $matches);

    if (! isset($matches[1])) {
        return [];
    }

    $decoded = json_decode(html_entity_decode($matches[1]), true);

    return is_array($decoded) ? $decoded : [];
}

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
    configureLayoutNavigationResources([
        'test-users' => TestUserResource::class,
    ]);

    $user = LayoutNavUser::query()->create(['name' => 'Layout Nav User', 'email' => 'layout-nav@example.com']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/test-users')
        ->assertOk()
        ->assertSee(route('seviaflow.dashboard'), false)
        ->assertSee('/admin/resources/test-users', false);
});

it('renders expanded sidebar brand with official asset paths', function (): void {
    configureLayoutNavigationResources([]);

    $user = LayoutNavUser::query()->create(['name' => 'Brand User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('sf-brand-on-dark', false)
        ->assertSee('/vendor/seviaflow/ui/brand/seviaflow-mark.png', false)
        ->assertSee('sf-brand-text-lockup', false)
        ->assertDontSee('seviaflow-logo-original-white.png', false)
        ->assertDontSee('sf-auth-logo', false);
});

it('renders a resource navigation icon in the sidebar', function (): void {
    configureLayoutNavigationResources([
        'icon-users' => IconLayoutResource::class,
    ]);

    $user = LayoutNavUser::query()->create(['name' => 'Icon Nav User', 'email' => 'icon-nav@example.com']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/icon-users')
        ->assertOk()
        ->assertSee('/admin/resources/icon-users', false)
        ->assertSee('Icon users')
        ->assertSee('data-seviaflow-icon="users"', false);
});

it('renders resource navigation normally without an icon', function (): void {
    configureLayoutNavigationResources([
        'no-icon-users' => NoIconLayoutResource::class,
    ]);

    $user = LayoutNavUser::query()->create(['name' => 'No Icon Nav User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/no-icon-users')
        ->assertOk()
        ->assertSee('/admin/resources/no-icon-users', false)
        ->assertSee('No icon users')
        ->assertSee('data-seviaflow-icon="circle-stack"', false)
        ->assertDontSee('data-seviaflow-icon="no-icon-users"', false);
});

it('ignores unknown resource navigation icons without crashing', function (): void {
    configureLayoutNavigationResources([
        'invalid-icon-users' => InvalidIconLayoutResource::class,
    ]);

    $user = LayoutNavUser::query()->create(['name' => 'Invalid Icon Nav User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/invalid-icon-users')
        ->assertOk()
        ->assertSee('/admin/resources/invalid-icon-users', false)
        ->assertSee('Invalid icon users')
        ->assertSee('data-seviaflow-icon="circle-stack"', false)
        ->assertDontSee('data-seviaflow-icon="definitely-not-an-icon"', false);
});

it('renders built in navigation icons', function (): void {
    configureLayoutNavigationResources([]);
    if (! Route::has('seviaflow.api.docs')) {
        Route::get('/admin/api-docs', static fn (): string => 'API documentation')->name('seviaflow.api.docs');
    }

    $user = LayoutNavUser::query()->create(['name' => 'Built In Icon User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('data-seviaflow-icon="squares-2x2"', false)
        ->assertSee('data-seviaflow-icon="shield-check"', false)
        ->assertSee('data-seviaflow-icon="users"', false)
        ->assertSee('data-seviaflow-icon="code-bracket"', false)
        ->assertSee('data-seviaflow-icon="lock-closed"', false)
        ->assertSee('data-seviaflow-icon="arrow-right-on-rectangle"', false);
});

it('renders product navigation area headings', function (): void {
    configureLayoutNavigationResources([
        'test-users' => TestUserResource::class,
    ]);

    $user = LayoutNavUser::query()->create(['name' => 'Group Heading User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/test-users')
        ->assertOk()
        ->assertSee('Workspace')
        ->assertSee('Data')
        ->assertSee('Settings');
});

it('renders custom resource navigation groups from metadata', function (): void {
    configureLayoutNavigationResources([
        'sales-layout-users' => SalesLayoutResource::class,
        'operations-layout-users' => OperationsLayoutResource::class,
    ]);

    $user = LayoutNavUser::query()->create(['name' => 'Grouped Resource User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/resources/sales-layout-users')
        ->assertOk()
        ->assertSee('Data')
        ->assertSee('Sales')
        ->assertSee('Operations')
        ->assertSee('/admin/resources/sales-layout-users', false)
        ->assertSee('/admin/resources/operations-layout-users', false);
});

it('keeps long resource navigation lists in the sidebar', function (): void {
    $resources = [];
    foreach (range(1, 25) as $index) {
        $resources[sprintf('layout-resource-%02d', $index)] = TestUserResource::class;
    }

    configureLayoutNavigationResources($resources);

    $user = LayoutNavUser::query()->create(['name' => 'Long Nav User']);
    pest_test_case()->actingAs($user, 'web');

    $response = pest_test_case()->get('/admin/resources/layout-resource-01');

    $response->assertOk()
        ->assertSee('25 resources');

    foreach (array_keys($resources) as $slug) {
        $response->assertSee('/admin/resources/'.$slug, false);
    }
});

it('marks active resource link in sidebar', function (): void {
    configureLayoutNavigationResources([
        'icon-users' => IconLayoutResource::class,
    ]);

    $user = LayoutNavUser::query()->create(['name' => 'Active Nav User']);
    pest_test_case()->actingAs($user, 'web');

    $response = pest_test_case()->get('/admin/resources/icon-users');

    $response->assertOk()
        ->assertSee('sf-sidebar-link-active', false)
        ->assertSee('data-seviaflow-icon="users"', false);

    expect((string) $response->getContent())->toMatch('/sf-sidebar-link[^"]*sf-sidebar-link-active[\s\S]*data-seviaflow-icon="users"[\s\S]*Icon users/');
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
    configureLayoutNavigationResources([
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
        ->assertDontSee('Hidden nav records')
        ->assertDontSee('data-seviaflow-icon="archive-box"', false);
});

it('builds command palette from authorized get navigation only', function (): void {
    configureLayoutNavigationResources([
        'icon-users' => IconLayoutResource::class,
        'hidden-nav' => HiddenNavResource::class,
    ]);

    Gate::policy(HiddenNavRecord::class, HiddenNavPolicy::class);

    $user = LayoutNavUser::query()->create(['name' => 'Command Palette User']);
    pest_test_case()->actingAs($user, 'web');

    $response = pest_test_case()->get('/admin/resources/icon-users');
    $items = extractLayoutCommandItems((string) $response->getContent());
    $labels = array_map(static fn (array $item): string => (string) ($item['label'] ?? ''), $items);

    $response->assertOk()
        ->assertSee('data-sf-command-items', false)
        ->assertSee('data-sf-command-open', false);

    expect($labels)->toContain('Icon users')
        ->and($labels)->toContain('Profile')
        ->and($labels)->not->toContain('Hidden nav records')
        ->and($labels)->not->toContain('Sign out');
});

it('allows direct access to authorized resources hidden from navigation', function (): void {
    configureLayoutNavigationResources([
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

    protected static ?string $navigationIcon = 'archive-box';

    public static function pluralLabel(): string
    {
        return 'Hidden nav records';
    }
}

final class IconLayoutResource extends Resource
{
    protected static string $model = TestUser::class;

    protected static ?string $navigationIcon = 'users';

    public static function pluralLabel(): string
    {
        return 'Icon users';
    }

    public static function table(Table $table): Table
    {
        return TestUserResource::table($table);
    }
}

final class NoIconLayoutResource extends Resource
{
    protected static string $model = TestUser::class;

    public static function pluralLabel(): string
    {
        return 'No icon users';
    }

    public static function table(Table $table): Table
    {
        return TestUserResource::table($table);
    }
}

final class InvalidIconLayoutResource extends Resource
{
    protected static string $model = TestUser::class;

    protected static ?string $navigationIcon = 'definitely-not-an-icon';

    public static function pluralLabel(): string
    {
        return 'Invalid icon users';
    }

    public static function table(Table $table): Table
    {
        return TestUserResource::table($table);
    }
}

final class SalesLayoutResource extends Resource
{
    protected static string $model = TestUser::class;

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationIcon = 'users';

    public static function pluralLabel(): string
    {
        return 'Sales accounts';
    }

    public static function table(Table $table): Table
    {
        return TestUserResource::table($table);
    }
}

final class OperationsLayoutResource extends Resource
{
    protected static string $model = TestUser::class;

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationIcon = 'briefcase';

    public static function pluralLabel(): string
    {
        return 'Operations accounts';
    }

    public static function table(Table $table): Table
    {
        return TestUserResource::table($table);
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
