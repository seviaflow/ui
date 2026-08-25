<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceEvents;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Ui\Livewire\ResourceIndex;

beforeEach(function (): void {
    config()->set('seviaflow.resources', [
        'soft-bulk-users' => SoftBulkUserResource::class,
        'soft-bulk-event-users' => SoftBulkEventUserResource::class,
        'hard-bulk-users' => HardBulkUserResource::class,
    ]);

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('can_view_any')->default(true);
        $table->boolean('can_view')->default(true);
        $table->boolean('can_update')->default(true);
        $table->boolean('can_delete')->default(true);
        $table->boolean('can_restore')->default(true);
        $table->timestamps();
    });

    Schema::create('soft_bulk_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('hard_bulk_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
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

    Gate::policy(SoftBulkUser::class, SoftBulkUserPolicy::class);
    pest_test_case()->actingAs(BulkPolicyUser::query()->create([
        'name' => 'Bulk Allowed User',
        'can_view_any' => true,
        'can_view' => true,
        'can_update' => true,
        'can_delete' => true,
        'can_restore' => true,
    ]), 'web');
    SoftBulkEventTracker::reset();
});

it('soft-deletes selected records with built-in bulk delete', function (): void {
    $first = SoftBulkUser::query()->create(['name' => 'Soft First']);
    $second = SoftBulkUser::query()->create(['name' => 'Soft Second']);

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-bulk-users'])
        ->set('selectedRecords', [(string) $first->getKey(), (string) $second->getKey()])
        ->call('executeBulkAction', '__built_in_bulk_delete', true)
        ->assertSee('Selected records were moved to trash.');

    expect(SoftBulkUser::withTrashed()->find($first->getKey())?->trashed())->toBeTrue()
        ->and(SoftBulkUser::withTrashed()->find($second->getKey())?->trashed())->toBeTrue();
});

it('built-in bulk delete does not force delete soft-deletable records', function (): void {
    $record = SoftBulkUser::query()->create(['name' => 'No Force Delete']);

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-bulk-users'])
        ->set('selectedRecords', [(string) $record->getKey()])
        ->call('executeBulkAction', '__built_in_bulk_delete', true);

    expect(SoftBulkUser::withTrashed()->find($record->getKey()))->not->toBeNull();
});

it('restores trashed records with built-in bulk restore', function (): void {
    $record = SoftBulkUser::query()->create(['name' => 'Restore Me']);
    $record->delete();

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-bulk-users'])
        ->set('trashedScope', 'trashed')
        ->set('selectedRecords', [(string) $record->getKey()])
        ->call('executeBulkAction', '__built_in_bulk_restore', true)
        ->assertSee('Selected records were restored.');

    expect(SoftBulkUser::withTrashed()->find($record->getKey())?->trashed())->toBeFalse();
});

it('does not expose bulk restore for non-soft-deletable resources', function (): void {
    HardBulkUser::query()->create(['name' => 'Hard One']);

    pest_test_case()->get('/admin/resources/hard-bulk-users')
        ->assertOk()
        ->assertDontSee('Restore selected');
});

it('requires confirmation for built-in bulk delete', function (): void {
    $record = SoftBulkUser::query()->create(['name' => 'Confirm Delete']);

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-bulk-users'])
        ->set('selectedRecords', [(string) $record->getKey()])
        ->call('executeBulkAction', '__built_in_bulk_delete')
        ->assertSee('You are about to run this action on 1 records. Please confirm before continuing.');

    expect(SoftBulkUser::withTrashed()->find($record->getKey())?->trashed())->toBeFalse();
});

it('blocks whole built-in bulk delete when policy denies a selected record', function (): void {
    $allowed = SoftBulkUser::query()->create(['name' => 'Allowed']);
    $denied = SoftBulkUser::query()->create(['name' => 'Denied']);
    $user = BulkPolicyUser::query()->create([
        'name' => 'No Delete',
        'can_view_any' => true,
        'can_view' => true,
        'can_update' => true,
        'can_delete' => false,
        'can_restore' => true,
    ]);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-bulk-users'])
        ->set('selectedRecords', [(string) $allowed->getKey(), (string) $denied->getKey()])
        ->call('executeBulkAction', '__built_in_bulk_delete', true)
        ->assertSee('You are not authorized to delete one or more selected records.');

    expect(SoftBulkUser::withTrashed()->find($allowed->getKey())?->trashed())->toBeFalse()
        ->and(SoftBulkUser::withTrashed()->find($denied->getKey())?->trashed())->toBeFalse();
});

