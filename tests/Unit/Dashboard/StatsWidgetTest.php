<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Ui\Dashboard\Widgets\StatsWidget;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

it('renders static value through view data', function (): void {
    $widget = StatsWidget::make('pending')->value(12);

    expect($widget->viewData()['value'])->toBe(12);
});

it('renders closure value through view data', function (): void {
    $widget = StatsWidget::make('pending')->value(static fn (): int => 8);

    expect($widget->viewData()['value'])->toBe(8);
});

it('stores and exposes label description and color', function (): void {
    $widget = StatsWidget::make('failed_imports')
        ->label('Failed imports')
        ->description('Rows that could not be imported')
        ->color('amber');

    expect($widget->getLabel())->toBe('Failed imports')
        ->and($widget->getDescription())->toBe('Rows that could not be imported')
        ->and($widget->viewData()['color'])->toBe('amber');
});

it('rejects invalid color safely', function (): void {
    expect(fn () => StatsWidget::make('x')->color('dangerous-class'))
        ->toThrow(InvalidArgumentException::class, 'not allowed');
});

it('reuses cached value when cacheFor is set', function (): void {
    flushArrayCache();
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow-ui.cache.dashboard', true);
    config()->set('seviaflow.performance.cache.store', 'array');

    $runs = 0;
    $widget = StatsWidget::make('cached_metric')
        ->cacheFor(60)
        ->value(static function () use (&$runs): int {
            $runs++;

            return 123;
        });

    $first = $widget->resolveValue(null, 'ops');
    $second = $widget->resolveValue(null, 'ops');

    expect($first)->toBe(123)
        ->and($second)->toBe(123)
        ->and($runs)->toBe(1);
});

it('uses user scoped cache keys to prevent leakage between users', function (): void {
    flushArrayCache();
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow-ui.cache.dashboard', true);
    config()->set('seviaflow.performance.cache.store', 'array');

    $runs = 0;
    $widget = StatsWidget::make('user_scoped_metric')
        ->cacheFor(60)
        ->value(static function (?AuthenticatableUser $user) use (&$runs): int {
            $runs++;

            return (int) ($user?->getKey() ?? 0) * 10;
        });

    $userA = StatsWidgetUser::query()->create(['name' => 'A']);
    $userB = StatsWidgetUser::query()->create(['name' => 'B']);

    $aFirst = $widget->resolveValue($userA, 'ops');
    $aSecond = $widget->resolveValue($userA, 'ops');
    $bFirst = $widget->resolveValue($userB, 'ops');

    expect($aFirst)->toBe(10)
        ->and($aSecond)->toBe(10)
        ->and($bFirst)->toBe(20)
        ->and($runs)->toBe(2);
});

final class StatsWidgetUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}
