<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Core\Auth\Roles\Permission;
use SeviaFlow\Core\Auth\Roles\Role;
use SeviaFlow\Core\Auth\Roles\RoleAssignment;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Workflow;
use SeviaFlow\Ui\Dashboard\Dashboard as DashboardDefinition;
use SeviaFlow\Ui\Dashboard\DashboardRegistry;
use SeviaFlow\Ui\Dashboard\Widgets\ChartWidget;
use SeviaFlow\Ui\Dashboard\Widgets\RecentActivityWidget;
use SeviaFlow\Ui\Dashboard\Widgets\StatsWidget;
use SeviaFlow\Ui\Dashboard\Widgets\TableWidget;
use SeviaFlow\Ui\Dashboard\Widgets\Widget;
use SeviaFlow\Ui\Dashboard\Widgets\WorkflowQueueWidget;
use SeviaFlow\Ui\Livewire\Dashboard;
use SeviaFlow\Ui\SeviaFlowUiServiceProvider;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Schema::create('dashboard_visible_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Schema::create('dashboard_hidden_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Schema::create('dashboard_workflow_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('dashboard_workflow_hidden_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });
    Schema::create('dashboard_creatable_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
    });
    Schema::create('dashboard_non_creatable_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
    });

    Schema::create('seviaflow_workflow_activities', function (Blueprint $table): void {
        $table->id();
        $table->string('resource');
        $table->string('record_type');
        $table->string('record_id');
        $table->string('transition');
        $table->string('from_state')->nullable();
        $table->string('to_state');
        $table->string('user_type')->nullable();
        $table->string('user_id')->nullable();
        $table->text('comment')->nullable();
        $table->timestamps();
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

    if (! Schema::hasTable('seviaflow_dashboard_layouts')) {
        Schema::create('seviaflow_dashboard_layouts', function (Blueprint $table): void {
            $table->id();
            $table->string('dashboard');
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->json('layout');
            $table->timestamps();

            $table->unique(['dashboard', 'user_type', 'user_id'], 'seviaflow_dashboard_layouts_scope_unique');
        });
    }
});

it('registers dashboard route name with default prefix', function (): void {
    expect(Route::has('seviaflow.dashboard'))->toBeTrue();
});

it('registers dashboard show route name with default prefix', function (): void {
    expect(Route::has('seviaflow.dashboards.show'))->toBeTrue();
});

it('renders dashboard livewire component without runtime errors when no dashboards are visible', function (): void {
    config()->set('seviaflow-ui.dashboards', []);

    $user = DashboardTestUser::query()->create([
        'name' => 'Runtime Smoke User',
        'email' => 'runtime-smoke@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(Dashboard::class)
        ->assertSee('sf-dashboard-grid', false);
});

it('shows configured default dashboard at admin root', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        OperationsFeatureDashboard::class,
        SalesFeatureDashboard::class,
    ]);
    config()->set('seviaflow-ui.dashboard.default', 'sales');

    $user = DashboardTestUser::query()->create([
        'name' => 'Default Dashboard User',
        'email' => 'default-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(Dashboard::class)
        ->assertSee('Sales Dashboard')
        ->assertSee('Sales operations overview');
});

it('shows selected dashboard by slug route', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        OperationsFeatureDashboard::class,
        SalesFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Slug Dashboard User',
        'email' => 'slug-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/operations')
        ->assertOk()
        ->assertSeeLivewire(Dashboard::class)
        ->assertSee('Operations Dashboard')
        ->assertSee('Operations queue and daily workload');
});

it('renders visible dashboard widgets from dashboard definition', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        WidgetFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Widget Dashboard User',
        'email' => 'widget-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/widgets')
        ->assertOk()
        ->assertSee('Pending orders')
        ->assertSee('Recent orders')
        ->assertSee('data-widget="pending_orders"', false)
        ->assertSee('data-widget="recent_orders"', false)
        ->assertSee('sf-dashboard-widget-grid', false);
});

it('renders stats widget static and closure values', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        StatsFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Stats Dashboard User',
        'email' => 'stats-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/stats')
        ->assertOk()
        ->assertSee('Pending orders')
        ->assertSee('12')
        ->assertSee('Open tickets')
        ->assertSee('8')
        ->assertSee('sf-dashboard-stat-amber', false);
});

it('renders chart widget container with safe json data attributes', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        ChartFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Chart Dashboard User',
        'email' => 'chart-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/charts')
        ->assertOk()
        ->assertSee('Orders by status')
        ->assertSee('data-sf-chart="1"', false)
        ->assertSee('data-chart-type="doughnut"', false)
        ->assertSee('data-chart-data=', false);
});

it('renders table widget with enforced row limit and row links', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-visible' => DashboardVisibleResource::class,
    ]);
    config()->set('seviaflow-ui.dashboards', [
        TableFeatureDashboard::class,
    ]);

    DashboardVisibleRecord::query()->create(['name' => 'Order #001']);
    DashboardVisibleRecord::query()->create(['name' => 'Order #002']);
    DashboardVisibleRecord::query()->create(['name' => 'Order #003']);

    $user = DashboardTestUser::query()->create([
        'name' => 'Table Dashboard User',
        'email' => 'table-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/table')
        ->assertOk()
        ->assertSee('Recent orders')
        ->assertSee('Order #003')
        ->assertSee('Order #002')
        ->assertDontSee('Order #001')
        ->assertSee('/admin/resources/dashboard-visible/', false);
});

it('renders table widget empty state when no rows', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-visible' => DashboardVisibleResource::class,
    ]);
    config()->set('seviaflow-ui.dashboards', [
        TableEmptyFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Table Empty Dashboard User',
        'email' => 'table-empty-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/table-empty')
        ->assertOk()
        ->assertSee('No records found.');
});

it('hides unauthorized table widget resource data', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-hidden' => DashboardHiddenResource::class,
    ]);
    config()->set('seviaflow-ui.dashboards', [
        TableDeniedFeatureDashboard::class,
    ]);
    Gate::policy(DashboardHiddenRecord::class, DashboardHiddenPolicy::class);

    DashboardHiddenRecord::query()->create(['name' => 'Should Not Render']);

    $user = DashboardTestUser::query()->create([
        'name' => 'Table Denied Dashboard User',
        'email' => 'table-denied-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/table-denied')
        ->assertOk()
        ->assertDontSee('Hidden table widget')
        ->assertDontSee('Should Not Render');
});

