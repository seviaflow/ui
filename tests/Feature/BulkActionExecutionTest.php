<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Core\Resources\BulkAction;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Ui\Livewire\ResourceIndex;

beforeEach(function (): void {
    config()->set('seviaflow.resources', [
        'bulk-users' => BulkActionUserResource::class,
    ]);

    Schema::create('bulk_action_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::create('bulk_action_others', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('can_run_bulk')->default(false);
        $table->timestamps();
    });

    Schema::create('seviaflow_saved_views', function (Blueprint $table): void {
        $table->id();
        $table->string('resource');
        $table->string('name');
        $table->string('user_type')->nullable();
        $table->string('user_id')->nullable();
        $table->string('visibility')->default('private');
        $table->json('state');
        $table->timestamps();
    });

    pest_test_case()->actingAs(BulkActionAuthUser::query()->create([
        'name' => 'Bulk Test User',
        'can_run_bulk' => false,
    ]), 'web');
});

it('renders bulk actions when resource defines them', function (): void {
    BulkActionUser::query()->create([
        'name' => 'Bulk Render',
        'is_active' => true,
    ]);

    pest_test_case()->get('/admin/resources/bulk-users')
        ->assertOk()
        ->assertSee('Deactivate selected')
        ->assertSee('Dangerous deactivate selected');
});

it('executes bulk action on selected records', function (): void {
    $first = BulkActionUser::query()->create(['name' => 'One', 'is_active' => true]);
    $second = BulkActionUser::query()->create(['name' => 'Two', 'is_active' => true]);

    Livewire::test(ResourceIndex::class, ['resource' => 'bulk-users'])
        ->set('selectedRecords', [(string) $first->getKey(), (string) $second->getKey()])
        ->call('executeBulkAction', 'deactivate-selected', true)
        ->assertSee('Selected users have been deactivated.')
        ->assertSet('selectedRecords', []);

    expect($first->fresh()?->is_active)->toBeFalse()
        ->and($second->fresh()?->is_active)->toBeFalse();
});

it('fails safely for unknown bulk action', function (): void {
    BulkActionUser::query()->create(['name' => 'Unknown', 'is_active' => true]);

    Livewire::test(ResourceIndex::class, ['resource' => 'bulk-users'])
        ->set('selectedRecords', ['1'])
        ->call('executeBulkAction', 'unknown-bulk-action', true)
        ->assertSee('The selected bulk action is not available.');
});

it('does not execute bulk action when no records are selected', function (): void {
    BulkActionUser::query()->create(['name' => 'None', 'is_active' => true]);

    Livewire::test(ResourceIndex::class, ['resource' => 'bulk-users'])
        ->call('executeBulkAction', 'deactivate-selected', true)
        ->assertSee('Select at least one record to run this bulk action.');
});

it('enforces bulk action max records', function (): void {
    $first = BulkActionUser::query()->create(['name' => 'Max One', 'is_active' => true]);
    $second = BulkActionUser::query()->create(['name' => 'Max Two', 'is_active' => true]);

    Livewire::test(ResourceIndex::class, ['resource' => 'bulk-users'])
        ->set('selectedRecords', [(string) $first->getKey(), (string) $second->getKey()])
        ->call('executeBulkAction', 'limited-deactivate', true)
        ->assertSee('This bulk action allows up to 1 selected records.');

    expect($first->fresh()?->is_active)->toBeTrue()
        ->and($second->fresh()?->is_active)->toBeTrue();
});

it('enforces config max selected records', function (): void {
    config()->set('seviaflow-ui.bulk_actions.max_selected', 1);

    $first = BulkActionUser::query()->create(['name' => 'Config One', 'is_active' => true]);
    $second = BulkActionUser::query()->create(['name' => 'Config Two', 'is_active' => true]);

    Livewire::test(ResourceIndex::class, ['resource' => 'bulk-users'])
        ->set('selectedRecords', [(string) $first->getKey(), (string) $second->getKey()])
        ->call('executeBulkAction', 'deactivate-selected', true)
        ->assertSee('You can select up to 1 records for bulk actions.');

    expect($first->fresh()?->is_active)->toBeTrue()
        ->and($second->fresh()?->is_active)->toBeTrue();
});

it('requires confirmation for dangerous bulk actions', function (): void {
    $first = BulkActionUser::query()->create(['name' => 'Danger One', 'is_active' => true]);
    $second = BulkActionUser::query()->create(['name' => 'Danger Two', 'is_active' => true]);

    Livewire::test(ResourceIndex::class, ['resource' => 'bulk-users'])
        ->set('selectedRecords', [(string) $first->getKey(), (string) $second->getKey()])
        ->call('executeBulkAction', 'dangerous-deactivate')
        ->assertSee('You are about to run this action on 2 records. Please confirm before continuing.')
        ->assertSee('Confirm dangerous bulk action')
        ->assertSee('sf-confirm-consequences', false)
        ->assertSee('Confirm deactivation')
        ->assertSee('Cancel');

    expect($first->fresh()?->is_active)->toBeTrue()
        ->and($second->fresh()?->is_active)->toBeTrue();
});

