<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Ui\Dashboard\Widgets\TableWidget;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('table_widget_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('status')->nullable();
        $table->timestamps();
    });
});

it('enforces default and configured limit safely', function (): void {
    config()->set('seviaflow.resources', [
        'table-widget' => TableWidgetResource::class,
    ]);
    config()->set('seviaflow-ui.dashboard.table_widgets.max_limit', 10);

    for ($i = 1; $i <= 40; $i++) {
        TableWidgetRecord::query()->create([
            'name' => 'Row '.$i,
            'status' => $i % 2 === 0 ? 'open' : 'closed',
        ]);
    }

    $widget = TableWidget::make('recent')
        ->resource(TableWidgetResource::class)
        ->columns([TextColumn::make('name')])
        ->limit(100);

    $rows = $widget->viewData()['rows'];

    expect($rows)->toHaveCount(10);
});

it('applies query callback before limiting', function (): void {
    config()->set('seviaflow.resources', [
        'table-widget' => TableWidgetResource::class,
    ]);

    TableWidgetRecord::query()->create(['name' => 'A', 'status' => 'open']);
    TableWidgetRecord::query()->create(['name' => 'B', 'status' => 'closed']);
    TableWidgetRecord::query()->create(['name' => 'C', 'status' => 'open']);

    $widget = TableWidget::make('open_only')
        ->resource(TableWidgetResource::class)
        ->columns([TextColumn::make('name')])
        ->query(static fn (Builder $query): Builder => $query->where('status', 'open'))
        ->limit(5);

    $rows = $widget->viewData()['rows'];
    $names = array_map(static fn (array $row): string => strip_tags((string) ($row['cells'][0] ?? '')), $rows);

    expect($rows)->toHaveCount(2)
        ->and($names)->toBe(['A', 'C']);
});

it('requires explicit columns', function (): void {
    config()->set('seviaflow.resources', [
        'table-widget' => TableWidgetResource::class,
    ]);

    $widget = TableWidget::make('invalid')->resource(TableWidgetResource::class);

    expect(fn () => $widget->viewData())
        ->toThrow(InvalidArgumentException::class, 'explicit columns');
});

it('requires authorization for visibility', function (): void {
    config()->set('seviaflow.resources', [
        'table-widget' => TableWidgetResource::class,
    ]);
    Gate::policy(TableWidgetRecord::class, TableWidgetDeniedPolicy::class);

    $widget = TableWidget::make('secure')
        ->resource(TableWidgetResource::class)
        ->columns([TextColumn::make('name')]);

    $user = TableWidgetUser::query()->create(['name' => 'Denied']);

    expect($widget->isVisible($user))->toBeFalse();
});

it('reuses cached table rows and scopes cache by user', function (): void {
    config()->set('seviaflow.resources', [
        'table-widget' => TableWidgetResource::class,
    ]);
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow.performance.cache.dashboard', true);
    config()->set('seviaflow.performance.cache.store', 'array');
    flushArrayCache();

    TableWidgetRecord::query()->create(['name' => 'A', 'status' => 'open']);
    TableWidgetRecord::query()->create(['name' => 'B', 'status' => 'closed']);
    TableWidgetRecord::query()->create(['name' => 'C', 'status' => 'open']);

    $runs = 0;
    $widget = TableWidget::make('cached_table')
        ->resource(TableWidgetResource::class)
        ->columns([TextColumn::make('name')])
        ->cacheFor(60)
        ->query(static function (Builder $query, ?AuthenticatableUser $user) use (&$runs): Builder {
            $runs++;

            if ($user instanceof TableWidgetUser && $user->name === 'A') {
                return $query->where('status', 'open');
            }

            return $query->where('status', 'closed');
        });

    $userA = TableWidgetUser::query()->create(['name' => 'A']);
    $userB = TableWidgetUser::query()->create(['name' => 'B']);

    $aFirst = $widget->viewData($userA, 'ops')['rows'];
    $aSecond = $widget->viewData($userA, 'ops')['rows'];
    $bFirst = $widget->viewData($userB, 'ops')['rows'];

    expect($runs)->toBe(2)
        ->and(count($aFirst))->toBe(2)
        ->and(count($aSecond))->toBe(2)
        ->and(count($bFirst))->toBe(1);
});

/**
 * @property string $name
 */
final class TableWidgetUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}

final class TableWidgetRecord extends Model
{
    protected $table = 'table_widget_records';

    protected $guarded = [];
}

final class TableWidgetResource extends Resource
{
    protected static string $model = TableWidgetRecord::class;
}

final class TableWidgetDeniedPolicy
{
    public function viewAny(TableWidgetUser $user): bool
    {
        return false;
    }
}