it('renders workflow queue widget records in configured states with badges', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
    ]);
    config()->set('seviaflow-ui.dashboards', [
        WorkflowQueueFeatureDashboard::class,
    ]);
    Gate::policy(DashboardWorkflowRecord::class, DashboardWorkflowVisiblePolicy::class);

    DashboardWorkflowRecord::query()->create(['name' => 'Order #1001', 'status' => 'pending_review']);
    DashboardWorkflowRecord::query()->create(['name' => 'Order #1002', 'status' => 'pending_review']);
    DashboardWorkflowRecord::query()->create(['name' => 'Order #1003', 'status' => 'draft']);

    $user = DashboardTestUser::query()->create([
        'name' => 'Workflow Queue User',
        'email' => 'workflow-queue@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/workflow-queue')
        ->assertOk()
        ->assertSee('Orders needing attention')
        ->assertSee('Order #1001')
        ->assertSee('Order #1002')
        ->assertDontSee('Order #1003')
        ->assertSee('Pending review')
        ->assertSee('sf-badge', false)
        ->assertSee('/admin/resources/dashboard-workflow/', false);
});

it('renders workflow queue widget empty state when no matching records', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
    ]);
    config()->set('seviaflow-ui.dashboards', [
        WorkflowQueueFeatureDashboard::class,
    ]);
    Gate::policy(DashboardWorkflowRecord::class, DashboardWorkflowVisiblePolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'Workflow Queue Empty User',
        'email' => 'workflow-queue-empty@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/workflow-queue')
        ->assertOk()
        ->assertSee('No records currently need workflow attention.');
});

it('hides unauthorized workflow queue widget resource data', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow-hidden' => DashboardWorkflowHiddenResource::class,
    ]);
    config()->set('seviaflow-ui.dashboards', [
        WorkflowQueueDeniedFeatureDashboard::class,
    ]);
    Gate::policy(DashboardWorkflowHiddenRecord::class, DashboardWorkflowHiddenPolicy::class);

    DashboardWorkflowHiddenRecord::query()->create(['name' => 'Should Not Render', 'status' => 'draft']);

    $user = DashboardTestUser::query()->create([
        'name' => 'Workflow Queue Denied User',
        'email' => 'workflow-queue-denied@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/workflow-queue-denied')
        ->assertOk()
        ->assertDontSee('Hidden workflow queue')
        ->assertDontSee('Should Not Render');
});

it('fails clearly when workflow queue widget defines invalid state', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
    ]);
    config()->set('seviaflow-ui.dashboards', [
        WorkflowQueueInvalidStateFeatureDashboard::class,
    ]);
    Gate::policy(DashboardWorkflowRecord::class, DashboardWorkflowVisiblePolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'Workflow Queue Invalid State User',
        'email' => 'workflow-queue-invalid@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');
    pest_test_case()->withoutExceptionHandling();

    expect(fn () => pest_test_case()->get('/admin/dashboards/workflow-queue-invalid'))
        ->toThrow(InvalidArgumentException::class, 'not defined in resource workflow');
});

it('fails clearly for workflow queue widget on non-workflow resource', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-visible' => DashboardVisibleResource::class,
    ]);
    config()->set('seviaflow-ui.dashboards', [
        WorkflowQueueNoWorkflowFeatureDashboard::class,
    ]);
    Gate::policy(DashboardVisibleRecord::class, DashboardVisiblePolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'Workflow Queue No Workflow User',
        'email' => 'workflow-queue-no-workflow@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');
    pest_test_case()->withoutExceptionHandling();

    expect(fn () => pest_test_case()->get('/admin/dashboards/workflow-queue-no-workflow'))
        ->toThrow(InvalidArgumentException::class, 'does not define workflow states');
});

it('renders recent activity widget items with user timestamp and comment', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
    ]);
    config()->set('seviaflow-ui.dashboards', [
        RecentActivityFeatureDashboard::class,
    ]);
    Gate::policy(DashboardWorkflowRecord::class, DashboardWorkflowVisiblePolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'Recent Activity User',
        'email' => 'recent-activity-widget@example.com',
    ]);

    DB::table('seviaflow_workflow_activities')->insert([
        'resource' => DashboardWorkflowResource::class,
        'record_type' => DashboardWorkflowRecord::class,
        'record_id' => '501',
        'transition' => 'approve',
        'from_state' => 'draft',
        'to_state' => 'published',
        'user_type' => DashboardTestUser::class,
        'user_id' => (string) $user->getKey(),
        'comment' => 'Approved manually',
        'created_at' => Carbon::parse('2026-01-05 11:05:00'),
        'updated_at' => Carbon::parse('2026-01-05 11:05:00'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/recent-activity-widget')
        ->assertOk()
        ->assertSee('Recent activity')
        ->assertSee('Workflow records')
        ->assertSee('DashboardWorkflowRecord #501')
        ->assertSee('Approved manually')
        ->assertSee('Recent Activity User')
        ->assertSee('2026-01-05 11:05:00');
});

it('enforces recent activity widget limit', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
    ]);
    config()->set('seviaflow-ui.dashboard.recent_activity_widgets.max_limit', 2);
    config()->set('seviaflow-ui.dashboards', [
        RecentActivityFeatureDashboard::class,
    ]);
    Gate::policy(DashboardWorkflowRecord::class, DashboardWorkflowVisiblePolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'Recent Activity Limit User',
        'email' => 'recent-activity-limit@example.com',
    ]);

    for ($i = 1; $i <= 3; $i++) {
        DB::table('seviaflow_workflow_activities')->insert([
            'resource' => DashboardWorkflowResource::class,
            'record_type' => DashboardWorkflowRecord::class,
            'record_id' => (string) $i,
            'transition' => 'approve',
            'from_state' => 'draft',
            'to_state' => 'published',
            'user_type' => DashboardTestUser::class,
            'user_id' => (string) $user->getKey(),
            'comment' => null,
            'created_at' => Carbon::parse('2026-01-05 11:0'.$i.':00'),
            'updated_at' => Carbon::parse('2026-01-05 11:0'.$i.':00'),
        ]);
    }

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/recent-activity-widget')
        ->assertOk()
        ->assertSee('DashboardWorkflowRecord #3')
        ->assertSee('DashboardWorkflowRecord #2')
        ->assertDontSee('DashboardWorkflowRecord #1');
});

