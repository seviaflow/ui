<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Ui\Dashboard\Dashboard as DashboardDefinition;
use SeviaFlow\Ui\Navigation\AdminNavigationBuilder;
use SeviaFlow\Ui\Navigation\NavigationGroup;
use SeviaFlow\Ui\Navigation\NavigationItem;
use SeviaFlow\Ui\Navigation\NavigationMetadataCache;
use SeviaFlow\Ui\SeviaFlowUiServiceProvider;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Schema::create('nav_visible_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
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
});

/**
 * @param  array<int, NavigationGroup>  $groups
 * @return array<int, NavigationItem>
 */
function navItems(array $groups): array
{
    $items = [];
    foreach ($groups as $group) {
        foreach ($group->items as $item) {
            $items[] = $item;
        }
    }

    return $items;
}

it('includes dashboard navigation item', function (): void {
    $user = NavTestUser::query()->create(['name' => 'Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');

    $keys = array_map(static fn (NavigationItem $item): string => $item->key, navItems($groups));

    expect($keys)->toContain('dashboard:default');
});

it('shows only visible dashboards in navigation', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        NavVisibleDashboard::class,
        NavHiddenDashboard::class,
    ]);

    $user = NavTestUser::query()->create(['name' => 'Dashboard Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');

    $keys = array_map(static fn (NavigationItem $item): string => $item->key, navItems($groups));

    expect($keys)->toContain('dashboard:visible-dashboard')
        ->and($keys)->not->toContain('dashboard:hidden-dashboard');
});

