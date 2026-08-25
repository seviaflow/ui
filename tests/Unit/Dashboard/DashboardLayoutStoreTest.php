<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Ui\Dashboard\Dashboard;
use SeviaFlow\Ui\Dashboard\Layouts\DashboardLayoutStore;
use SeviaFlow\Ui\Dashboard\Widgets\Widget;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
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
    });
});

it('generates default layout from dashboard widgets', function (): void {
    $store = new DashboardLayoutStore;

    $layout = $store->defaultLayout(LayoutTestDashboard::class, null);

    expect($layout)->toHaveCount(2)
        ->and($layout[0])->toMatchArray([
            'widget' => 'pending_orders',
            'x' => 0,
            'y' => 0,
            'w' => 1,
            'h' => 1,
            'visible' => true,
        ])
        ->and($layout[1])->toMatchArray([
            'widget' => 'recent_orders',
            'x' => 0,
            'y' => 1,
            'w' => 2,
            'h' => 1,
            'visible' => true,
        ]);
});

it('saves and loads user scoped layout', function (): void {
    $store = new DashboardLayoutStore;
    $user = LayoutTestUser::query()->create(['name' => 'Owner']);

    $saved = $store->saveLayout(LayoutTestDashboard::class, $user, [
        ['widget' => 'pending_orders', 'x' => 1, 'y' => 2, 'w' => 1, 'h' => 1, 'visible' => true],
    ]);

    $loaded = $store->getLayout(LayoutTestDashboard::class, $user);

    expect($saved[0]['widget'])->toBe('pending_orders')
        ->and($loaded[0]['widget'])->toBe('pending_orders')
        ->and($loaded[0]['x'])->toBe(1)
        ->and($loaded[0]['y'])->toBe(2);
});

it('scopes layouts per user and dashboard', function (): void {
    $store = new DashboardLayoutStore;
    $owner = LayoutTestUser::query()->create(['name' => 'Owner']);
    $other = LayoutTestUser::query()->create(['name' => 'Other']);

    $store->saveLayout(LayoutTestDashboard::class, $owner, [
        ['widget' => 'pending_orders', 'x' => 2, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
    ]);

    $ownerLayout = $store->getLayout(LayoutTestDashboard::class, $owner);
    $otherLayout = $store->getLayout(LayoutTestDashboard::class, $other);
    $otherDashboardLayout = $store->getLayout(LayoutTestSecondaryDashboard::class, $owner);

    expect($ownerLayout[0]['x'])->toBe(2)
        ->and($otherLayout[0]['x'])->toBe(0)
        ->and($otherDashboardLayout[0]['widget'])->toBe('secondary_widget');
});

it('ignores unknown widget keys safely', function (): void {
    $store = new DashboardLayoutStore;
    $user = LayoutTestUser::query()->create(['name' => 'Owner']);

    $layout = $store->saveLayout(LayoutTestDashboard::class, $user, [
        ['widget' => 'unknown_widget', 'x' => 9, 'y' => 9, 'w' => 9, 'h' => 9, 'visible' => false],
        ['widget' => 'pending_orders', 'x' => 3, 'y' => 1, 'w' => 1, 'h' => 1, 'visible' => true],
    ]);

    expect(array_column($layout, 'widget'))->not->toContain('unknown_widget')
        ->and($layout[0]['widget'])->toBe('pending_orders')
        ->and($layout[0]['x'])->toBe(3);
});

it('reset returns default layout', function (): void {
    $store = new DashboardLayoutStore;
    $user = LayoutTestUser::query()->create(['name' => 'Owner']);

    $store->saveLayout(LayoutTestDashboard::class, $user, [
        ['widget' => 'pending_orders', 'x' => 4, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
    ]);

    $reset = $store->resetLayout(LayoutTestDashboard::class, $user);

    expect($reset[0]['widget'])->toBe('pending_orders')
        ->and($reset[0]['x'])->toBe(0)
        ->and($store->getLayout(LayoutTestDashboard::class, $user)[0]['x'])->toBe(0);
});

it('cannot save layout without authenticated user', function (): void {
    $store = new DashboardLayoutStore;

    expect(fn () => $store->saveLayout(LayoutTestDashboard::class, null, []))
        ->toThrow(InvalidArgumentException::class, 'Dashboard layouts require an authenticated user.');
});

final class LayoutTestUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}

final class LayoutTestDashboard extends Dashboard
{
    public static function slug(): string
    {
        return 'layout-test';
    }

    public static function label(): string
    {
        return 'Layout Test';
    }

    public static function widgets(): array
    {
        return [
            Widget::make('pending_orders')->columnSpan(1),
            Widget::make('recent_orders')->columnSpan(2),
        ];
    }
}

final class LayoutTestSecondaryDashboard extends Dashboard
{
    public static function slug(): string
    {
        return 'layout-test-secondary';
    }

    public static function label(): string
    {
        return 'Layout Test Secondary';
    }

    public static function widgets(): array
    {
        return [
            Widget::make('secondary_widget')->columnSpan(1),
        ];
    }
}