it('blocks whole built-in bulk restore when policy denies restore', function (): void {
    $record = SoftBulkUser::query()->create(['name' => 'Denied Restore']);
    $record->delete();
    $user = BulkPolicyUser::query()->create([
        'name' => 'No Restore',
        'can_view_any' => true,
        'can_view' => true,
        'can_update' => true,
        'can_delete' => true,
        'can_restore' => false,
    ]);

    pest_test_case()->actingAs($user, 'web');

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-bulk-users'])
        ->set('trashedScope', 'trashed')
        ->set('selectedRecords', [(string) $record->getKey()])
        ->call('executeBulkAction', '__built_in_bulk_restore', true)
        ->assertSee('You are not authorized to restore one or more selected records.');

    expect(SoftBulkUser::withTrashed()->find($record->getKey())?->trashed())->toBeTrue();
});

it('respects max selected limit for built-in bulk delete', function (): void {
    config()->set('seviaflow-ui.bulk_actions.max_selected', 1);
    $first = SoftBulkUser::query()->create(['name' => 'Limit One']);
    $second = SoftBulkUser::query()->create(['name' => 'Limit Two']);

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-bulk-users'])
        ->set('selectedRecords', [(string) $first->getKey(), (string) $second->getKey()])
        ->call('executeBulkAction', '__built_in_bulk_delete', true)
        ->assertSee('You can select up to 1 records for bulk actions.');
});

it('bulk delete fires deleting and deleted per record in order', function (): void {
    $first = SoftBulkUser::query()->create(['name' => 'Event One']);
    $second = SoftBulkUser::query()->create(['name' => 'Event Two']);

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-bulk-event-users'])
        ->set('selectedRecords', [(string) $first->getKey(), (string) $second->getKey()])
        ->call('executeBulkAction', '__built_in_bulk_delete', true)
        ->assertSee('Selected records were moved to trash.');

    expect(SoftBulkEventTracker::$events)->toHaveCount(4)
        ->and(SoftBulkEventTracker::$events[0]['event'])->toBe('deleting')
        ->and(SoftBulkEventTracker::$events[1]['event'])->toBe('deleted')
        ->and(SoftBulkEventTracker::$events[2]['event'])->toBe('deleting')
        ->and(SoftBulkEventTracker::$events[3]['event'])->toBe('deleted')
        ->and(SoftBulkEventTracker::$events[0]['source'])->toBe('bulk_action');
});

it('bulk restore fires restoring and restored per record in order', function (): void {
    $first = SoftBulkUser::query()->create(['name' => 'Restore One']);
    $second = SoftBulkUser::query()->create(['name' => 'Restore Two']);
    $first->delete();
    $second->delete();

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-bulk-event-users'])
        ->set('trashedScope', 'trashed')
        ->set('selectedRecords', [(string) $first->getKey(), (string) $second->getKey()])
        ->call('executeBulkAction', '__built_in_bulk_restore', true)
        ->assertSee('Selected records were restored.');

    expect(SoftBulkEventTracker::$events)->toHaveCount(4)
        ->and(SoftBulkEventTracker::$events[0]['event'])->toBe('restoring')
        ->and(SoftBulkEventTracker::$events[1]['event'])->toBe('restored')
        ->and(SoftBulkEventTracker::$events[2]['event'])->toBe('restoring')
        ->and(SoftBulkEventTracker::$events[3]['event'])->toBe('restored')
        ->and(SoftBulkEventTracker::$events[0]['source'])->toBe('bulk_action');
});

it('bulk deleting handler failure rolls back whole operation with safe error', function (): void {
    $first = SoftBulkUser::query()->create(['name' => 'Blocked One']);
    $second = SoftBulkUser::query()->create(['name' => 'Blocked Two']);
    SoftBulkEventTracker::$throwOnDeleting = true;

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-bulk-event-users'])
        ->set('selectedRecords', [(string) $first->getKey(), (string) $second->getKey()])
        ->call('executeBulkAction', '__built_in_bulk_delete', true)
        ->assertSee('Bulk delete blocked by event.');

    expect(SoftBulkUser::withTrashed()->find($first->getKey())?->trashed())->toBeFalse()
        ->and(SoftBulkUser::withTrashed()->find($second->getKey())?->trashed())->toBeFalse();
});

