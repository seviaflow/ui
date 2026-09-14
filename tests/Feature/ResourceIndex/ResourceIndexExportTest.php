<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceIndex;

require __DIR__.'/../Support/ResourceIndexSetup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceIndexBootstrap(), 'web');
});

it('shows export button only when export is enabled and fields exist', function (): void {
    pest_test_case()->get('/admin/resources/export-enabled-users')
        ->assertOk()
        ->assertSee('wire:click="openExportModal"', false);

    pest_test_case()->get('/admin/resources/export-disabled-users')
        ->assertOk()
        ->assertDontSee('wire:click="openExportModal"', false);

    pest_test_case()->get('/admin/resources/export-no-fields-users')
        ->assertOk()
        ->assertDontSee('wire:click="openExportModal"', false);
});

it('opens export modal with only allowed fields and csv format', function (): void {
    Livewire::test(ResourceIndex::class, ['resource' => 'export-enabled-users'])
        ->call('openExportModal')
        ->assertSet('showExportModal', true)
        ->assertSet('selectedExportFormat', 'csv')
        ->assertSet('selectedExportFields', ['id', 'name', 'email'])
        ->assertSee('Export records')
        ->assertSee('id="sf-resource-export"', false)
        ->assertSee('role="dialog"', false)
        ->assertSee('aria-modal="true"', false)
        ->assertSee('ID')
        ->assertSee('Name')
        ->assertSee('Email address')
        ->assertDontSee('value="status"', false);
});

it('validates export format and fields against resource export definition', function (): void {
    Livewire::test(ResourceIndex::class, ['resource' => 'export-enabled-users'])
        ->call('openExportModal')
        ->set('selectedExportFields', ['id', 'unknown_field'])
        ->call('exportRecords')
        ->assertSee('One or more selected export fields are not allowed.')
        ->set('selectedExportFields', ['id'])
        ->set('selectedExportFormat', 'xlsx')
        ->call('exportRecords')
        ->assertSee('Selected export format is not allowed.');
});

it('validates selected rows scope and exports current view', function (): void {
    TestUser::query()->create([
        'name' => 'Export User',
        'email' => 'export-user@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'export-enabled-users'])
        ->call('openExportModal')
        ->set('selectedExportFields', ['id'])
        ->set('exportScope', 'selected_rows')
        ->call('exportRecords')
        ->assertSee('Select at least one row to export selected rows.')
        ->set('exportScope', 'current_view')
        ->call('exportRecords')
        ->assertFileDownloaded();
});

it('does not allow export when resource export contract is disabled', function (): void {
    Livewire::test(ResourceIndex::class, ['resource' => 'export-disabled-users'])
        ->call('openExportModal')
        ->assertSet('showExportModal', false)
        ->call('exportRecords')
        ->assertSee('Export is not available for this resource.');
});

it('downloads csv export for current filtered view with safe filename', function (): void {
    Carbon::setTestNow('2026-05-08 12:00:00');

    TestUser::query()->create([
        'name' => 'Filtered User',
        'email' => 'filtered@example.com',
        'status' => 'published',
        'is_active' => true,
    ]);
    TestUser::query()->create([
        'name' => 'Other User',
        'email' => 'other@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'export-enabled-users'])
        ->set('filterValues.status', 'published')
        ->call('openExportModal')
        ->set('selectedExportFields', ['id', 'name'])
        ->set('selectedExportFormat', 'csv')
        ->set('exportScope', 'current_view')
        ->call('exportRecords')
        ->assertFileDownloaded('export-enabled-users-export-20260508-120000.csv');

    Carbon::setTestNow();
});

it('downloads csv export for selected rows only', function (): void {
    Carbon::setTestNow('2026-05-08 13:00:00');

    $first = TestUser::query()->create([
        'name' => 'Selected Export',
        'email' => 'selected-export@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    TestUser::query()->create([
        'name' => 'Unselected Export',
        'email' => 'unselected-export@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'export-enabled-users'])
        ->set('selectedRecords', [(string) $first->getKey()])
        ->call('openExportModal')
        ->set('selectedExportFields', ['id', 'name'])
        ->set('selectedExportFormat', 'csv')
        ->set('exportScope', 'selected_rows')
        ->call('exportRecords')
        ->assertFileDownloaded('export-enabled-users-export-20260508-130000.csv');

    Carbon::setTestNow();
});

it('rejects export when max rows is exceeded', function (): void {
    config()->set('seviaflow.export.max_rows', 1);

    TestUser::query()->create([
        'name' => 'Row One',
        'email' => 'row-one@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);
    TestUser::query()->create([
        'name' => 'Row Two',
        'email' => 'row-two@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'export-enabled-users'])
        ->call('openExportModal')
        ->set('selectedExportFields', ['id', 'name'])
        ->set('selectedExportFormat', 'csv')
        ->set('exportScope', 'current_view')
        ->call('exportRecords')
        ->assertSee('Export exceeds the maximum allowed rows (1). Please narrow your filters or selection.');
});
