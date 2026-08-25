<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Ui\Dashboard\Dashboard as DashboardDefinition;
use SeviaFlow\Ui\Dashboard\Layouts\SavedDashboard;
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

    config()->set('seviaflow-ui.dashboards', [
        SavedLayoutsFeatureDashboard::class,
    ]);
});

it('saves named dashboard layout and lists it for current user', function (): void {
    $user = SavedLayoutsFeatureUser::query()->create(['name' => 'Owner']);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(Dashboard::class, ['dashboard' => 'saved-layouts-feature'])
        ->set('layoutPayload', json_encode([
            ['widget' => 'two', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
            ['widget' => 'one', 'x' => 0, 'y' => 1, 'w' => 1, 'h' => 1, 'visible' => true],
        ]))
        ->set('savedLayoutName', 'My Focus')
        ->call('saveCurrentAsLayout')
        ->assertSee('My Focus');

    expect(SavedDashboard::query()->where('name', 'My Focus')->count())->toBe(1);
});

it('switches to selected saved layout', function (): void {
    $user = SavedLayoutsFeatureUser::query()->create(['name' => 'Owner']);

    $saved = SavedDashboard::query()->create([
        'dashboard' => 'saved-layouts-feature',
        'name' => 'Switch Me',
        'user_type' => $user->getMorphClass(),
        'user_id' => (string) $user->getKey(),
        'visibility' => 'private',
        'layout' => [
            ['widget' => 'two', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
            ['widget' => 'one', 'x' => 0, 'y' => 1, 'w' => 1, 'h' => 1, 'visible' => true],
        ],
        'is_default' => false,
    ]);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(Dashboard::class, ['dashboard' => 'saved-layouts-feature'])
        ->set('selectedSavedLayoutId', (int) $saved->id)
        ->call('applySavedLayout');

    $layout = DB::table('seviaflow_dashboard_layouts')
        ->where('dashboard', 'saved-layouts-feature')
        ->where('user_type', SavedLayoutsFeatureUser::class)
        ->where('user_id', (string) $user->getKey())
        ->first();

    expect($layout)->not->toBeNull();
});

it('sets default saved layout and loads it on dashboard open', function (): void {
    $user = SavedLayoutsFeatureUser::query()->create(['name' => 'Owner']);

    $saved = SavedDashboard::query()->create([
        'dashboard' => 'saved-layouts-feature',
        'name' => 'Default Layout',
        'user_type' => $user->getMorphClass(),
        'user_id' => (string) $user->getKey(),
        'visibility' => 'private',
        'layout' => [
            ['widget' => 'two', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
            ['widget' => 'one', 'x' => 0, 'y' => 1, 'w' => 1, 'h' => 1, 'visible' => true],
        ],
        'is_default' => false,
    ]);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(Dashboard::class, ['dashboard' => 'saved-layouts-feature'])
        ->set('selectedSavedLayoutId', (int) $saved->id)
        ->call('setDefaultSavedLayout');

    pest_test_case()->get('/admin/dashboards/saved-layouts-feature')
        ->assertOk()
        ->assertSeeInOrder(['Two', 'One']);
});

it('deletes saved layout and enforces private ownership', function (): void {
    $owner = SavedLayoutsFeatureUser::query()->create(['name' => 'Owner']);
    $other = SavedLayoutsFeatureUser::query()->create(['name' => 'Other']);

    $saved = SavedDashboard::query()->create([
        'dashboard' => 'saved-layouts-feature',
        'name' => 'Private Layout',
        'user_type' => $owner->getMorphClass(),
        'user_id' => (string) $owner->getKey(),
        'visibility' => 'private',
        'layout' => [
            ['widget' => 'one', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
        ],
        'is_default' => false,
    ]);

    pest_test_case()->actingAs($other, 'web');
    Livewire::test(Dashboard::class, ['dashboard' => 'saved-layouts-feature'])
        ->set('selectedSavedLayoutId', (int) $saved->id)
        ->call('deleteSavedLayout');

    expect(SavedDashboard::query()->whereKey($saved->id)->exists())->toBeTrue();

    pest_test_case()->actingAs($owner, 'web');
    Livewire::test(Dashboard::class, ['dashboard' => 'saved-layouts-feature'])
        ->set('selectedSavedLayoutId', (int) $saved->id)
        ->call('deleteSavedLayout');

    expect(SavedDashboard::query()->whereKey($saved->id)->exists())->toBeFalse();
});

it('rejects invalid widget keys when saving named layout', function (): void {
    $user = SavedLayoutsFeatureUser::query()->create(['name' => 'Owner']);
    pest_test_case()->actingAs($user, 'web');

    Livewire::test(Dashboard::class, ['dashboard' => 'saved-layouts-feature'])
        ->set('layoutPayload', json_encode([
            ['widget' => 'unknown', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'visible' => true],
            ['widget' => 'one', 'x' => 0, 'y' => 1, 'w' => 1, 'h' => 1, 'visible' => true],
        ]))
        ->set('savedLayoutName', 'Safe Layout')
        ->call('saveCurrentAsLayout');

    $saved = SavedDashboard::query()->where('name', 'Safe Layout')->first();
    $layout = is_array($saved?->layout) ? $saved->layout : [];
    $widgets = array_column($layout, 'widget');

    expect($widgets)->toContain('one')
        ->and($widgets)->not->toContain('unknown');
});

final class SavedLayoutsFeatureUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}

final class SavedLayoutsFeatureDashboard extends DashboardDefinition
{
    public static function slug(): string
    {
        return 'saved-layouts-feature';
    }

    public static function label(): string
    {
        return 'Saved Layouts Feature Dashboard';
    }

    public static function widgets(): array
    {
        return [
            Widget::make('one')->label('One'),
            Widget::make('two')->label('Two'),
        ];
    }
}