it('returns system navigation groups', function (): void {
    $user = NavTestUser::query()->create(['name' => 'Grouped Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');

    $groupKeys = array_map(static fn (NavigationGroup $group): string => $group->key, $groups);
    $groupLabels = array_map(static fn (NavigationGroup $group): string => $group->label, $groups);

    expect($groupKeys)->toContain('main', 'resources', 'settings', 'developer', 'account')
        ->and($groupLabels)->toContain('Main', 'Resources', 'Settings', 'Developer', 'Account');
});

it('shows registered authorized resources in navigation', function (): void {
    config()->set('seviaflow.resources', [
        'visible-resource' => NavVisibleResource::class,
    ]);
    Gate::policy(NavVisibleRecord::class, NavVisiblePolicy::class);

    $user = NavTestUser::query()->create(['name' => 'Visible Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.resources.index', 'visible-resource');

    $labels = array_map(static fn (NavigationItem $item): string => $item->label, navItems($groups));
    expect($labels)->toContain('Visible records');
});

it('uses navigation metadata overrides for label group icon and sort', function (): void {
    config()->set('seviaflow.resources', [
        'visible-resource' => NavVisibleResource::class,
        'metadata-resource' => NavMetadataResource::class,
    ]);
    Gate::policy(NavVisibleRecord::class, NavVisiblePolicy::class);
    Gate::policy(NavMetadataRecord::class, NavVisiblePolicy::class);

    $user = NavTestUser::query()->create(['name' => 'Metadata Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');
    $items = navItems($groups);

    $metadataItem = collect($items)->first(fn (NavigationItem $item): bool => $item->key === 'resource:metadata-resource');
    $visibleItem = collect($items)->first(fn (NavigationItem $item): bool => $item->key === 'resource:visible-resource');

    expect($metadataItem)->not->toBeNull()
        ->and($visibleItem)->not->toBeNull()
        ->and($metadataItem?->label)->toBe('Customers')
        ->and($metadataItem?->group)->toBe('Sales')
        ->and($metadataItem?->icon)->toBe('users')
        ->and($metadataItem?->order)->toBe(5);

    expect(array_search('resource:metadata-resource', array_map(static fn (NavigationItem $item): string => $item->key, $items), true))
        ->toBeLessThan(array_search('resource:visible-resource', array_map(static fn (NavigationItem $item): string => $item->key, $items), true));
});

it('hides resources when should register navigation is false', function (): void {
    config()->set('seviaflow.resources', [
        'visible-resource' => NavVisibleResource::class,
        'hidden-navigation-resource' => NavHiddenNavigationResource::class,
    ]);
    Gate::policy(NavVisibleRecord::class, NavVisiblePolicy::class);

    $user = NavTestUser::query()->create(['name' => 'Hidden Metadata Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');

    $keys = array_map(static fn (NavigationItem $item): string => $item->key, navItems($groups));
    expect($keys)->toContain('resource:visible-resource')
        ->and($keys)->not->toContain('resource:hidden-navigation-resource');
});

it('falls back to humanized slug label when no labels are defined', function (): void {
    config()->set('seviaflow.resources', [
        'plain-resource-slug' => NavPlainResource::class,
    ]);
    Gate::policy(NavPlainRecord::class, NavVisiblePolicy::class);

    $user = NavTestUser::query()->create(['name' => 'Fallback Label Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');

    $labels = array_map(static fn (NavigationItem $item): string => $item->label, navItems($groups));
    expect($labels)->toContain('Plain Resource Slug');
});

it('hides unauthorized resources from navigation', function (): void {
    config()->set('seviaflow.resources', [
        'visible-resource' => NavVisibleResource::class,
        'hidden-resource' => NavHiddenResource::class,
    ]);
    Gate::policy(NavVisibleRecord::class, NavVisiblePolicy::class);
    Gate::policy(NavHiddenRecord::class, NavHiddenPolicy::class);

    $user = NavTestUser::query()->create(['name' => 'Unauthorized Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');

    $labels = array_map(static fn (NavigationItem $item): string => $item->label, navItems($groups));
    expect($labels)->toContain('Visible records')
        ->and($labels)->not->toContain('Hidden records');
});

it('builds navigation links with custom route prefix', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice-admin.');
    config()->set('seviaflow.resources', [
        'visible-resource' => NavVisibleResource::class,
    ]);

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();
    app(NavigationMetadataCache::class)->clear();

    Gate::policy(NavVisibleRecord::class, NavVisiblePolicy::class);

    $user = NavTestUser::query()->create(['name' => 'Prefixed Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'backoffice-admin.resources.index', 'visible-resource');

    $urls = array_map(static fn (NavigationItem $item): string => $item->url, navItems($groups));
    expect($urls)->toContain(route('backoffice-admin.resources.index', ['resource' => 'visible-resource']))
        ->and($urls)->not->toContain(url('/admin/resources/visible-resource'));
});

it('does not include links for routes that do not exist', function (): void {
    config()->set('seviaflow-ui.route_name_prefix', 'missing-admin.');
    $user = NavTestUser::query()->create(['name' => 'Missing Routes Nav User']);

    $groups = app(AdminNavigationBuilder::class)->build($user, 'missing-admin.dashboard');
    $keys = array_map(static fn (NavigationItem $item): string => $item->key, navItems($groups));

    expect($keys)->not->toContain('dashboard:default')
        ->and($keys)->not->toContain('account.profile')
        ->and($keys)->not->toContain('account.security')
        ->and($keys)->not->toContain('account.logout');
});

it('marks current resource item as active', function (): void {
    config()->set('seviaflow.resources', [
        'visible-resource' => NavVisibleResource::class,
    ]);
    Gate::policy(NavVisibleRecord::class, NavVisiblePolicy::class);

    $user = NavTestUser::query()->create(['name' => 'Active Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.resources.index', 'visible-resource');

    $active = array_values(array_filter(navItems($groups), static fn (NavigationItem $item): bool => $item->active));
    $activeKeys = array_map(static fn (NavigationItem $item): string => $item->key, $active);

    expect($activeKeys)->toContain('resource:visible-resource');
});

it('includes profile and logout account links when routes exist', function (): void {
    $user = NavTestUser::query()->create(['name' => 'Account Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');

    $keys = array_map(static fn (NavigationItem $item): string => $item->key, navItems($groups));
    expect($keys)->toContain('account.profile')
        ->and($keys)->toContain('account.security')
        ->and($keys)->toContain('account.logout');
});

it('hides settings links when permissions deny access', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $user = NavTestUser::query()->create(['name' => 'Denied Settings Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');

    $keys = array_map(static fn (NavigationItem $item): string => $item->key, navItems($groups));
    expect($keys)->not->toContain('settings.roles')
        ->and($keys)->not->toContain('settings.admin-users');
});

it('supports no resources without errors', function (): void {
    config()->set('seviaflow.resources', []);
    $user = NavTestUser::query()->create(['name' => 'No Resources Nav User']);

    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');
    $resourceItems = array_filter(
        navItems($groups),
        static fn (NavigationItem $item): bool => str_starts_with($item->key, 'resource:')
    );

    expect($resourceItems)->toBeArray()->toBeEmpty();
});

it('shows api docs navigation link when enabled and authorized', function (): void {
    config()->set('seviaflow.api_docs.enabled', true);
    config()->set('seviaflow.api_docs.show_in_navigation', true);

    $user = NavTestUser::query()->create(['name' => 'API Docs Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');

    $docsItem = collect(navItems($groups))->first(
        fn (NavigationItem $item): bool => $item->key === 'developer.api-docs'
    );

    expect($docsItem)->not->toBeNull()
        ->and($docsItem?->label)->toBe('API documentation')
        ->and($docsItem?->url)->toContain('/admin/api-docs');
});

it('hides api docs navigation link when docs navigation is disabled', function (): void {
    config()->set('seviaflow.api_docs.show_in_navigation', false);

    $user = NavTestUser::query()->create(['name' => 'No Docs Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');

    $keys = array_map(static fn (NavigationItem $item): string => $item->key, navItems($groups));
    expect($keys)->not->toContain('developer.api-docs');
});

it('hides api docs navigation link for unauthorized user when permissions are enabled', function (): void {
    config()->set('seviaflow.permissions.enabled', true);
    config()->set('seviaflow.api_docs.show_in_navigation', true);

    $user = NavTestUser::query()->create(['name' => 'Unauthorized Docs User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');

    $keys = array_map(static fn (NavigationItem $item): string => $item->key, navItems($groups));
    expect($keys)->not->toContain('developer.api-docs');
});

it('builds api docs navigation link with custom route prefix', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice-admin.');

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    $user = NavTestUser::query()->create(['name' => 'Prefixed Docs Nav User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'backoffice-admin.dashboard');

    $docsItem = collect(navItems($groups))->first(
        fn (NavigationItem $item): bool => $item->key === 'developer.api-docs'
    );

    expect($docsItem)->not->toBeNull()
        ->and($docsItem?->url)->toContain('/backoffice/api-docs');
});

it('does not render api docs link when docs route is disabled', function (): void {
    config()->set('seviaflow.api_docs.enabled', false);

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, true);

    $user = NavTestUser::query()->create(['name' => 'No Docs Route User']);
    $groups = app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard');

    $keys = array_map(static fn (NavigationItem $item): string => $item->key, navItems($groups));
    expect($keys)->not->toContain('developer.api-docs');
});

it('caches base navigation metadata when enabled', function (): void {
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow.performance.cache.navigation', true);
    config()->set('seviaflow.performance.cache.store', 'array');
    flushArrayCache();

    $cache = app(NavigationMetadataCache::class);

    $first = $cache->resolve();
    $second = $cache->resolve();

    expect(cache()->store('array')->has('seviaflow.navigation.base'))->toBeTrue()
        ->and($first)->toBe($second);
});

it('builds navigation metadata directly when cache is disabled', function (): void {
    config()->set('seviaflow.performance.cache.enabled', false);
    config()->set('seviaflow.performance.cache.navigation', true);

    $cache = app(NavigationMetadataCache::class);

    $cache->resolve();
    $cache->resolve();

    expect(cache()->store('array')->has('seviaflow.navigation.base'))->toBeFalse();
});

it('authorization still filters cached navigation per user', function (): void {
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow.performance.cache.navigation', true);
    config()->set('seviaflow.performance.cache.store', 'array');
    flushArrayCache();

    config()->set('seviaflow.resources', [
        'conditional-resource' => NavConditionalResource::class,
    ]);
    Gate::policy(NavVisibleRecord::class, NavConditionalPolicy::class);

    $allowed = NavTestUser::query()->create(['name' => 'Allowed']);
    $denied = NavTestUser::query()->create(['name' => 'Denied']);

    $allowedKeys = array_map(
        static fn (NavigationItem $item): string => $item->key,
        navItems(app(AdminNavigationBuilder::class)->build($allowed, 'seviaflow.dashboard'))
    );
    $deniedKeys = array_map(
        static fn (NavigationItem $item): string => $item->key,
        navItems(app(AdminNavigationBuilder::class)->build($denied, 'seviaflow.dashboard'))
    );

    expect($allowedKeys)->toContain('resource:conditional-resource')
        ->and($deniedKeys)->not->toContain('resource:conditional-resource');
});

it('active state is computed per request and not cached globally', function (): void {
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow.performance.cache.navigation', true);
    config()->set('seviaflow.performance.cache.store', 'array');
    flushArrayCache();

    config()->set('seviaflow.resources', [
        'visible-resource' => NavVisibleResource::class,
    ]);
    Gate::policy(NavVisibleRecord::class, NavVisiblePolicy::class);

    $user = NavTestUser::query()->create(['name' => 'Active Cached User']);

    $dashboardActiveKeys = array_map(
        static fn (NavigationItem $item): string => $item->key,
        array_values(array_filter(
            navItems(app(AdminNavigationBuilder::class)->build($user, 'seviaflow.dashboard')),
            static fn (NavigationItem $item): bool => $item->active
        ))
    );

    $resourceActiveKeys = array_map(
        static fn (NavigationItem $item): string => $item->key,
        array_values(array_filter(
            navItems(app(AdminNavigationBuilder::class)->build($user, 'seviaflow.resources.index', 'visible-resource')),
            static fn (NavigationItem $item): bool => $item->active
        ))
    );

    expect($dashboardActiveKeys)->toContain('dashboard:default')
        ->and($dashboardActiveKeys)->not->toContain('resource:visible-resource')
        ->and($resourceActiveKeys)->toContain('resource:visible-resource')
        ->and($resourceActiveKeys)->not->toContain('dashboard:default');
});

it('navigation cache clear invalidates base metadata cache', function (): void {
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow.performance.cache.navigation', true);
    config()->set('seviaflow.performance.cache.store', 'array');
    flushArrayCache();

    $cache = app(NavigationMetadataCache::class);

    $cache->resolve();
    expect(cache()->store('array')->has('seviaflow.navigation.base'))->toBeTrue();

    $cache->clear();
    expect(cache()->store('array')->has('seviaflow.navigation.base'))->toBeFalse();

    $cache->resolve();

    expect(cache()->store('array')->has('seviaflow.navigation.base'))->toBeTrue();
});

/**
 * @property string $name
 */
final class NavTestUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}

final class NavVisibleRecord extends Model
{
    protected $table = 'nav_visible_records';

    protected $guarded = [];
}

final class NavHiddenRecord extends Model
{
    protected $table = 'nav_hidden_records';

    protected $guarded = [];
}

final class NavMetadataRecord extends Model
{
    protected $table = 'nav_visible_records';

    protected $guarded = [];
}

final class NavPlainRecord extends Model
{
    protected $table = 'nav_visible_records';

    protected $guarded = [];
}

final class NavVisibleResource extends Resource
{
    protected static string $model = NavVisibleRecord::class;

    public static function pluralLabel(): string
    {
        return 'Visible records';
    }
}

final class NavHiddenResource extends Resource
{
    protected static string $model = NavHiddenRecord::class;

    public static function pluralLabel(): string
    {
        return 'Hidden records';
    }
}

final class NavMetadataResource extends Resource
{
    protected static string $model = NavMetadataRecord::class;

    public static function navigationLabel(): ?string
    {
        return 'Customers';
    }

    public static function navigationGroup(): ?string
    {
        return 'Sales';
    }

    public static function navigationIcon(): ?string
    {
        return 'users';
    }

    public static function navigationSort(): ?int
    {
        return 5;
    }
}

final class NavHiddenNavigationResource extends Resource
{
    protected static string $model = NavVisibleRecord::class;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}

final class NavPlainResource extends Resource
{
    protected static string $model = NavPlainRecord::class;
}

final class NavConditionalResource extends Resource
{
    protected static string $model = NavVisibleRecord::class;

    public static function pluralLabel(): string
    {
        return 'Conditional records';
    }
}

final class NavVisiblePolicy
{
    public function viewAny(NavTestUser $user): bool
    {
        return true;
    }
}

final class NavHiddenPolicy
{
    public function viewAny(NavTestUser $user): bool
    {
        return false;
    }
}

final class NavConditionalPolicy
{
    public function viewAny(NavTestUser $user): bool
    {
        return $user->name === 'Allowed';
    }
}

final class NavVisibleDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'visible-dashboard';
    }

    public static function label(): string
    {
        return 'Visible Dashboard';
    }
}

final class NavHiddenDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'hidden-dashboard';
    }

    public static function label(): string
    {
        return 'Hidden Dashboard';
    }

    public static function visibleFor(mixed $user): bool
    {
        return false;
    }
}