it('hides unauthorized activities in recent activity widget', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
        'dashboard-workflow-hidden' => DashboardWorkflowHiddenResource::class,
    ]);
    config()->set('seviaflow-ui.dashboards', [
        RecentActivityFeatureDashboard::class,
    ]);
    Gate::policy(DashboardWorkflowRecord::class, DashboardWorkflowVisiblePolicy::class);
    Gate::policy(DashboardWorkflowHiddenRecord::class, DashboardWorkflowHiddenPolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'Recent Activity Authorized User',
        'email' => 'recent-activity-authorized@example.com',
    ]);

    DB::table('seviaflow_workflow_activities')->insert([
        [
            'resource' => DashboardWorkflowResource::class,
            'record_type' => DashboardWorkflowRecord::class,
            'record_id' => '10',
            'transition' => 'approve',
            'from_state' => 'draft',
            'to_state' => 'published',
            'user_type' => DashboardTestUser::class,
            'user_id' => (string) $user->getKey(),
            'comment' => null,
            'created_at' => Carbon::parse('2026-01-05 11:10:00'),
            'updated_at' => Carbon::parse('2026-01-05 11:10:00'),
        ],
        [
            'resource' => DashboardWorkflowHiddenResource::class,
            'record_type' => DashboardWorkflowHiddenRecord::class,
            'record_id' => '20',
            'transition' => 'approve',
            'from_state' => 'draft',
            'to_state' => 'published',
            'user_type' => DashboardTestUser::class,
            'user_id' => (string) $user->getKey(),
            'comment' => null,
            'created_at' => Carbon::parse('2026-01-05 11:20:00'),
            'updated_at' => Carbon::parse('2026-01-05 11:20:00'),
        ],
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/recent-activity-widget')
        ->assertOk()
        ->assertSee('DashboardWorkflowRecord #10')
        ->assertDontSee('DashboardWorkflowHiddenRecord #20');
});

it('renders recent activity widget empty state', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
    ]);
    config()->set('seviaflow-ui.dashboards', [
        RecentActivityFeatureDashboard::class,
    ]);
    Gate::policy(DashboardWorkflowRecord::class, DashboardWorkflowVisiblePolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'Recent Activity Empty User',
        'email' => 'recent-activity-empty-widget@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/recent-activity-widget')
        ->assertOk()
        ->assertSee('No recent activity found.');
});

it('renders dashboard layout edit mode', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        LayoutEditableFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Layout Edit User',
        'email' => 'layout-edit@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(Dashboard::class, ['dashboard' => 'layout-editable'])
        ->call('startLayoutEditing')
        ->assertSet('layoutEditing', true)
        ->assertSee('Save layout')
        ->assertSee('Reset to default')
        ->assertSee('Drag to reorder');
});

it('saves dashboard layout for authenticated user', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        LayoutEditableFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Layout Save User',
        'email' => 'layout-save@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(Dashboard::class, ['dashboard' => 'layout-editable'])
        ->set('layoutPayload', json_encode([
            ['widget' => 'beta_widget', 'x' => 0, 'y' => 0, 'w' => 2, 'h' => 1, 'visible' => true],
            ['widget' => 'alpha_widget', 'x' => 0, 'y' => 1, 'w' => 1, 'h' => 1, 'visible' => true],
        ]))
        ->call('saveLayout')
        ->assertSet('layoutEditing', false);

    $stored = DB::table('seviaflow_dashboard_layouts')
        ->where('dashboard', 'layout-editable')
        ->where('user_type', DashboardTestUser::class)
        ->where('user_id', (string) $user->getKey())
        ->first();

    expect($stored)->not->toBeNull();
});

it('rejects unknown widget keys from layout payload safely', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        LayoutEditableFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Layout Invalid Widget User',
        'email' => 'layout-invalid-widget@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(Dashboard::class, ['dashboard' => 'layout-editable'])
        ->set('layoutPayload', json_encode([
            ['widget' => 'unknown_widget', 'x' => 0, 'y' => 0, 'w' => 3, 'h' => 1, 'visible' => true],
            ['widget' => 'alpha_widget', 'x' => 0, 'y' => 1, 'w' => 1, 'h' => 1, 'visible' => true],
        ]))
        ->call('saveLayout');

    $stored = DB::table('seviaflow_dashboard_layouts')
        ->where('dashboard', 'layout-editable')
        ->where('user_type', DashboardTestUser::class)
        ->where('user_id', (string) $user->getKey())
        ->first();

    $layout = json_decode((string) ($stored?->layout ?? '[]'), true);
    $widgets = is_array($layout) ? array_column($layout, 'widget') : [];

    expect($widgets)->not->toContain('unknown_widget')
        ->and($widgets)->toContain('alpha_widget');
});

it('resets dashboard layout to defaults', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        LayoutEditableFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Layout Reset User',
        'email' => 'layout-reset@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(Dashboard::class, ['dashboard' => 'layout-editable'])
        ->set('layoutPayload', json_encode([
            ['widget' => 'beta_widget', 'x' => 0, 'y' => 0, 'w' => 2, 'h' => 1, 'visible' => true],
            ['widget' => 'alpha_widget', 'x' => 0, 'y' => 1, 'w' => 1, 'h' => 1, 'visible' => true],
        ]))
        ->call('saveLayout')
        ->call('resetLayout');

    $stored = DB::table('seviaflow_dashboard_layouts')
        ->where('dashboard', 'layout-editable')
        ->where('user_type', DashboardTestUser::class)
        ->where('user_id', (string) $user->getKey())
        ->first();

    expect($stored)->toBeNull();
});

it('user cannot update another user layout', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        LayoutEditableFeatureDashboard::class,
    ]);

    $owner = DashboardTestUser::query()->create([
        'name' => 'Layout Owner User',
        'email' => 'layout-owner@example.com',
    ]);
    $other = DashboardTestUser::query()->create([
        'name' => 'Layout Other User',
        'email' => 'layout-other@example.com',
    ]);

    pest_test_case()->actingAs($owner, 'web');
    Livewire::test(Dashboard::class, ['dashboard' => 'layout-editable'])
        ->set('layoutPayload', json_encode([
            ['widget' => 'beta_widget', 'x' => 0, 'y' => 0, 'w' => 2, 'h' => 1, 'visible' => true],
            ['widget' => 'alpha_widget', 'x' => 0, 'y' => 1, 'w' => 1, 'h' => 1, 'visible' => true],
        ]))
        ->call('saveLayout');

    pest_test_case()->actingAs($other, 'web');
    Livewire::test(Dashboard::class, ['dashboard' => 'layout-editable'])
        ->set('layoutPayload', json_encode([
            ['widget' => 'alpha_widget', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
            ['widget' => 'beta_widget', 'x' => 0, 'y' => 1, 'w' => 1, 'h' => 1, 'visible' => true],
        ]))
        ->call('saveLayout');

    $ownerStored = DB::table('seviaflow_dashboard_layouts')
        ->where('dashboard', 'layout-editable')
        ->where('user_type', DashboardTestUser::class)
        ->where('user_id', (string) $owner->getKey())
        ->first();

    $layout = json_decode((string) ($ownerStored?->layout ?? '[]'), true);

    expect($layout[0]['widget'] ?? null)->toBe('beta_widget');
});

it('dashboard renders saved layout order', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        LayoutEditableFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Layout Render User',
        'email' => 'layout-render@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(Dashboard::class, ['dashboard' => 'layout-editable'])
        ->set('layoutPayload', json_encode([
            ['widget' => 'beta_widget', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
            ['widget' => 'alpha_widget', 'x' => 0, 'y' => 1, 'w' => 1, 'h' => 1, 'visible' => true],
        ]))
        ->call('saveLayout');

    pest_test_case()->get('/admin/dashboards/layout-editable')
        ->assertOk()
        ->assertSeeInOrder(['Beta widget', 'Alpha widget']);
});

