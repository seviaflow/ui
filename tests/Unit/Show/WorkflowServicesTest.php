<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Transition;
use SeviaFlow\Core\Resources\Workflows\Workflow;
use SeviaFlow\Ui\Show\Workflow\WorkflowPanelState;
use SeviaFlow\Ui\Show\Workflow\WorkflowTransitionRunner;

beforeEach(function (): void {
    Schema::create('workflow_service_orders', function (Blueprint $table): void {
        $table->id();
        $table->string('status')->default('draft');
        $table->string('number');
        $table->timestamps();
    });

    if (! Schema::hasTable('seviaflow_workflow_activities')) {
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
    }
});

it('workflow panel state is hidden when resource workflow is empty', function (): void {
    $record = WorkflowServiceOrder::query()->create(['number' => 'ORD-EMPTY', 'status' => 'draft']);

    $panel = (new WorkflowPanelState)->resolve(WorkflowNoStatesResource::class, $record, null);

    expect($panel['visible'])->toBeFalse()
        ->and($panel['availableTransitions'])->toBe([]);
});

it('workflow transition runner executes valid transition and returns success result', function (): void {
    $record = WorkflowServiceOrder::query()->create(['number' => 'ORD-OK', 'status' => 'draft']);

    $result = (new WorkflowTransitionRunner)->run(
        resourceClass: WorkflowServiceResource::class,
        record: $record,
        transitionName: 'submit',
        confirmed: false,
        comment: null,
        user: null
    );

    expect($result['statusMessage'])->toBe('Transition completed successfully.')
        ->and($result['errorMessage'])->toBeNull()
        ->and($result['refreshRecord'])->toBeTrue()
        ->and($result['clearComment'])->toBeTrue();

    expect(WorkflowServiceOrder::query()->find($record->getKey())?->status)->toBe('pending');
});

it('workflow transition runner enforces required comment', function (): void {
    $record = WorkflowServiceOrder::query()->create(['number' => 'ORD-COMMENT', 'status' => 'pending']);

    $result = (new WorkflowTransitionRunner)->run(
        resourceClass: WorkflowServiceResource::class,
        record: $record,
        transitionName: 'cancel',
        confirmed: false,
        comment: '',
        user: null
    );

    expect($result['errorMessage'])->toBe('This transition requires a comment.')
        ->and($result['statusMessage'])->toBeNull();
});

it('workflow panel state exposes transitions and timeline', function (): void {
    $record = WorkflowServiceOrder::query()->create(['number' => 'ORD-PANEL', 'status' => 'draft']);

    (new WorkflowTransitionRunner)->run(
        resourceClass: WorkflowServiceResource::class,
        record: $record,
        transitionName: 'submit',
        confirmed: false,
        comment: null,
        user: null
    );

    $record->refresh();

    $panel = (new WorkflowPanelState)->resolve(WorkflowServiceResource::class, $record, null);

    expect($panel['visible'])->toBeTrue()
        ->and($panel['currentStateLabel'])->toBe('Pending')
        ->and($panel['primaryTransition']?->name())->toBe('cancel')
        ->and($panel['blockedTransitions'])->toBe([])
        ->and($panel['activities']->count())->toBeGreaterThan(0);
});

/**
 * @property string $status
 */
final class WorkflowServiceOrder extends Model
{
    protected $table = 'workflow_service_orders';

    protected $guarded = [];
}

final class WorkflowServiceResource extends Resource
{
    protected static string $model = WorkflowServiceOrder::class;

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->initialState('draft')
            ->states([
                State::make('draft')->label('Draft')->color('gray'),
                State::make('pending')->label('Pending')->color('yellow'),
                State::make('cancelled')->label('Cancelled')->color('red'),
            ])
            ->transitions([
                Transition::make('submit')
                    ->from('draft')
                    ->to('pending')
                    ->label('Submit order'),
                Transition::make('cancel')
                    ->from('pending')
                    ->to('cancelled')
                    ->label('Cancel order')
                    ->requiresComment(),
            ]);
    }
}

final class WorkflowNoStatesResource extends Resource
{
    protected static string $model = WorkflowServiceOrder::class;
}
