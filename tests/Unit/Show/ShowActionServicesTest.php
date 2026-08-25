<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Action;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Ui\Show\Actions\ShowActionResolver;
use SeviaFlow\Ui\Show\Actions\ShowActionRunner;

beforeEach(function (): void {
    config()->set('seviaflow.resources', [
        'show-action-test-users' => ShowActionTestUserResource::class,
    ]);

    Schema::create('show_action_test_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });
});

it('show action resolver returns actions and resolves by name safely', function (): void {
    $resolver = new ShowActionResolver;

    $actions = $resolver->actions(ShowActionTestUserResource::class);

    expect($actions)->toHaveCount(3)
        ->and($resolver->resolve(ShowActionTestUserResource::class, 'activate'))->toBeInstanceOf(Action::class)
        ->and($resolver->resolve(ShowActionTestUserResource::class, 'unknown'))->toBeNull();
});

it('show action runner executes action and returns success result', function (): void {
    $user = ShowActionTestUser::query()->create(['name' => 'Action user', 'is_active' => false]);

    $result = (new ShowActionRunner)->run(
        resourceClass: ShowActionTestUserResource::class,
        record: $user,
        actionName: 'activate',
        confirmed: true,
        user: null
    );

    expect($result['statusMessage'])->toBe('Account activated successfully.')
        ->and($result['errorMessage'])->toBeNull()
        ->and($result['refreshRecord'])->toBeTrue();

    expect(ShowActionTestUser::query()->find($user->getKey())?->is_active)->toBeTrue();
});

it('show action runner requires confirmation for danger action', function (): void {
    $user = ShowActionTestUser::query()->create(['name' => 'Danger user', 'is_active' => true]);

    $result = (new ShowActionRunner)->run(
        resourceClass: ShowActionTestUserResource::class,
        record: $user,
        actionName: 'deactivate',
        confirmed: false,
        user: null
    );

    expect($result['errorMessage'])->toBe('Please confirm this action before continuing.')
        ->and($result['pendingActionName'])->toBe('deactivate');
});

it('show action runner fails safely for missing action', function (): void {
    $user = ShowActionTestUser::query()->create(['name' => 'Missing action', 'is_active' => true]);

    $result = (new ShowActionRunner)->run(
        resourceClass: ShowActionTestUserResource::class,
        record: $user,
        actionName: 'missing',
        confirmed: true,
        user: null
    );

    expect($result['errorMessage'])->toBe('The selected action is not available.');
});

it('show action runner returns safe error message when handler fails', function (): void {
    $user = ShowActionTestUser::query()->create(['name' => 'Fail user', 'is_active' => true]);

    $result = (new ShowActionRunner)->run(
        resourceClass: ShowActionTestUserResource::class,
        record: $user,
        actionName: 'fail-action',
        confirmed: true,
        user: null
    );

    expect($result['errorMessage'])->toBe('Action failed. Please try again.')
        ->and($result['statusMessage'])->toBeNull();
});

final class ShowActionTestUserResource extends Resource
{
    protected static string $model = ShowActionTestUser::class;

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function actions(): array
    {
        return [
            Action::make('activate')
                ->successMessage('Account activated successfully.')
                ->handle(function (ShowActionTestUser $record): void {
                    $record->forceFill(['is_active' => true])->save();
                }),
            Action::make('deactivate')
                ->danger()
                ->requiresConfirmation()
                ->handle(function (ShowActionTestUser $record): void {
                    $record->forceFill(['is_active' => false])->save();
                }),
            Action::make('fail-action')
                ->errorMessage('Action failed. Please try again.')
                ->handle(function (): void {
                    throw new RuntimeException('fail');
                }),
        ];
    }
}

/**
 * @property bool $is_active
 */
final class ShowActionTestUser extends Model
{
    protected $table = 'show_action_test_users';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