it('does not render invisible dashboard widgets', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        WidgetHiddenFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Hidden Widget Dashboard User',
        'email' => 'hidden-widget-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/widgets-hidden')
        ->assertOk()
        ->assertSee('Visible widget')
        ->assertDontSee('Hidden widget');
});

it('fails clearly when dashboard defines duplicate widget names', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        WidgetDuplicateFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Duplicate Widget Dashboard User',
        'email' => 'duplicate-widget-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');
    pest_test_case()->withoutExceptionHandling();

    expect(fn () => pest_test_case()->get('/admin/dashboards/widgets-duplicate'))
        ->toThrow(InvalidArgumentException::class, 'duplicate widget name');
});

it('does not allow access to invisible dashboard', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        HiddenFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Invisible Dashboard User',
        'email' => 'invisible-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/hidden')
        ->assertOk()
        ->assertSee('No dashboards are currently visible for your account.');
});

it('allows role based dashboard visibility for assigned users', function (): void {
    config()->set('seviaflow.permissions.enabled', true);
    config()->set('seviaflow-ui.dashboards', [
        RoleOnlyFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Role Dashboard User',
        'email' => 'role-dashboard@example.com',
    ]);

    $role = Role::query()->create([
        'name' => 'Manager',
        'slug' => 'manager',
    ]);

    RoleAssignment::query()->create([
        'role_id' => $role->getKey(),
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/manager')
        ->assertOk()
        ->assertSee('Manager Dashboard');
});

it('denies role based dashboard visibility for unassigned users', function (): void {
    config()->set('seviaflow.permissions.enabled', true);
    config()->set('seviaflow-ui.dashboards', [
        RoleOnlyFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'No Role Dashboard User',
        'email' => 'no-role-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/manager')
        ->assertOk()
        ->assertSee('No dashboards are currently visible for your account.');
});

it('default dashboard skips invisible role based dashboard', function (): void {
    config()->set('seviaflow.permissions.enabled', true);
    config()->set('seviaflow-ui.dashboards', [
        RoleOnlyFeatureDashboard::class,
        OperationsFeatureDashboard::class,
    ]);
    config()->set('seviaflow-ui.dashboard.default', 'manager');

    $user = DashboardTestUser::query()->create([
        'name' => 'Role Default Fallback User',
        'email' => 'role-default-fallback@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('Operations Dashboard')
        ->assertDontSee('Manager Dashboard');
});

it('allows permission based dashboard visibility when granted', function (): void {
    config()->set('seviaflow.permissions.enabled', true);
    config()->set('seviaflow-ui.dashboards', [
        PermissionOnlyFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Permission Dashboard User',
        'email' => 'permission-dashboard@example.com',
    ]);

    $role = Role::query()->create([
        'name' => 'Operations',
        'slug' => 'ops',
    ]);

    Permission::query()->create([
        'role_id' => $role->getKey(),
        'resource' => 'dashboard.operations',
        'ability' => 'view',
        'allowed' => true,
    ]);

    RoleAssignment::query()->create([
        'role_id' => $role->getKey(),
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/permission-ops')
        ->assertOk()
        ->assertSee('Operations Permission Dashboard');
});

it('denies permission based dashboard visibility when missing', function (): void {
    config()->set('seviaflow.permissions.enabled', true);
    config()->set('seviaflow-ui.dashboards', [
        PermissionOnlyFeatureDashboard::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Missing Permission Dashboard User',
        'email' => 'missing-permission-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/permission-ops')
        ->assertOk()
        ->assertSee('No dashboards are currently visible for your account.');
});

it('uses fallback dashboard when no dashboards are configured', function (): void {
    config()->set('seviaflow-ui.dashboards', []);
    config()->set('seviaflow-ui.dashboard.default', null);

    $user = DashboardTestUser::query()->create([
        'name' => 'Fallback Dashboard User',
        'email' => 'fallback-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(Dashboard::class)
        ->assertSee('Dashboard')
        ->assertSee('Welcome to SeviaFlow');
});

it('fails clearly when duplicate dashboard slug is configured', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        OperationsFeatureDashboard::class,
        DuplicateOperationsFeatureDashboard::class,
    ]);

    expect(fn (): DashboardRegistry => app(DashboardRegistry::class))
        ->toThrow(InvalidArgumentException::class, 'Duplicate dashboard slug');
});

it('renders dashboard at admin root for authenticated user', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-visible' => DashboardVisibleResource::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Dashboard User',
        'email' => 'dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(Dashboard::class)
        ->assertSee('Dashboard')
        ->assertSee('sf-dashboard-grid', false)
        ->assertSee('sf-dashboard-grid-panels', false)
        ->assertSee('sf-dashboard-section', false)
        ->assertSee('sf-dashboard-metric', false)
        ->assertSee('Welcome to SeviaFlow')
        ->assertSee('Available resources')
        ->assertSee('Resource overview')
        ->assertSee('Visible records')
        ->assertSee('/admin/resources/dashboard-visible', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.css', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.js', false)
        ->assertDontSee('@vite', false);
});

it('renders dashboard using custom route prefix', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    $user = DashboardTestUser::query()->create([
        'name' => 'Backoffice User',
        'email' => 'backoffice-dashboard@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/backoffice')
        ->assertOk()
        ->assertSeeLivewire(Dashboard::class);
});

it('registers dashboard show route using custom route prefix', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');
    config()->set('seviaflow-ui.dashboards', [
        OperationsFeatureDashboard::class,
    ]);

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    $user = DashboardTestUser::query()->create([
        'name' => 'Backoffice Dashboard User',
        'email' => 'backoffice-dashboard-slug@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/backoffice/dashboards/operations')
        ->assertOk()
        ->assertSee('Operations Dashboard');
});

it('registers dashboard route using configured route name prefix', function (): void {
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice-admin.');

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    expect(Route::has('backoffice-admin.dashboard'))->toBeTrue();
});

it('blocks guest from dashboard according to admin middleware', function (): void {
    pest_test_case()->get('/admin')
        ->assertStatus(302);
});

it('dashboard does not redirect to application root', function (): void {
    $user = DashboardTestUser::query()->create([
        'name' => 'No Redirect User',
        'email' => 'no-redirect@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertDontSee('http-equiv="refresh"', false);
});

it('shows useful empty state when no resources are registered', function (): void {
    config()->set('seviaflow.resources', []);

    $user = DashboardTestUser::query()->create([
        'name' => 'Empty State User',
        'email' => 'empty-state@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('No resources are available yet.')
        ->assertSee('sf-dashboard-empty', false);
});

it('hides unauthorized resources from dashboard overview', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-visible' => DashboardVisibleResource::class,
        'dashboard-hidden' => DashboardHiddenResource::class,
    ]);

    Gate::policy(DashboardVisibleRecord::class, DashboardVisiblePolicy::class);
    Gate::policy(DashboardHiddenRecord::class, DashboardHiddenPolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'Authorized Resource User',
        'email' => 'authorized-resources@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('Visible records')
        ->assertDontSee('Hidden records')
        ->assertSee('/admin/resources/dashboard-visible', false)
        ->assertDontSee('/admin/resources/dashboard-hidden', false);
});

it('does not reveal forbidden resource labels anywhere on dashboard', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-visible' => DashboardVisibleResource::class,
        'dashboard-hidden' => DashboardHiddenResource::class,
        'dashboard-workflow-hidden' => DashboardWorkflowHiddenResource::class,
    ]);

    Gate::policy(DashboardVisibleRecord::class, DashboardVisiblePolicy::class);
    Gate::policy(DashboardHiddenRecord::class, DashboardHiddenPolicy::class);
    Gate::policy(DashboardWorkflowHiddenRecord::class, DashboardWorkflowHiddenPolicy::class);

    $actor = DashboardTestUser::query()->create([
        'name' => 'No Leak User',
        'email' => 'no-leak@example.com',
    ]);

    pest_test_case()->actingAs($actor, 'web');

    DB::table('seviaflow_workflow_activities')->insert([
        'resource' => DashboardWorkflowHiddenResource::class,
        'record_type' => DashboardWorkflowHiddenRecord::class,
        'record_id' => '501',
        'transition' => 'approve',
        'from_state' => 'draft',
        'to_state' => 'published',
        'user_type' => DashboardTestUser::class,
        'user_id' => (string) $actor->getKey(),
        'comment' => null,
        'created_at' => Carbon::parse('2026-01-05 11:05:00'),
        'updated_at' => Carbon::parse('2026-01-05 11:05:00'),
    ]);

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('Visible records')
        ->assertDontSee('Hidden records')
        ->assertDontSee('Hidden workflow records')
        ->assertDontSee('DashboardWorkflowHiddenRecord #501');
});

it('uses configured prefix in dashboard resource links', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice-admin.');
    config()->set('seviaflow.resources', [
        'dashboard-visible' => DashboardVisibleResource::class,
    ]);

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    $user = DashboardTestUser::query()->create([
        'name' => 'Backoffice Link User',
        'email' => 'backoffice-link@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/backoffice')
        ->assertOk()
        ->assertSee('/backoffice/resources/dashboard-visible', false);
});

it('keeps resource counts disabled by default', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-visible' => DashboardVisibleResource::class,
    ]);

    DashboardVisibleRecord::query()->create(['name' => 'Record A']);
    DashboardVisibleRecord::query()->create(['name' => 'Record B']);

    $user = DashboardTestUser::query()->create([
        'name' => 'No Count User',
        'email' => 'no-count@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertDontSee('Records:');
});

it('shows resource counts when dashboard counts are enabled', function (): void {
    config()->set('seviaflow-ui.dashboard.show_resource_counts', true);
    config()->set('seviaflow.resources', [
        'dashboard-visible' => DashboardVisibleResource::class,
    ]);

    DashboardVisibleRecord::query()->create(['name' => 'Record A']);
    DashboardVisibleRecord::query()->create(['name' => 'Record B']);

    $user = DashboardTestUser::query()->create([
        'name' => 'Count User',
        'email' => 'count-user@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('Records: 2');
});

it('shows workflow-enabled resources in workflow overview', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
        'dashboard-visible' => DashboardVisibleResource::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Workflow User',
        'email' => 'workflow-user@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('Workflow overview')
        ->assertSee('Workflow records')
        ->assertSee('State field: status')
        ->assertSee('States: Draft, Pending review, Published')
        ->assertSee('/admin/resources/dashboard-workflow', false);
});

it('does not show resources without workflow in workflow overview', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-visible' => DashboardVisibleResource::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'No Workflow Resource User',
        'email' => 'no-workflow-resource@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertDontSee('State field: status')
        ->assertDontSee('Workflow records');
});

it('hides unauthorized workflow resources in workflow overview', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
        'dashboard-workflow-hidden' => DashboardWorkflowHiddenResource::class,
    ]);

    Gate::policy(DashboardWorkflowRecord::class, DashboardWorkflowVisiblePolicy::class);
    Gate::policy(DashboardWorkflowHiddenRecord::class, DashboardWorkflowHiddenPolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'Workflow Authorization User',
        'email' => 'workflow-auth@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('Workflow records')
        ->assertDontSee('Hidden workflow records')
        ->assertSee('/admin/resources/dashboard-workflow', false)
        ->assertDontSee('/admin/resources/dashboard-workflow-hidden', false);
});

it('shows workflow empty state when no workflow resources are available', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-visible' => DashboardVisibleResource::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Workflow Empty User',
        'email' => 'workflow-empty@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('No workflow-enabled resources are currently available.')
        ->assertSee('sf-dashboard-empty', false);
});

it('keeps workflow attention counts disabled by default', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
    ]);

    DashboardWorkflowRecord::query()->create(['name' => 'A', 'status' => 'draft']);
    DashboardWorkflowRecord::query()->create(['name' => 'B', 'status' => 'published']);

    $user = DashboardTestUser::query()->create([
        'name' => 'Workflow Count Off User',
        'email' => 'workflow-count-off@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertDontSee('Needs attention:');
});

