<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Ui\Dashboard\Dashboard as DashboardDefinition;
use SeviaFlow\Ui\Dashboard\Widgets\Widget;
use SeviaFlow\Ui\Livewire\Dashboard;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Schema::create('seviaflow_dashboard_layouts', function (Blueprint $table): void {
        $table->id();
        $table->string('dashboard');
        $table->string('user_type')->nullable();
        $table->string('user_id')->nullable();
        $table->json('layout');
        $table->timestamps();
    });

    Schema::create('seviaflow_saved_dashboards', function (Blueprint $table): void {
        $table->id();
        $table->string('dashboard');
        $table->string('name');
        $table->string('user_type')->nullable();
        $table->string('user_id')->nullable();
        $table->string('visibility')->default('private');
        $table->json('layout');
        $table->boolean('is_default')->default(false);
        $table->timestamps();
    });
});

it('renders dashboard switcher when multiple dashboards are visible', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        PolishPrimaryDashboard::class,
        PolishSecondaryDashboard::class,
    ]);

    $user = DashboardPolishUser::query()->create(['name' => 'User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/polish-primary')
        ->assertOk()
        ->assertSee('sf-dashboard-switcher', false)
        ->assertSee('Polish Primary')
        ->assertSee('Polish Secondary');
});

it('hides dashboard switcher when only one dashboard is visible', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        PolishPrimaryDashboard::class,
    ]);

    $user = DashboardPolishUser::query()->create(['name' => 'User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/polish-primary')
        ->assertOk()
        ->assertDontSee('sf-dashboard-switcher', false);
});

it('renders saved layout dropdown controls for customizable dashboard', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        PolishPrimaryDashboard::class,
    ]);

    $user = DashboardPolishUser::query()->create(['name' => 'User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/dashboards/polish-primary')
        ->assertOk()
        ->assertSee('Saved layouts')
        ->assertSee('Save as new');
});

it('renders edit mode controls and layout classes', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        PolishPrimaryDashboard::class,
    ]);

    $user = DashboardPolishUser::query()->create(['name' => 'User']);
    pest_test_case()->actingAs($user, 'web');

    Livewire::test(Dashboard::class, ['dashboard' => 'polish-primary'])
        ->call('startLayoutEditing')
        ->assertSet('layoutEditing', true)
        ->assertSee('Save layout')
        ->assertSee('Reset to default')
        ->assertSee('Drag to reorder')
        ->assertSee('sf-dashboard-widget-grid-editing');
});

it('shows no visible dashboards empty state', function (): void {
    config()->set('seviaflow-ui.dashboards', [
        PolishHiddenDashboard::class,
    ]);

    $user = DashboardPolishUser::query()->create(['name' => 'User']);
    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('No dashboards are currently visible for your account.');
});

final class DashboardPolishUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}

final class PolishPrimaryDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'polish-primary';
    }

    public static function label(): string
    {
        return 'Polish Primary';
    }

    public static function widgets(): array
    {
        return [
            Widget::make('first')->label('First'),
        ];
    }
}

final class PolishSecondaryDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'polish-secondary';
    }

    public static function label(): string
    {
        return 'Polish Secondary';
    }

    public static function widgets(): array
    {
        return [
            Widget::make('second')->label('Second'),
        ];
    }
}

final class PolishHiddenDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'polish-hidden';
    }

    public static function label(): string
    {
        return 'Polish Hidden';
    }

    public static function visibleFor(mixed $user): bool
    {
        return false;
    }
}
