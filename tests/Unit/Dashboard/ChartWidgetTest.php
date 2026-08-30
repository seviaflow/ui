<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Ui\Dashboard\Widgets\ChartWidget;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

it('stores chart type', function (): void {
    $widget = ChartWidget::make('orders')->type('line');

    expect($widget->getType())->toBe('line');
});

it('rejects invalid chart type', function (): void {
    expect(fn () => ChartWidget::make('orders')->type('pie'))
        ->toThrow(InvalidArgumentException::class, 'not supported');
});

it('resolves static chart data', function (): void {
    $widget = ChartWidget::make('orders')
        ->data([
            'labels' => ['Draft', 'Paid'],
            'datasets' => [['label' => 'Orders', 'data' => [1, 2]]],
        ]);

    $resolved = $widget->viewData();

    expect($resolved['chartData']['labels'])->toBe(['Draft', 'Paid'])
        ->and($resolved['chartData']['datasets'])->toHaveCount(1);
});

it('resolves closure chart data', function (): void {
    $widget = ChartWidget::make('orders')->data(
        static fn (): array => [
            'labels' => ['A'],
            'datasets' => [['label' => 'X', 'data' => [5]]],
        ]
    );

    $resolved = $widget->viewData();

    expect($resolved['chartData']['labels'])->toBe(['A'])
        ->and($resolved['chartData']['datasets'][0]['data'])->toBe([5]);
});

it('reuses cached chart data when cacheFor is set', function (): void {
    flushArrayCache();
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow-ui.cache.dashboard', true);
    config()->set('seviaflow.performance.cache.store', 'array');

    $runs = 0;
    $widget = ChartWidget::make('cached_chart')
        ->cacheFor(60)
        ->data(static function () use (&$runs): array {
            $runs++;

            return [
                'labels' => ['A'],
                'datasets' => [['label' => 'Cached', 'data' => [10]]],
            ];
        });

    $first = $widget->resolveData(null, 'ops');
    $second = $widget->resolveData(null, 'ops');

    expect($first)->toBe($second)
        ->and($runs)->toBe(1);
});

it('uses user scoped cache keys for chart data', function (): void {
    flushArrayCache();
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow-ui.cache.dashboard', true);
    config()->set('seviaflow.performance.cache.store', 'array');

    $runs = 0;
    $widget = ChartWidget::make('user_chart')
        ->cacheFor(60)
        ->data(static function (?ChartWidgetUser $user) use (&$runs): array {
            $runs++;
            $base = (int) ($user?->getKey() ?? 0);

            return [
                'labels' => ['A'],
                'datasets' => [['label' => 'User', 'data' => [$base]]],
            ];
        });

    $userA = ChartWidgetUser::query()->create(['name' => 'A']);
    $userB = ChartWidgetUser::query()->create(['name' => 'B']);

    $a = $widget->resolveData($userA, 'ops');
    $aAgain = $widget->resolveData($userA, 'ops');
    $b = $widget->resolveData($userB, 'ops');

    expect($a['datasets'][0]['data'])->toBe([1])
        ->and($aAgain['datasets'][0]['data'])->toBe([1])
        ->and($b['datasets'][0]['data'])->toBe([2])
        ->and($runs)->toBe(2);
});

final class ChartWidgetUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}
