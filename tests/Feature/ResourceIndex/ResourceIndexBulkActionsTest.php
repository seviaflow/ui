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

it('renders row selection controls', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Selectable User',
        'email' => 'selectable@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    pest_test_case()->get('/admin/resources/search-sort-users')
        ->assertOk()
        ->assertSee('record-select-'.$user->getKey())
        ->assertSee('select-all-visible')
        ->assertSee('0 records selected');
});

it('keeps stale record IDs for deterministic bulk-action rejection', function (): void {
    $first = TestUser::query()->create([
        'name' => 'First Selected',
        'email' => 'first-selected@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    $second = TestUser::query()->create([
        'name' => 'Second Selected',
        'email' => 'second-selected@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->set('selectedRecords', [(string) $first->getKey(), '999999'])
        ->assertSet('selectedRecords', [(string) $first->getKey(), '999999'])
        ->assertSee('2 records selected')
        ->assertSee($first->name)
        ->assertSee($second->name);
});

it('select all visible selects only current page records', function (): void {
    foreach (range(1, 16) as $index) {
        TestUser::query()->create([
            'name' => 'Paged Select '.$index,
            'email' => 'paged-select-'.$index.'@example.com',
            'status' => 'draft',
            'is_active' => true,
        ]);
    }

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->set('perPage', 15)
        ->call('selectAllVisible')
        ->assertSet('selectedRecords', array_map('strval', range(1, 15)))
        ->assertSee('15 records selected')
        ->assertDontSee('Paged Select 16');
});

it('clears selected records', function (): void {
    $first = TestUser::query()->create([
        'name' => 'Clear One',
        'email' => 'clear-one@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    $second = TestUser::query()->create([
        'name' => 'Clear Two',
        'email' => 'clear-two@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->set('selectedRecords', [(string) $first->getKey(), (string) $second->getKey()])
        ->call('clearSelection')
        ->assertSet('selectedRecords', [])
        ->assertSet('selectPage', false)
        ->assertSee('0 records selected');
});

it('resets selection when search scope changes', function (): void {
    TestUser::query()->create([
        'name' => 'Search Scope One',
        'email' => 'search-scope-one@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    TestUser::query()->create([
        'name' => 'Search Scope Two',
        'email' => 'search-scope-two@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->call('selectAllVisible')
        ->assertSet('selectedRecords', ['1', '2'])
        ->set('search', 'Search Scope One')
        ->assertSet('selectedRecords', [])
        ->assertSet('selectPage', false)
        ->assertSee('0 records selected');
});
