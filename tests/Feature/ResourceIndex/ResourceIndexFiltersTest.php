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

it('filters records by boolean filter', function (): void {
    TestUser::query()->create([
        'name' => 'Active User',
        'email' => 'active@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    TestUser::query()->create([
        'name' => 'Inactive User',
        'email' => 'inactive@example.com',
        'status' => 'draft',
        'is_active' => false,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->set('filterValues.is_active', '1')
        ->assertSee('Active User')
        ->assertDontSee('Inactive User');
});

it('filters records by select filter', function (): void {
    TestUser::query()->create([
        'name' => 'Draft User',
        'email' => 'draft@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    TestUser::query()->create([
        'name' => 'Published User',
        'email' => 'published@example.com',
        'status' => 'published',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->set('filterValues.status', 'published')
        ->assertSee('Published User')
        ->assertDontSee('Draft User');
});

it('does not apply filters when filter value is empty', function (): void {
    TestUser::query()->create([
        'name' => 'First',
        'email' => 'first@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    TestUser::query()->create([
        'name' => 'Second',
        'email' => 'second@example.com',
        'status' => 'published',
        'is_active' => false,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->set('filterValues.status', '')
        ->assertSee('First')
        ->assertSee('Second');
});

it('renders filter labels and active filter count', function (): void {
    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->assertSee('Active')
        ->assertSee('Status')
        ->assertSee('0 filters active')
        ->set('filterValues.status', 'published')
        ->assertSee('1 filter active')
        ->set('filterValues.is_active', '1')
        ->assertSee('2 filters active');
});

it('renders filters in the contextual drawer without duplicating livewire state', function (): void {
    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->assertSee('data-sf-drawer-target="sf-resource-filters"', false)
        ->assertSee('id="sf-resource-filters"', false)
        ->assertSee('aria-labelledby="sf-resource-filters-title"', false)
        ->assertSee('wire:model.live="filterValues.is_active"', false)
        ->assertSee('wire:model.live="filterValues.status"', false)
        ->assertSee('Values apply as they change')
        ->assertSee('wire:click="resetFilters"', false);
});

it('does not render fake index prototype controls', function (): void {
    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->assertDontSee('Columns')
        ->assertDontSee('Notifications')
        ->assertDontSee('system-health', false)
        ->assertDontSee('data-sf-column-manager', false);
});

it('reset filters clears filter state and preserves search value', function (): void {
    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->set('search', 'keep-search')
        ->set('filterValues.status', 'published')
        ->set('filterValues.is_active', '1')
        ->call('resetFilters')
        ->assertSet('search', 'keep-search')
        ->assertSet('filterValues.status', '')
        ->assertSet('filterValues.is_active', '')
        ->assertSee('0 filters active');
});

it('changing filter resets pagination to first page', function (): void {
    foreach (range(1, 16) as $index) {
        TestUser::query()->create([
            'name' => 'Paged Filter User '.$index,
            'email' => 'paged-filter-user-'.$index.'@example.com',
            'status' => $index % 2 === 0 ? 'published' : 'draft',
            'is_active' => true,
        ]);
    }

    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->set('perPage', 15)
        ->call('nextPage')
        ->assertSee('Paged Filter User 16')
        ->set('filterValues.status', 'draft')
        ->assertSee('Paged Filter User 1')
        ->assertDontSee('Paged Filter User 16');
});

it('normalizes invalid filter values safely while preserving valid values', function (): void {
    Livewire::test(ResourceIndex::class, ['resource' => 'filter-users'])
        ->set('filterValues.status', 'invalid-status')
        ->assertSet('filterValues.status', '')
        ->set('filterValues.status', 'draft')
        ->assertSet('filterValues.status', 'draft')
        ->set('filterValues.is_active', 'not-a-bool')
        ->assertSet('filterValues.is_active', '')
        ->set('filterValues.is_active', '1')
        ->assertSet('filterValues.is_active', '1');
});
