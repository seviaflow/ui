<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Ui\Dashboard\Dashboard;
use SeviaFlow\Ui\Dashboard\Layouts\SavedDashboardLayoutStore;
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

it('saves and lists user saved dashboard layouts', function (): void {
    $user = SavedLayoutUser::query()->create(['name' => 'Owner']);
    $store = new SavedDashboardLayoutStore;

    $saved = $store->saveAs(SavedLayoutDashboard::class, $user, 'Ops layout', [
        ['widget' => 'beta', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
    ]);

    $listed = $store->listForUser(SavedLayoutDashboard::class, $user);

    expect($saved->name)->toBe('Ops layout')
        ->and($listed)->toHaveCount(1)
        ->and($listed->first()?->id)->toBe($saved->id);
});

it('applies saved layout and normalizes unknown widgets safely', function (): void {
    $user = SavedLayoutUser::query()->create(['name' => 'Owner']);
    $store = new SavedDashboardLayoutStore;

    $saved = $store->saveAs(SavedLayoutDashboard::class, $user, 'Ops layout', [
        ['widget' => 'unknown', 'x' => 3, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
        ['widget' => 'beta', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
    ]);

    $applied = $store->applySavedLayout(SavedLayoutDashboard::class, $user, (int) $saved->id);

    expect(array_column($applied, 'widget'))->not->toContain('unknown')
        ->and($applied[0]['widget'])->toBe('beta');
});

it('sets and resolves default saved layout per user', function (): void {
    $user = SavedLayoutUser::query()->create(['name' => 'Owner']);
    $store = new SavedDashboardLayoutStore;

    $a = $store->saveAs(SavedLayoutDashboard::class, $user, 'Layout A', [
        ['widget' => 'alpha', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
    ]);
    $b = $store->saveAs(SavedLayoutDashboard::class, $user, 'Layout B', [
        ['widget' => 'beta', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
    ]);

    $store->setDefaultSavedLayout(SavedLayoutDashboard::class, $user, (int) $b->id);

    $default = $store->defaultSavedLayout(SavedLayoutDashboard::class, $user);

    expect($default?->id)->toBe($b->id)
        ->and((bool) $default?->is_default)->toBeTrue()
        ->and($default?->id)->not->toBe($a->id);
});

it('enforces private ownership for delete and does not leak between users', function (): void {
    $owner = SavedLayoutUser::query()->create(['name' => 'Owner']);
    $other = SavedLayoutUser::query()->create(['name' => 'Other']);
    $store = new SavedDashboardLayoutStore;

    $saved = $store->saveAs(SavedLayoutDashboard::class, $owner, 'Owner layout', [
        ['widget' => 'alpha', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
    ]);

    expect($store->deleteSavedLayout(SavedLayoutDashboard::class, $other, (int) $saved->id))->toBeFalse()
        ->and($store->listForUser(SavedLayoutDashboard::class, $owner))->toHaveCount(1)
        ->and($store->deleteSavedLayout(SavedLayoutDashboard::class, $owner, (int) $saved->id))->toBeTrue()
        ->and($store->listForUser(SavedLayoutDashboard::class, $owner))->toHaveCount(0);
});

it('lists only private layouts owned by current user', function (): void {
    $owner = SavedLayoutUser::query()->create(['name' => 'Owner']);
    $other = SavedLayoutUser::query()->create(['name' => 'Other']);
    $store = new SavedDashboardLayoutStore;

    $store->saveAs(SavedLayoutDashboard::class, $owner, 'Owner layout', [
        ['widget' => 'alpha', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
    ]);
    $store->saveAs(SavedLayoutDashboard::class, $other, 'Other layout', [
        ['widget' => 'beta', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
    ]);

    $ownerList = $store->listForUser(SavedLayoutDashboard::class, $owner);

    expect($ownerList)->toHaveCount(1)
        ->and($ownerList->first()?->name)->toBe('Owner layout');
});

final class SavedLayoutUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}

final class SavedLayoutDashboard extends Dashboard
{
    public static function slug(): string
    {
        return 'saved-layout';
    }

    public static function label(): string
    {
        return 'Saved layout dashboard';
    }

    public static function widgets(): array
    {
        return [
            Widget::make('alpha')->columnSpan(1),
            Widget::make('beta')->columnSpan(1),
        ];
    }
}
