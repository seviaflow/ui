<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Analytics\AnalyticsDataset;
use SeviaFlow\Core\Analytics\AnalyticsFilter;
use SeviaFlow\Core\Analytics\AnalyticsQuery;
use SeviaFlow\Core\Analytics\AnalyticsRegistry;
use SeviaFlow\Core\Analytics\Dimension;
use SeviaFlow\Core\Analytics\Measure;
use SeviaFlow\Core\Analytics\TimeDimension;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Ui\Dashboard\Widgets\AnalyticsChartWidget;
use SeviaFlow\Ui\Dashboard\Widgets\AnalyticsMetricWidget;
use SeviaFlow\Ui\Dashboard\Widgets\AnalyticsTableWidget;

beforeEach(function (): void {
    Schema::create('analytics_widget_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('analytics_widget_orders', function (Blueprint $table): void {
        $table->id();
        $table->string('status');
        $table->decimal('total', 12, 2);
        $table->dateTime('ordered_at');
        $table->timestamps();
    });

    config()->set('seviaflow.resources', [
        'analytics-widget-orders' => AnalyticsWidgetOrderResource::class,
    ]);

    app(AnalyticsRegistry::class)->register(analyticsWidgetDataset());

    AnalyticsWidgetOrder::query()->create([
        'status' => 'completed',
        'total' => 100,
        'ordered_at' => '2026-01-10 10:00:00',
    ]);
    AnalyticsWidgetOrder::query()->create([
        'status' => 'shipped',
        'total' => 50,
        'ordered_at' => '2026-02-10 10:00:00',
    ]);
});

it('renders analytics metric values with money formatting', function (): void {
    $widget = AnalyticsMetricWidget::make('revenue')
        ->label('Revenue')
        ->query(AnalyticsQuery::make('orders')->measure('revenue'))
        ->measure('revenue')
        ->color('emerald');

    $data = $widget->viewData();

    expect($data['state'])->toBe('ready')
        ->and($data['value'])->toBe('EUR 150.00')
        ->and($data['valueRaw'])->toBe(150.0)
        ->and($data['color'])->toBe('emerald');
});

it('maps analytics time-series results to chart data', function (): void {
    $widget = AnalyticsChartWidget::make('revenue_over_time')
        ->label('Revenue over time')
        ->type('line')
        ->query(
            AnalyticsQuery::make('orders')
                ->measure('revenue')
                ->time('ordered_at', 'month', '2026-01-01', '2026-02-28')
                ->orderBy('ordered_at', 'asc')
        )
        ->measure('revenue');

    $data = $widget->viewData();

    expect($data['state'])->toBe('ready')
        ->and($data['chartType'])->toBe('line')
        ->and($data['chartData']['labels'])->toBe(['2026-01-01', '2026-02-01'])
        ->and($data['chartData']['datasets'][0]['data'])->toBe([100.0, 50.0]);
});

it('maps analytics dimension results to table rows', function (): void {
    $widget = AnalyticsTableWidget::make('orders_by_status')
        ->label('Orders by status')
        ->query(
            AnalyticsQuery::make('orders')
                ->measures(['orders', 'revenue'])
                ->dimension('status')
                ->orderBy('orders', 'desc')
        );

    $data = $widget->viewData();

    expect($data['state'])->toBe('ready')
        ->and($data['columns'])->toBe(['Status', 'Orders', 'Revenue'])
        ->and($data['rows'])->toContain(['Completed', '1', 'EUR 100.00'])
        ->and($data['rows'])->toContain(['Shipped', '1', 'EUR 50.00']);
});

