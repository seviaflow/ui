<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceIndex;

require __DIR__.'/../Support/ResourceIndexSetup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceIndexBootstrap(), 'web');
});

it('hides columns configured with hiddenOnIndex from index rendering', function (): void {
    TestUser::query()->create([
        'name' => 'Visible Name',
        'email' => 'hidden-column@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    pest_test_case()->get('/admin/resources/visibility-users')
        ->assertOk()
        ->assertSee('Name')
        ->assertDontSee('Email')
        ->assertSee('Visible Name')
        ->assertDontSee('hidden-column@example.com');
});

it('searches only searchable columns', function (): void {
    TestUser::query()->create([
        'name' => 'Alice Johnson',
        'email' => 'alice@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    TestUser::query()->create([
        'name' => 'Bob Smith',
        'email' => 'bob@example.com',
        'status' => 'published',
        'is_active' => false,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->set('search', 'Alice')
        ->assertSee('Alice Johnson')
        ->assertDontSee('Bob Smith');
});

it('does not search non-searchable columns', function (): void {
    TestUser::query()->create([
        'name' => 'Visible User',
        'email' => 'hidden-match@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->set('search', 'hidden-match@example.com')
        ->assertSee('No matching records found.');
});

it('sorts by sortable columns', function (): void {
    TestUser::query()->create([
        'name' => 'Charlie',
        'email' => 'charlie@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    TestUser::query()->create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'status' => 'published',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->call('sortBy', 'name')
        ->assertSeeInOrder(['Alice', 'Charlie'])
        ->call('sortBy', 'name')
        ->assertSeeInOrder(['Charlie', 'Alice']);
});

it('ignores invalid sort columns safely', function (): void {
    TestUser::query()->create([
        'name' => 'First User',
        'email' => 'first@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    TestUser::query()->create([
        'name' => 'Second User',
        'email' => 'second@example.com',
        'status' => 'published',
        'is_active' => false,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->set('sortColumn', 'email')
        ->set('sortDirection', 'desc')
        ->assertSet('sortColumn', 'email')
        ->assertSee('First User')
        ->assertSee('Second User');
});

it('paginates records', function (): void {
    foreach (range(1, 16) as $index) {
        TestUser::query()->create([
            'name' => 'User '.$index,
            'email' => 'user'.$index.'@example.com',
            'status' => $index % 2 === 0 ? 'published' : 'draft',
            'is_active' => true,
        ]);
    }

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->set('perPage', 15)
        ->assertSee('User 1')
        ->assertSee('User 15')
        ->assertDontSee('User 16');
});

it('enforces configured max per-page guard', function (): void {
    config()->set('seviaflow.performance.pagination.max_per_page', 5);
    config()->set('seviaflow.performance.pagination.per_page_options', [5, 10, 25]);

    foreach (range(1, 6) as $index) {
        TestUser::query()->create([
            'name' => 'Guarded User '.$index,
            'email' => 'guarded'.$index.'@example.com',
            'status' => 'draft',
            'is_active' => true,
        ]);
    }

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->set('perPage', 25)
        ->assertSee('Guarded User 1')
        ->assertSee('Guarded User 5')
        ->assertDontSee('Guarded User 6');
});

it('falls back to safe default when per-page is invalid', function (): void {
    config()->set('seviaflow.performance.pagination.default_per_page', 15);
    config()->set('seviaflow.performance.pagination.per_page_options', [15, 25, 50]);
    config()->set('seviaflow.performance.pagination.max_per_page', 100);

    foreach (range(1, 16) as $index) {
        TestUser::query()->create([
            'name' => 'Fallback User '.$index,
            'email' => 'fallback'.$index.'@example.com',
            'status' => 'draft',
            'is_active' => true,
        ]);
    }

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->set('perPage', 0)
        ->assertSee('Fallback User 1')
        ->assertSee('Fallback User 15')
        ->assertDontSee('Fallback User 16');
});