it('bulk restoring handler failure rolls back whole restore with safe error', function (): void {
    $first = SoftBulkUser::query()->create(['name' => 'Blocked Restore One']);
    $second = SoftBulkUser::query()->create(['name' => 'Blocked Restore Two']);
    $first->delete();
    $second->delete();
    SoftBulkEventTracker::$throwOnRestoring = true;

    Livewire::test(ResourceIndex::class, ['resource' => 'soft-bulk-event-users'])
        ->set('trashedScope', 'trashed')
        ->set('selectedRecords', [(string) $first->getKey(), (string) $second->getKey()])
        ->call('executeBulkAction', '__built_in_bulk_restore', true)
        ->assertSee('Bulk restore blocked by event.');

    expect(SoftBulkUser::withTrashed()->find($first->getKey())?->trashed())->toBeTrue()
        ->and(SoftBulkUser::withTrashed()->find($second->getKey())?->trashed())->toBeTrue();
});

final class SoftBulkUserResource extends Resource
{
    protected static string $model = SoftBulkUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }
}

final class HardBulkUserResource extends Resource
{
    protected static string $model = HardBulkUser::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name'),
        ]);
    }
}

final class SoftBulkEventUserResource extends Resource
{
    protected static string $model = SoftBulkUser::class;

    public static function table(Table $table): Table
    {
        return SoftBulkUserResource::table($table);
    }

    public static function events(ResourceEvents $events): ResourceEvents
    {
        return $events
            ->onDeleting(function (SoftBulkUser $record, ResourceEventContext $context): void {
                SoftBulkEventTracker::$events[] = [
                    'event' => 'deleting',
                    'source' => $context->source(),
                    'operation' => $context->operation(),
                ];

                if (SoftBulkEventTracker::$throwOnDeleting) {
                    throw new LogicException('Bulk delete blocked by event.');
                }
            })
            ->onDeleted(function (SoftBulkUser $record, ResourceEventContext $context): void {
                SoftBulkEventTracker::$events[] = [
                    'event' => 'deleted',
                    'source' => $context->source(),
                    'operation' => $context->operation(),
                ];
            })
            ->onRestoring(function (SoftBulkUser $record, ResourceEventContext $context): void {
                SoftBulkEventTracker::$events[] = [
                    'event' => 'restoring',
                    'source' => $context->source(),
                    'operation' => $context->operation(),
                ];

                if (SoftBulkEventTracker::$throwOnRestoring) {
                    throw new LogicException('Bulk restore blocked by event.');
                }
            })
            ->onRestored(function (SoftBulkUser $record, ResourceEventContext $context): void {
                SoftBulkEventTracker::$events[] = [
                    'event' => 'restored',
                    'source' => $context->source(),
                    'operation' => $context->operation(),
                ];
            });
    }
}

/**
 * @property string $name
 */
final class SoftBulkUser extends Model
{
    use SoftDeletes;

    protected $table = 'soft_bulk_users';

    protected $guarded = [];
}

final class HardBulkUser extends Model
{
    protected $table = 'hard_bulk_users';

    protected $guarded = [];
}

/**
 * @property bool $can_view_any
 * @property bool $can_view
 * @property bool $can_update
 * @property bool $can_delete
 * @property bool $can_restore
 */
final class BulkPolicyUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}

final class SoftBulkUserPolicy
{
    public function viewAny(BulkPolicyUser $user): bool
    {
        return (bool) $user->can_view_any;
    }

    public function view(BulkPolicyUser $user, SoftBulkUser $record): bool
    {
        return (bool) $user->can_view;
    }

    public function update(BulkPolicyUser $user, SoftBulkUser $record): bool
    {
        return (bool) $user->can_update;
    }

    public function delete(BulkPolicyUser $user, SoftBulkUser $record): bool
    {
        return (bool) $user->can_delete;
    }

    public function restore(BulkPolicyUser $user, SoftBulkUser $record): bool
    {
        return (bool) $user->can_restore;
    }
}

final class SoftBulkEventTracker
{
    /**
     * @var array<int, array{event:string,source:string,operation:string}>
     */
    public static array $events = [];

    public static bool $throwOnDeleting = false;

    public static bool $throwOnRestoring = false;

    public static function reset(): void
    {
        self::$events = [];
        self::$throwOnDeleting = false;
        self::$throwOnRestoring = false;
    }
}