it('shows workflow attention counts when enabled', function (): void {
    config()->set('seviaflow-ui.dashboard.show_workflow_attention_counts', true);
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
    ]);

    DashboardWorkflowRecord::query()->create(['name' => 'A', 'status' => 'draft']);
    DashboardWorkflowRecord::query()->create(['name' => 'B', 'status' => 'pending_review']);
    DashboardWorkflowRecord::query()->create(['name' => 'C', 'status' => 'published']);

    $user = DashboardTestUser::query()->create([
        'name' => 'Workflow Count On User',
        'email' => 'workflow-count-on@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('Needs attention: 2');
});

it('shows recent workflow activities on dashboard', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
    ]);

    $actor = DashboardTestUser::query()->create([
        'name' => 'Activity Actor',
        'email' => 'activity-actor@example.com',
    ]);

    pest_test_case()->actingAs($actor, 'web');

    DashboardWorkflowRecord::query()->create(['name' => 'Invoice #1', 'status' => 'published']);

    DB::table('seviaflow_workflow_activities')->insert([
        'resource' => DashboardWorkflowResource::class,
        'record_type' => DashboardWorkflowRecord::class,
        'record_id' => '1',
        'transition' => 'approve',
        'from_state' => 'pending_review',
        'to_state' => 'published',
        'user_type' => DashboardTestUser::class,
        'user_id' => (string) $actor->getKey(),
        'comment' => 'Approved by supervisor',
        'created_at' => Carbon::parse('2026-01-05 10:15:00'),
        'updated_at' => Carbon::parse('2026-01-05 10:15:00'),
    ]);

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('Recent activity')
        ->assertSee('Workflow records')
        ->assertSeeInOrder(['Approve:', 'Pending review', 'Published'])
        ->assertSee('Published')
        ->assertSee('DashboardWorkflowRecord #1')
        ->assertSee('by Activity Actor')
        ->assertSee('Comment: Approved by supervisor')
        ->assertSee('2026-01-05 10:15:00');
});

