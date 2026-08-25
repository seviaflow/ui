<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Workflow;
use SeviaFlow\Ui\Dashboard\Widgets\RecentActivityWidget;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Schema::create('recent_widget_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

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
});

it('renders recent activity items with enforced limit', function (): void {
    config()->set('seviaflow.resources', [
        'recent-widget' => RecentWidgetResource::class,
    ]);
    config()->set('seviaflow-ui.dashboard.recent_activity_widgets.max_limit', 2);

    $user = RecentWidgetUser::query()->create([
        'name' => 'Activity User',
        'email' => 'activity@example.com',
    ]);

    for ($i = 1; $i <= 3; $i++) {
        DB::table('seviaflow_workflow_activities')->insert([
            'resource' => RecentWidgetResource::class,
            'record_type' => RecentWidgetRecord::class,
            'record_id' => (string) $i,
            'transition' => 'approve',
            'from_state' => 'draft',
            'to_state' => 'published',
            'user_type' => RecentWidgetUser::class,
            'user_id' => (string) $user->getKey(),
            'comment' => 'Comment '.$i,
            'created_at' => Carbon::parse('2026-01-05 10:0'.$i.':00'),
            'updated_at' => Carbon::parse('2026-01-05 10:0'.$i.':00'),
        ]);
    }

    $widget = RecentActivityWidget::make('recent_activity')->limit(100);

    $items = $widget->viewData($user)['items'];

    expect($items)->toHaveCount(2)
        ->and($items[0]['recordIdentifier'])->toBe('RecentWidgetRecord #3')
        ->and($items[1]['recordIdentifier'])->toBe('RecentWidgetRecord #2');
});

it('supports resource scoping', function (): void {
    config()->set('seviaflow.resources', [
        'recent-widget' => RecentWidgetResource::class,
        'recent-widget-other' => RecentOtherWidgetResource::class,
    ]);

    $user = RecentWidgetUser::query()->create(['name' => 'Scoped']);

    DB::table('seviaflow_workflow_activities')->insert([
        [
            'resource' => RecentWidgetResource::class,
            'record_type' => RecentWidgetRecord::class,
            'record_id' => '10',
            'transition' => 'approve',
            'from_state' => 'draft',
            'to_state' => 'published',
            'user_type' => RecentWidgetUser::class,
            'user_id' => (string) $user->getKey(),
            'comment' => null,
            'created_at' => Carbon::parse('2026-01-05 10:00:00'),
            'updated_at' => Carbon::parse('2026-01-05 10:00:00'),
        ],
        [
            'resource' => RecentOtherWidgetResource::class,
            'record_type' => RecentWidgetRecord::class,
            'record_id' => '20',
            'transition' => 'approve',
            'from_state' => 'draft',
            'to_state' => 'published',
            'user_type' => RecentWidgetUser::class,
            'user_id' => (string) $user->getKey(),
            'comment' => null,
            'created_at' => Carbon::parse('2026-01-05 10:10:00'),
            'updated_at' => Carbon::parse('2026-01-05 10:10:00'),
        ],
    ]);

    $widget = RecentActivityWidget::make('recent_activity')
        ->resources([RecentWidgetResource::class])
        ->limit(10);

    $items = $widget->viewData($user)['items'];

    expect($items)->toHaveCount(1)
        ->and($items[0]['recordIdentifier'])->toBe('RecentWidgetRecord #10');
});

it('rejects invalid resources configuration', function (): void {
    /** @var array<int, class-string<resource>> $resources */
    $resources = ['not-a-resource'];

    expect(fn () => RecentActivityWidget::make('recent')->resources($resources))
        ->toThrow(InvalidArgumentException::class, 'must contain only Resource class strings');
});

/**
 * @property string $name
 */
final class RecentWidgetUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}

/**
 * @property string $name
 * @property string $status
 */
final class RecentWidgetRecord extends Model
{
    protected $table = 'recent_widget_records';

    protected $guarded = [];
}

final class RecentWidgetResource extends Resource
{
    protected static string $model = RecentWidgetRecord::class;

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->states([
                State::make('draft')->label('Draft'),
                State::make('published')->label('Published')->final(),
            ]);
    }
}

final class RecentOtherWidgetResource extends Resource
{
    protected static string $model = RecentWidgetRecord::class;

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->states([
                State::make('draft')->label('Draft'),
                State::make('published')->label('Published')->final(),
            ]);
    }
}