it('does not execute selected IDs from outside current page/resource scope', function (): void {
    $first = BulkActionUser::query()->create(['name' => 'Scoped One', 'is_active' => true]);
    $second = BulkActionUser::query()->create(['name' => 'Scoped Two', 'is_active' => true]);
    BulkActionOther::query()->create(['name' => 'Other Model']);

    Livewire::test(ResourceIndex::class, ['resource' => 'bulk-users'])
        ->set('selectedRecords', [(string) $first->getKey(), (string) $second->getKey(), '999999'])
        ->call('executeBulkAction', 'deactivate-selected', true)
        ->assertSee('Selected records are no longer available. Please refresh selection.');

    expect($first->fresh()?->is_active)->toBeTrue()
        ->and($second->fresh()?->is_active)->toBeTrue()
        ->and(BulkActionOther::query()->count())->toBe(1);
});

it('fails safely when selected records become stale or deleted', function (): void {
    $record = BulkActionUser::query()->create(['name' => 'Stale', 'is_active' => true]);

    $component = Livewire::test(ResourceIndex::class, ['resource' => 'bulk-users'])
        ->set('selectedRecords', [(string) $record->getKey()]);

    $record->delete();

    $component
        ->call('executeBulkAction', 'deactivate-selected', true)
        ->assertSee('Selected records are no longer available. Please refresh selection.');
});

it('blocks custom bulk action when authorization callback denies', function (): void {
    $first = BulkActionUser::query()->create(['name' => 'Auth One', 'is_active' => true]);
    $second = BulkActionUser::query()->create(['name' => 'Auth Two', 'is_active' => true]);

    Livewire::test(ResourceIndex::class, ['resource' => 'bulk-users'])
        ->set('selectedRecords', [(string) $first->getKey(), (string) $second->getKey()])
        ->call('executeBulkAction', 'authorized-deactivate', true)
        ->assertSee('You are not authorized to run this bulk action on the selected records.');

    expect($first->fresh()?->is_active)->toBeTrue()
        ->and($second->fresh()?->is_active)->toBeTrue();
});

it('executes custom bulk action when authorization callback allows', function (): void {
    $record = BulkActionUser::query()->create(['name' => 'Auth Allowed', 'is_active' => true]);

    pest_test_case()->actingAs(BulkActionAuthUser::query()->create(['name' => 'Allowed', 'can_run_bulk' => true]), 'web');

    Livewire::test(ResourceIndex::class, ['resource' => 'bulk-users'])
        ->set('selectedRecords', [(string) $record->getKey()])
        ->call('executeBulkAction', 'authorized-deactivate', true)
        ->assertSee('Authorized bulk action completed.');

    expect($record->fresh()?->is_active)->toBeFalse();
});

final class BulkActionUserResource extends Resource
{
    protected static string $model = BulkActionUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
            TextColumn::make('is_active')->label('Active'),
        ]);
    }

    public static function bulkActions(): array
    {
        return [
            BulkAction::make('deactivate-selected')
                ->label('Deactivate selected')
                ->successMessage('Selected users have been deactivated.')
                ->handle(function (Collection $records): void {
                    $records->each(function (BulkActionUser $record): void {
                        $record->is_active = false;
                        $record->save();
                    });
                }),
            BulkAction::make('limited-deactivate')
                ->label('Limited deactivate selected')
                ->maxRecords(1)
                ->handle(function (Collection $records): void {
                    $records->each(function (BulkActionUser $record): void {
                        $record->is_active = false;
                        $record->save();
                    });
                }),
            BulkAction::make('dangerous-deactivate')
                ->label('Dangerous deactivate selected')
                ->danger()
                ->confirmButton('Confirm deactivation')
                ->cancelButton('Cancel')
                ->handle(function (Collection $records): void {
                    $records->each(function (BulkActionUser $record): void {
                        $record->is_active = false;
                        $record->save();
                    });
                }),
            BulkAction::make('authorized-deactivate')
                ->label('Authorized deactivate selected')
                ->successMessage('Authorized bulk action completed.')
                ->authorize(fn (?BulkActionAuthUser $user, Collection $records): bool => (bool) ($user?->can_run_bulk) && $records->count() <= 1)
                ->handle(function (Collection $records): void {
                    $records->each(function (BulkActionUser $record): void {
                        $record->is_active = false;
                        $record->save();
                    });
                }),
        ];
    }
}

/**
 * @property bool $is_active
 */
final class BulkActionUser extends Model
{
    protected $table = 'bulk_action_users';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'bool',
    ];
}

final class BulkActionOther extends Model
{
    protected $table = 'bulk_action_others';

    protected $guarded = [];
}

/**
 * @property bool $can_run_bulk
 */
final class BulkActionAuthUser extends User
{
    protected $table = 'users';

    protected $guarded = [];
}