it('exposes loading empty and error states without misleading zeroes', function (): void {
    $loading = AnalyticsMetricWidget::make('loading')
        ->query(AnalyticsQuery::make('orders')->measure('revenue'))
        ->loading()
        ->viewData();

    $empty = AnalyticsChartWidget::make('empty')
        ->query(
            AnalyticsQuery::make('orders')
                ->measure('revenue')
                ->dimension('status')
                ->filter(AnalyticsFilter::equals('status', 'missing'))
        )
        ->viewData();

    $error = AnalyticsMetricWidget::make('error')
        ->query(AnalyticsQuery::make('missing')->measure('revenue'))
        ->viewData();

    expect($loading['state'])->toBe('loading')
        ->and($loading['valueRaw'])->toBeNull()
        ->and($empty['state'])->toBe('empty')
        ->and($empty['chartData']['datasets'])->toBe([])
        ->and($error['state'])->toBe('error')
        ->and($error['valueRaw'])->toBeNull();
});

it('uses server-side analytics authorization for widget visibility and direct rendering', function (): void {
    app(AnalyticsRegistry::class)->register(analyticsRestrictedWidgetDataset());

    $allowed = AnalyticsWidgetUser::query()->create(['name' => 'Allowed']);
    $denied = AnalyticsWidgetUser::query()->create(['name' => 'Denied']);
    $widget = AnalyticsMetricWidget::make('restricted')
        ->query(AnalyticsQuery::make('restricted-orders')->measure('revenue'));

    expect($widget->isVisible($allowed))->toBeTrue()
        ->and($widget->isVisible($denied))->toBeFalse()
        ->and($widget->viewData($denied)['state'])->toBe('error');
});

it('renders analytics chart and table views with responsive dashboard markup', function (): void {
    $chart = AnalyticsChartWidget::make('chart')
        ->label('Revenue over time')
        ->query(
            AnalyticsQuery::make('orders')
                ->measure('revenue')
                ->time('ordered_at', 'month', '2026-01-01', '2026-02-28')
        );
    $table = AnalyticsTableWidget::make('table')
        ->label('Orders by status')
        ->query(
            AnalyticsQuery::make('orders')
                ->measure('orders')
                ->dimension('status')
        );

    $chartHtml = view($chart->view(), array_merge($chart->viewData(), [
        'widget' => ['label' => $chart->getLabel(), 'description' => $chart->getDescription()],
    ]))->render();
    $tableHtml = view($table->view(), array_merge($table->viewData(), [
        'widget' => ['label' => $table->getLabel(), 'description' => $table->getDescription()],
    ]))->render();

    expect($chartHtml)->toContain('sf-dashboard-chart-frame')
        ->and($chartHtml)->toContain('sf-dashboard-chart-canvas')
        ->and($chartHtml)->toContain('data-sf-chart="1"')
        ->and($tableHtml)->toContain('sf-table-wrapper')
        ->and($tableHtml)->toContain('sf-table !min-w-full');
});

function analyticsWidgetDataset(): AnalyticsDataset
{
    return AnalyticsDataset::make('orders')
        ->resource(AnalyticsWidgetOrderResource::class)
        ->measures([
            Measure::make('orders')->count(),
            Measure::make('revenue')->sum('total')->currency('EUR'),
        ])
        ->dimensions([
            Dimension::make('status')
                ->field('status')
                ->labels([
                    'completed' => 'Completed',
                    'shipped' => 'Shipped',
                ]),
        ])
        ->timeDimensions([
            TimeDimension::make('ordered_at')->field('ordered_at'),
        ]);
}

function analyticsRestrictedWidgetDataset(): AnalyticsDataset
{
    return AnalyticsDataset::make('restricted-orders')
        ->resource(AnalyticsWidgetOrderResource::class)
        ->authorize(static fn (?AnalyticsWidgetUser $user): bool => $user?->getAttribute('name') === 'Allowed')
        ->measures([
            Measure::make('revenue')->sum('total')->currency('EUR'),
        ]);
}

final class AnalyticsWidgetUser extends AuthenticatableUser
{
    protected $table = 'analytics_widget_users';

    protected $guarded = [];
}

final class AnalyticsWidgetOrder extends Model
{
    protected $table = 'analytics_widget_orders';

    protected $guarded = [];
}

final class AnalyticsWidgetOrderResource extends Resource
{
    protected static string $model = AnalyticsWidgetOrder::class;

    public static function query(Builder $query): Builder
    {
        return $query;
    }
}