it('limits recent activity items', function (): void {
    config()->set('seviaflow-ui.dashboard.recent_activity_limit', 2);
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
    ]);

    $actor = DashboardTestUser::query()->create([
        'name' => 'Limit Actor',
        'email' => 'limit-actor@example.com',
    ]);

    pest_test_case()->actingAs($actor, 'web');

    for ($index = 1; $index <= 3; $index++) {
        DB::table('seviaflow_workflow_activities')->insert([
            'resource' => DashboardWorkflowResource::class,
            'record_type' => DashboardWorkflowRecord::class,
            'record_id' => (string) $index,
            'transition' => 'approve',
            'from_state' => 'pending_review',
            'to_state' => 'published',
            'user_type' => DashboardTestUser::class,
            'user_id' => (string) $actor->getKey(),
            'comment' => null,
            'created_at' => Carbon::parse('2026-01-05 10:1'.$index.':00'),
            'updated_at' => Carbon::parse('2026-01-05 10:1'.$index.':00'),
        ]);
    }

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('DashboardWorkflowRecord #3')
        ->assertSee('DashboardWorkflowRecord #2')
        ->assertDontSee('DashboardWorkflowRecord #1');
});

it('hides unauthorized resource activities from recent activity', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
        'dashboard-workflow-hidden' => DashboardWorkflowHiddenResource::class,
    ]);

    Gate::policy(DashboardWorkflowRecord::class, DashboardWorkflowVisiblePolicy::class);
    Gate::policy(DashboardWorkflowHiddenRecord::class, DashboardWorkflowHiddenPolicy::class);

    $actor = DashboardTestUser::query()->create([
        'name' => 'Authorization Actor',
        'email' => 'authorization-actor@example.com',
    ]);

    pest_test_case()->actingAs($actor, 'web');

    DB::table('seviaflow_workflow_activities')->insert([
        [
            'resource' => DashboardWorkflowResource::class,
            'record_type' => DashboardWorkflowRecord::class,
            'record_id' => '10',
            'transition' => 'approve',
            'from_state' => 'pending_review',
            'to_state' => 'published',
            'user_type' => DashboardTestUser::class,
            'user_id' => (string) $actor->getKey(),
            'comment' => null,
            'created_at' => Carbon::parse('2026-01-05 11:00:00'),
            'updated_at' => Carbon::parse('2026-01-05 11:00:00'),
        ],
        [
            'resource' => DashboardWorkflowHiddenResource::class,
            'record_type' => DashboardWorkflowHiddenRecord::class,
            'record_id' => '99',
            'transition' => 'approve',
            'from_state' => 'draft',
            'to_state' => 'published',
            'user_type' => DashboardTestUser::class,
            'user_id' => (string) $actor->getKey(),
            'comment' => null,
            'created_at' => Carbon::parse('2026-01-05 11:05:00'),
            'updated_at' => Carbon::parse('2026-01-05 11:05:00'),
        ],
    ]);

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('Workflow records')
        ->assertSee('DashboardWorkflowRecord #10')
        ->assertDontSee('Hidden workflow records')
        ->assertDontSee('DashboardWorkflowHiddenRecord #99');
});

it('shows recent activity empty state when activity table is empty', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
    ]);

    $user = DashboardTestUser::query()->create([
        'name' => 'Activity Empty User',
        'email' => 'activity-empty@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('No recent workflow activity found.')
        ->assertSee('sf-dashboard-empty', false);
});

it('does not leak authorization-sensitive dashboard sections between users when cache is enabled', function (): void {
    config()->set('cache.default', 'array');
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow-ui.cache.dashboard', true);
    config()->set('seviaflow.resources', [
        'dashboard-visible' => DashboardVisibleResource::class,
        'dashboard-hidden' => DashboardHiddenResource::class,
    ]);

    Gate::policy(DashboardVisibleRecord::class, DashboardVisiblePolicy::class);
    Gate::policy(DashboardHiddenRecord::class, DashboardHiddenPolicy::class);

    $firstUser = DashboardTestUser::query()->create([
        'name' => 'First Dashboard User',
        'email' => 'dashboard-cache-first@example.com',
    ]);

    $secondUser = DashboardTestUser::query()->create([
        'name' => 'Second Dashboard User',
        'email' => 'dashboard-cache-second@example.com',
    ]);

    pest_test_case()->actingAs($firstUser, 'web');
    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('Visible records')
        ->assertDontSee('Hidden records');

    auth()->logout();

    pest_test_case()->actingAs($secondUser, 'web');
    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('Visible records')
        ->assertDontSee('Hidden records');
});

it('does not cache recent activity globally when dashboard cache is enabled', function (): void {
    config()->set('cache.default', 'array');
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow-ui.cache.dashboard', true);
    config()->set('seviaflow.resources', [
        'dashboard-workflow' => DashboardWorkflowResource::class,
    ]);
    Gate::policy(DashboardWorkflowRecord::class, DashboardWorkflowFirstUserOnlyPolicy::class);

    $firstUser = DashboardTestUser::query()->create([
        'name' => 'Activity First User',
        'email' => 'activity-first@example.com',
    ]);

    $secondUser = DashboardTestUser::query()->create([
        'name' => 'Activity Second User',
        'email' => 'activity-second@example.com',
    ]);

    DB::table('seviaflow_workflow_activities')->insert([
        'resource' => DashboardWorkflowResource::class,
        'record_type' => DashboardWorkflowRecord::class,
        'record_id' => '71',
        'transition' => 'approve',
        'from_state' => 'pending_review',
        'to_state' => 'published',
        'user_type' => DashboardTestUser::class,
        'user_id' => (string) $firstUser->getKey(),
        'comment' => null,
        'created_at' => Carbon::parse('2026-01-05 12:00:00'),
        'updated_at' => Carbon::parse('2026-01-05 12:00:00'),
    ]);

    pest_test_case()->actingAs($firstUser, 'web');
    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('DashboardWorkflowRecord #71');

    auth()->logout();

    pest_test_case()->actingAs($secondUser, 'web');
    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertDontSee('DashboardWorkflowRecord #71')
        ->assertSee('No recent workflow activity found.');
});

