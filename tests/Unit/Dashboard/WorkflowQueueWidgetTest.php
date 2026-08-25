<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Workflow;
use SeviaFlow\Ui\Dashboard\Widgets\WorkflowQueueWidget;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('workflow_widget_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });
});

it('renders only configured workflow states with enforced limit', function (): void {
    config()->set('seviaflow.resources', [
        'workflow-widget' => WorkflowWidgetResource::class,
    ]);
    config()->set('seviaflow-ui.dashboard.workflow_widgets.max_limit', 2);

    WorkflowWidgetRecord::query()->create(['name' => 'A', 'status' => 'pending_review']);
    WorkflowWidgetRecord::query()->create(['name' => 'B', 'status' => 'pending_review']);
    WorkflowWidgetRecord::query()->create(['name' => 'C', 'status' => 'draft']);

    $widget = WorkflowQueueWidget::make('workflow_attention')
        ->resource(WorkflowWidgetResource::class)
        ->states(['pending_review'])
        ->limit(100);

    $rows = $widget->viewData()['rows'];
    $identifiers = array_map(static fn (array $row): string => (string) $row['identifier'], $rows);

    expect($rows)->toHaveCount(2)
        ->and($identifiers)->toBe(['A', 'B']);
});

it('fails clearly when configured state does not exist in workflow', function (): void {
    $widget = WorkflowQueueWidget::make('invalid_state')
        ->resource(WorkflowWidgetResource::class)
        ->states(['does_not_exist']);

    expect(fn () => $widget->viewData())
        ->toThrow(InvalidArgumentException::class, 'is not defined in resource workflow');
});

it('fails clearly for non workflow resources', function (): void {
    $widget = WorkflowQueueWidget::make('missing_workflow')
        ->resource(WorkflowWidgetNoWorkflowResource::class)
        ->states(['pending_review']);

    expect(fn () => $widget->viewData())
        ->toThrow(InvalidArgumentException::class, 'does not define workflow states');
});

it('requires authorization for visibility', function (): void {
    Gate::policy(WorkflowWidgetRecord::class, WorkflowWidgetDeniedPolicy::class);

    $widget = WorkflowQueueWidget::make('secure')
        ->resource(WorkflowWidgetResource::class)
        ->states(['pending_review']);

    $user = WorkflowWidgetUser::query()->create(['name' => 'Denied']);

    expect($widget->isVisible($user))->toBeFalse();
});

it('uses user scoped cache to avoid workflow queue leakage', function (): void {
    config()->set('seviaflow.resources', [
        'workflow-widget' => WorkflowWidgetResource::class,
    ]);
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow.performance.cache.dashboard', true);
    config()->set('seviaflow.performance.cache.store', 'array');
    flushArrayCache();
    Gate::policy(WorkflowWidgetRecord::class, WorkflowWidgetPerUserPolicy::class);

    WorkflowWidgetRecord::query()->create(['name' => 'Open', 'status' => 'pending_review']);
    WorkflowWidgetRecord::query()->create(['name' => 'Closed', 'status' => 'published']);

    $widget = WorkflowQueueWidget::make('workflow_attention')
        ->resource(WorkflowWidgetResource::class)
        ->states(['pending_review', 'published'])
        ->cacheFor(60)
        ->limit(10);

    $userA = WorkflowWidgetUser::query()->create(['name' => 'A']);
    $userB = WorkflowWidgetUser::query()->create(['name' => 'B']);

    $aRows = $widget->viewData($userA, 'ops')['rows'];
    $aRowsAgain = $widget->viewData($userA, 'ops')['rows'];
    $bRows = $widget->viewData($userB, 'ops')['rows'];

    expect(count($aRows))->toBe(1)
        ->and(($aRows[0]['identifier'] ?? null))->toBe('Open')
        ->and(count($aRowsAgain))->toBe(1)
        ->and(count($bRows))->toBe(1)
        ->and(($bRows[0]['identifier'] ?? null))->toBe('Closed');
});

/**
 * @property string $name
 */
final class WorkflowWidgetUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}

/**
 * @property string|null $status
 */
final class WorkflowWidgetRecord extends Model
{
    protected $table = 'workflow_widget_records';

    protected $guarded = [];
}

final class WorkflowWidgetResource extends Resource
{
    protected static string $model = WorkflowWidgetRecord::class;

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

final class WorkflowWidgetNoWorkflowResource extends Resource
{
    protected static string $model = WorkflowWidgetRecord::class;
}

final class WorkflowWidgetDeniedPolicy
{
    public function viewAny(WorkflowWidgetUser $user): bool
    {
        return false;
    }
}

final class WorkflowWidgetPerUserPolicy
{
    public function viewAny(WorkflowWidgetUser $user): bool
    {
        return true;
    }

    public function view(WorkflowWidgetUser $user, WorkflowWidgetRecord $record): bool
    {
        return $user->name === 'A'
            ? $record->status === 'pending_review'
            : $record->status === 'published';
    }
}
