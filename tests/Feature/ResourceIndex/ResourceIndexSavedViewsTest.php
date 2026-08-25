<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceIndex;
use SeviaFlow\Ui\SavedViews\SavedView;

require __DIR__.'/../Support/ResourceIndexSetup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    resourceIndexBootstrap();

    Schema::create('saved_view_auth_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });
});

it('shows empty saved views state', function (): void {
    $user = SavedViewAuthUser::query()->create(['name' => 'Operator']);
    pest_test_case()->actingAs($user);

    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->assertSee('Saved views')
        ->assertSee('No saved views yet.');
});

it('saves current view state for authenticated user', function (): void {
    $user = SavedViewAuthUser::query()->create(['name' => 'Operator']);
    pest_test_case()->actingAs($user);

    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->set('search', 'acme')
        ->set('filterValues.status', 'published')
        ->set('sortColumn', 'name')
        ->set('sortDirection', 'desc')
        ->set('perPage', 25)
        ->set('savedViewName', 'Published Desc')
        ->call('saveCurrentView')
        ->assertSee('Saved view created successfully.');

    $savedView = SavedView::query()->firstOrFail();

    expect($savedView->resource)->toBe('filter-users')
        ->and($savedView->visibility)->toBe('private')
        ->and($savedView->name)->toBe('Published Desc')
        ->and($savedView->state)->toBe([
            'search' => 'acme',
            'filters' => ['is_active' => '', 'status' => 'published'],
            'sort' => ['column' => 'name', 'direction' => 'desc'],
            'per_page' => 25,
        ]);
});

it('applies saved view and resets pagination to first page', function (): void {
    foreach (range(1, 16) as $index) {
        TestUser::query()->create([
            'name' => 'Paged User '.$index,
            'email' => 'paged-user-'.$index.'@example.com',
            'status' => $index === 16 ? 'published' : 'draft',
            'is_active' => true,
        ]);
    }

    $user = SavedViewAuthUser::query()->create(['name' => 'Operator']);
    pest_test_case()->actingAs($user);

    $savedView = SavedView::query()->create([
        'resource' => 'filter-users',
        'name' => 'Published',
        'user_type' => $user->getMorphClass(),
        'user_id' => (string) $user->getKey(),
        'visibility' => 'private',
        'state' => [
            'search' => '',
            'filters' => ['status' => 'published'],
            'sort' => ['column' => '', 'direction' => 'asc'],
            'per_page' => 15,
        ],
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->call('nextPage')
        ->assertSet('paginators.page', 2)
        ->assertSee('Paged User 16')
        ->set('selectedSavedViewId', (string) $savedView->id)
        ->call('applySavedView', (string) $savedView->id)
        ->assertSet('paginators.page', 1)
        ->assertSet('filterValues.status', 'published')
        ->assertSee('Saved view applied.')
        ->assertSee('Paged User 16');
});

it('deletes owned saved view', function (): void {
    $user = SavedViewAuthUser::query()->create(['name' => 'Operator']);
    pest_test_case()->actingAs($user);

    $savedView = SavedView::query()->create([
        'resource' => 'filter-users',
        'name' => 'My view',
        'user_type' => $user->getMorphClass(),
        'user_id' => (string) $user->getKey(),
        'visibility' => 'private',
        'state' => ['search' => '', 'filters' => [], 'sort' => null, 'per_page' => 15],
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->call('deleteSavedView', (string) $savedView->id)
        ->assertSee('Saved view deleted.');

    expect(SavedView::query()->whereKey($savedView->id)->exists())->toBeFalse();
});

it('does not allow operating on another users private saved views', function (): void {
    $owner = SavedViewAuthUser::query()->create(['name' => 'Owner']);
    $other = SavedViewAuthUser::query()->create(['name' => 'Other']);
    pest_test_case()->actingAs($other);

    $savedView = SavedView::query()->create([
        'resource' => 'filter-users',
        'name' => 'Owner view',
        'user_type' => $owner->getMorphClass(),
        'user_id' => (string) $owner->getKey(),
        'visibility' => 'private',
        'state' => ['search' => 'owner', 'filters' => [], 'sort' => null, 'per_page' => 15],
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->call('applySavedView', (string) $savedView->id)
        ->assertSee('Saved view not found.')
        ->call('deleteSavedView', (string) $savedView->id)
        ->assertSee('Saved view not found.');

    expect(SavedView::query()->whereKey($savedView->id)->exists())->toBeTrue();
});

final class SavedViewAuthUser extends AuthenticatableUser
{
    protected $table = 'saved_view_auth_users';

    protected $guarded = [];
}
