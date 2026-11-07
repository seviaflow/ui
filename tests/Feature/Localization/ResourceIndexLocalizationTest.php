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

it('localizes empty tables and pagination while preserving queries and record values', function (string $locale, string $empty, string $range, string $page, string $selection): void {
    app()->setLocale($locale);

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->assertSee($empty);

    for ($index = 1; $index <= 16; $index++) {
        TestUser::query()->create(['name' => 'Save', 'email' => 'user'.$index.'@example.com']);
    }
    $record = TestUser::query()->firstOrFail();

    Livewire::test(ResourceIndex::class, ['resource' => 'search-sort-users'])
        ->set('perPage', 15)
        ->assertSee($range)
        ->assertSee($page)
        ->assertSee('Save')
        ->set('selectedRecords', [(string) $record->getKey()])
        ->assertSee($selection)
        ->call('nextPage')
        ->assertSet('paginators.page', 2)
        ->set('search', 'no-match')
        ->assertSet('paginators.page', 1)
        ->assertSee(__('seviaflow-ui::panel.no_matching_records_found'));
})->with([
    'English' => ['en', 'No records to display.', 'Showing 1-15 of 16', 'Page 1 of 2', '1 record selected'],
    'Polish' => ['pl', 'Brak rekordów do wyświetlenia.', 'Wyświetlono 1–15 z 16', 'Strona 1 z 2', 'Zaznaczony rekord: 1'],
]);