it('shows create quick action when user can create resource', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-creatable' => DashboardCreatableResource::class,
    ]);

    Gate::policy(DashboardCreatableRecord::class, DashboardCreatablePolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'Quick Create User',
        'email' => 'quick-create@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('Quick actions')
        ->assertSee('Create creatable records')
        ->assertSee('/admin/resources/dashboard-creatable/create', false);
});

it('hides create quick action when user cannot create resource', function (): void {
    config()->set('seviaflow.resources', [
        'dashboard-non-creatable' => DashboardNonCreatableResource::class,
    ]);

    Gate::policy(DashboardNonCreatableRecord::class, DashboardNonCreatablePolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'Quick Create Denied User',
        'email' => 'quick-create-denied@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertDontSee('Create non creatable records')
        ->assertDontSee('/admin/resources/dashboard-non-creatable/create', false);
});

it('permissions disabled mode still follows policy for create quick actions', function (): void {
    config()->set('seviaflow.permissions.enabled', false);
    config()->set('seviaflow.resources', [
        'dashboard-non-creatable' => DashboardNonCreatableResource::class,
        'dashboard-creatable' => DashboardCreatableResource::class,
    ]);

    Gate::policy(DashboardNonCreatableRecord::class, DashboardNonCreatablePolicy::class);
    Gate::policy(DashboardCreatableRecord::class, DashboardCreatablePolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'Policy Mode User',
        'email' => 'policy-mode@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('Create creatable records')
        ->assertDontSee('Create non creatable records')
        ->assertSee('/admin/resources/dashboard-creatable/create', false)
        ->assertDontSee('/admin/resources/dashboard-non-creatable/create', false);
});

it('uses configured prefix for create quick action url', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice-admin.');
    config()->set('seviaflow.resources', [
        'dashboard-creatable' => DashboardCreatableResource::class,
    ]);

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    Gate::policy(DashboardCreatableRecord::class, DashboardCreatablePolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'Prefixed Quick Action User',
        'email' => 'prefixed-quick-action@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/backoffice')
        ->assertOk()
        ->assertSee('/backoffice/resources/dashboard-creatable/create', false);
});

it('shows profile and account security shortcuts when routes exist', function (): void {
    $user = DashboardTestUser::query()->create([
        'name' => 'Profile Shortcut User',
        'email' => 'profile-shortcut@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('View profile')
        ->assertSee('Account security')
        ->assertSee('/admin/profile', false)
        ->assertSee('/admin/profile/password', false);
});

it('hides roles shortcut when unauthorized', function (): void {
    config()->set('seviaflow.permissions.enabled', true);

    $user = DashboardTestUser::query()->create([
        'name' => 'No Roles Access User',
        'email' => 'no-roles-access@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertDontSee('Manage roles')
        ->assertDontSee('/admin/settings/roles', false);
});

it('shows quick actions empty state when no actions are available', function (): void {
    config()->set('seviaflow-ui.auth.enabled', false);
    config()->set('seviaflow.permissions.enabled', true);
    config()->set('seviaflow.resources', [
        'dashboard-non-creatable' => DashboardNonCreatableResource::class,
    ]);

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    Gate::policy(DashboardNonCreatableRecord::class, DashboardNonCreatablePolicy::class);

    $user = DashboardTestUser::query()->create([
        'name' => 'No Quick Actions User',
        'email' => 'no-quick-actions@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('No quick actions are currently available.')
        ->assertSee('sf-dashboard-empty', false);
});

/**
 * @property string $email
 */
final class DashboardTestUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}

final class DashboardVisibleRecord extends Model
{
    protected $table = 'dashboard_visible_records';

    protected $guarded = [];
}

final class DashboardHiddenRecord extends Model
{
    protected $table = 'dashboard_hidden_records';

    protected $guarded = [];
}

final class DashboardWorkflowRecord extends Model
{
    protected $table = 'dashboard_workflow_records';

    protected $guarded = [];
}

final class DashboardWorkflowHiddenRecord extends Model
{
    protected $table = 'dashboard_workflow_hidden_records';

    protected $guarded = [];
}

final class DashboardVisibleResource extends Resource
{
    protected static string $model = DashboardVisibleRecord::class;

    public static function pluralLabel(): string
    {
        return 'Visible records';
    }
}

final class DashboardHiddenResource extends Resource
{
    protected static string $model = DashboardHiddenRecord::class;

    public static function pluralLabel(): string
    {
        return 'Hidden records';
    }
}

final class DashboardVisiblePolicy
{
    public function viewAny(DashboardTestUser $user): bool
    {
        return true;
    }
}

final class DashboardWorkflowResource extends Resource
{
    protected static string $model = DashboardWorkflowRecord::class;

    public static function pluralLabel(): string
    {
        return 'Workflow records';
    }

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->states([
                State::make('draft')->label('Draft'),
                State::make('pending_review')->label('Pending review'),
                State::make('published')->label('Published')->final(),
            ]);
    }
}

final class DashboardWorkflowHiddenResource extends Resource
{
    protected static string $model = DashboardWorkflowHiddenRecord::class;

    public static function pluralLabel(): string
    {
        return 'Hidden workflow records';
    }

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->states([
                State::make('draft')->label('Draft'),
                State::make('published')->label('Published')->final(),
            ]);
    }
}

final class DashboardHiddenPolicy
{
    public function viewAny(DashboardTestUser $user): bool
    {
        return false;
    }
}

final class DashboardWorkflowVisiblePolicy
{
    public function viewAny(DashboardTestUser $user): bool
    {
        return true;
    }

    public function view(DashboardTestUser $user, DashboardWorkflowRecord $record): bool
    {
        return true;
    }
}

final class DashboardWorkflowHiddenPolicy
{
    public function viewAny(DashboardTestUser $user): bool
    {
        return false;
    }
}

final class DashboardWorkflowFirstUserOnlyPolicy
{
    public function viewAny(DashboardTestUser $user): bool
    {
        return $user->email === 'activity-first@example.com';
    }
}

final class DashboardCreatableRecord extends Model
{
    protected $table = 'dashboard_creatable_records';

    protected $guarded = [];
}

final class DashboardNonCreatableRecord extends Model
{
    protected $table = 'dashboard_non_creatable_records';

    protected $guarded = [];
}

final class DashboardCreatableResource extends Resource
{
    protected static string $model = DashboardCreatableRecord::class;

    public static function pluralLabel(): string
    {
        return 'Creatable records';
    }
}

final class DashboardNonCreatableResource extends Resource
{
    protected static string $model = DashboardNonCreatableRecord::class;

    public static function pluralLabel(): string
    {
        return 'Non creatable records';
    }
}

final class DashboardCreatablePolicy
{
    public function viewAny(DashboardTestUser $user): bool
    {
        return true;
    }

    public function create(DashboardTestUser $user): bool
    {
        return true;
    }
}

final class DashboardNonCreatablePolicy
{
    public function viewAny(DashboardTestUser $user): bool
    {
        return true;
    }

    public function create(DashboardTestUser $user): bool
    {
        return false;
    }
}

final class OperationsFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'operations';
    }

    public static function label(): string
    {
        return 'Operations Dashboard';
    }

    public static function description(): ?string
    {
        return 'Operations queue and daily workload';
    }
}

final class SalesFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'sales';
    }

    public static function label(): string
    {
        return 'Sales Dashboard';
    }

    public static function description(): ?string
    {
        return 'Sales operations overview';
    }
}

