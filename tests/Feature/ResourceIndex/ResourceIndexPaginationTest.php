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

it('renders numbered length-aware pagination with range and ellipsis', function (): void {
    seedPaginationUsers(151);

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->set('perPage', 15)
        ->call('gotoPage', 6)
        ->assertSee('Showing 76-90 of 151')
        ->assertSee('Previous')
        ->assertSee('Next')
        ->assertSee('Page 6 of 11')
        ->assertSee('aria-current="page"', false)
        ->assertSee('aria-label="Go to page 1"', false)
        ->assertSee('aria-label="Go to page 5"', false)
        ->assertSee('aria-label="Go to page 7"', false)
        ->assertSee('aria-label="Go to page 11"', false)
        ->assertSee('sf-pagination-ellipsis', false);
});

it('changes pages and preserves sort and filter state', function (): void {
    seedPaginationUsers(76);

    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->set('perPage', 15)
        ->set('filterValues.status', 'published')
        ->call('sortBy', 'name')
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->assertSet('filterValues.status', 'published')
        ->assertSet('sortColumn', 'name')
        ->assertSet('sortDirection', 'asc')
        ->assertSee('Published User 032')
        ->call('previousPage')
        ->assertSet('paginators.page', 1)
        ->call('nextPage')
        ->assertSet('paginators.page', 2);
});

it('disables first and last boundary controls', function (): void {
    seedPaginationUsers(76);

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->set('perPage', 15)
        ->assertSee('aria-label="Previous page"', false)
        ->assertSee('disabled', false)
        ->call('gotoPage', 6)
        ->assertSee('aria-label="Next page"', false)
        ->assertSee('disabled', false);
});

it('resets to the first page when search filter or per-page changes', function (): void {
    seedPaginationUsers(76);

    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->set('perPage', 15)
        ->call('gotoPage', 3)
        ->assertSet('paginators.page', 3)
        ->set('search', 'Published User 002')
        ->assertSet('paginators.page', 1)
        ->call('gotoPage', 2)
        ->set('filterValues.status', 'draft')
        ->assertSet('paginators.page', 1)
        ->call('gotoPage', 2)
        ->set('perPage', 25)
        ->assertSet('paginators.page', 1);
});

function seedPaginationUsers(int $count): void
{
    foreach (range(1, $count) as $index) {
        $published = $index % 2 === 0;

        TestUser::query()->create([
            'name' => sprintf('%s User %03d', $published ? 'Published' : 'Draft', $index),
            'email' => sprintf('pagination-user-%03d@example.com', $index),
            'status' => $published ? 'published' : 'draft',
            'is_active' => true,
        ]);
    }
}