final class HiddenFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'hidden';
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

final class DuplicateOperationsFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'operations';
    }

    public static function label(): string
    {
        return 'Duplicate Operations Dashboard';
    }
}

final class RoleOnlyFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'manager';
    }

    public static function label(): string
    {
        return 'Manager Dashboard';
    }

    public static function roles(): array
    {
        return ['manager'];
    }
}

final class PermissionOnlyFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'permission-ops';
    }

    public static function label(): string
    {
        return 'Operations Permission Dashboard';
    }

    public static function permissions(): array
    {
        return ['dashboard.operations.view'];
    }
}

final class WidgetFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'widgets';
    }

    public static function label(): string
    {
        return 'Widget Dashboard';
    }

    public static function widgets(): array
    {
        return [
            Widget::make('pending_orders')->label('Pending orders')->columnSpan(1),
            Widget::make('recent_orders')->label('Recent orders')->columnSpan(2)->cacheFor(60),
        ];
    }
}

final class WidgetHiddenFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'widgets-hidden';
    }

    public static function label(): string
    {
        return 'Widget Hidden Dashboard';
    }

    public static function widgets(): array
    {
        return [
            Widget::make('visible_widget')->label('Visible widget'),
            Widget::make('hidden_widget')
                ->label('Hidden widget')
                ->visibleIf(static fn (): bool => false),
        ];
    }
}

final class WidgetDuplicateFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'widgets-duplicate';
    }

    public static function label(): string
    {
        return 'Widget Duplicate Dashboard';
    }

    public static function widgets(): array
    {
        return [
            Widget::make('same_widget'),
            Widget::make('same_widget'),
        ];
    }
}

final class StatsFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'stats';
    }

    public static function label(): string
    {
        return 'Stats Dashboard';
    }

    public static function widgets(): array
    {
        return [
            StatsWidget::make('pending_orders')
                ->label('Pending orders')
                ->description('Orders waiting for payment')
                ->value(12)
                ->color('amber'),
            StatsWidget::make('open_tickets')
                ->label('Open tickets')
                ->value(static fn (): int => 8),
        ];
    }
}

final class ChartFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'charts';
    }

    public static function label(): string
    {
        return 'Charts Dashboard';
    }

    public static function widgets(): array
    {
        return [
            ChartWidget::make('orders_by_status')
                ->label('Orders by status')
                ->type('doughnut')
                ->data(static fn (): array => [
                    'labels' => ['Draft', 'Paid', 'Cancelled'],
                    'datasets' => [
                        [
                            'label' => 'Orders',
                            'data' => [12, 45, 4],
                        ],
                    ],
                ])
                ->cacheFor(60),
        ];
    }
}

final class TableFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'table';
    }

    public static function label(): string
    {
        return 'Table Dashboard';
    }

    public static function widgets(): array
    {
        return [
            TableWidget::make('recent_orders')
                ->label('Recent orders')
                ->resource(DashboardVisibleResource::class)
                ->columns([
                    TextColumn::make('name'),
                ])
                ->query(static fn (Builder $query): Builder => $query->latest('id'))
                ->limit(2),
        ];
    }
}

final class TableEmptyFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'table-empty';
    }

    public static function label(): string
    {
        return 'Table Empty Dashboard';
    }

    public static function widgets(): array
    {
        return [
            TableWidget::make('empty_orders')
                ->label('Recent orders')
                ->resource(DashboardVisibleResource::class)
                ->columns([
                    TextColumn::make('name'),
                ])
                ->limit(2),
        ];
    }
}

final class TableDeniedFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'table-denied';
    }

    public static function label(): string
    {
        return 'Table Denied Dashboard';
    }

    public static function widgets(): array
    {
        return [
            TableWidget::make('hidden_table')
                ->label('Hidden table widget')
                ->resource(DashboardHiddenResource::class)
                ->columns([
                    TextColumn::make('name'),
                ])
                ->limit(2),
        ];
    }
}

final class WorkflowQueueFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'workflow-queue';
    }

    public static function label(): string
    {
        return 'Workflow Queue Dashboard';
    }

    public static function widgets(): array
    {
        return [
            WorkflowQueueWidget::make('orders_needing_attention')
                ->label('Orders needing attention')
                ->resource(DashboardWorkflowResource::class)
                ->states(['pending_review'])
                ->limit(2),
        ];
    }
}

final class WorkflowQueueDeniedFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'workflow-queue-denied';
    }

    public static function label(): string
    {
        return 'Workflow Queue Denied Dashboard';
    }

    public static function widgets(): array
    {
        return [
            WorkflowQueueWidget::make('hidden_workflow_queue')
                ->label('Hidden workflow queue')
                ->resource(DashboardWorkflowHiddenResource::class)
                ->states(['draft'])
                ->limit(2),
        ];
    }
}

final class WorkflowQueueInvalidStateFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'workflow-queue-invalid';
    }

    public static function label(): string
    {
        return 'Workflow Queue Invalid Dashboard';
    }

    public static function widgets(): array
    {
        return [
            WorkflowQueueWidget::make('invalid_state_queue')
                ->resource(DashboardWorkflowResource::class)
                ->states(['missing_state'])
                ->limit(2),
        ];
    }
}

final class WorkflowQueueNoWorkflowFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'workflow-queue-no-workflow';
    }

    public static function label(): string
    {
        return 'Workflow Queue No Workflow Dashboard';
    }

    public static function widgets(): array
    {
        return [
            WorkflowQueueWidget::make('no_workflow_queue')
                ->resource(DashboardVisibleResource::class)
                ->states(['draft'])
                ->limit(2),
        ];
    }
}

final class RecentActivityFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'recent-activity-widget';
    }

    public static function label(): string
    {
        return 'Recent Activity Widget Dashboard';
    }

    public static function widgets(): array
    {
        return [
            RecentActivityWidget::make('recent_activity')
                ->label('Recent activity')
                ->limit(10),
        ];
    }
}

final class LayoutEditableFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'layout-editable';
    }

    public static function label(): string
    {
        return 'Layout Editable Dashboard';
    }

    public static function widgets(): array
    {
        return [
            Widget::make('alpha_widget')->label('Alpha widget')->columnSpan(1),
            Widget::make('beta_widget')->label('Beta widget')->columnSpan(1),
        ];
    }
}
