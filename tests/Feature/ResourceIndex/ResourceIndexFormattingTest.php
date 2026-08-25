<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceIndex;

require __DIR__.'/../Support/ResourceIndexSetup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceIndexBootstrap(), 'web');
});

it('renders belongsTo related title on index', function (): void {
    $company = IndexCompany::query()->create(['name' => 'Acme Corp']);

    TestUser::query()->create([
        'name' => 'Related User',
        'email' => 'related@example.com',
        'company_id' => $company->getKey(),
        'status' => 'draft',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'belongs-to-column-users'])
        ->assertSee('Acme Corp');
});

it('renders safe placeholder when belongsTo relation is null', function (): void {
    TestUser::query()->create([
        'name' => 'No Company',
        'email' => 'nocompany@example.com',
        'company_id' => null,
        'status' => 'draft',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'belongs-to-column-users'])
        ->assertSee('—');
});

it('does not execute n plus one queries for belongsTo column rendering', function (): void {
    $companies = collect(range(1, 5))->map(fn (int $i): IndexCompany => IndexCompany::query()->create([
        'name' => 'Company '.$i,
    ]));

    foreach (range(1, 25) as $index) {
        TestUser::query()->create([
            'name' => 'User '.$index,
            'email' => 'user-'.$index.'@example.com',
            'company_id' => $companies[$index % $companies->count()]->getKey(),
            'status' => 'draft',
            'is_active' => true,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(ResourceIndex::class, ['resource' => 'belongs-to-column-users'])
        ->set('perPage', 25)
        ->assertSee('User 1');

    $companySelectQueries = collect(DB::getQueryLog())
        ->filter(static fn (array $entry): bool => str_contains(strtolower((string) ($entry['query'] ?? '')), 'from "index_companies"'))
        ->count();

    expect($companySelectQueries)->toBe(2);
});

it('renders badge column labels with configured color class', function (): void {
    TestUser::query()->create([
        'name' => 'Badge Active',
        'email' => 'badge-active@example.com',
        'status' => 'active',
        'is_active' => true,
    ]);

    pest_test_case()->get('/admin/resources/badge-column-users')
        ->assertOk()
        ->assertSee('Active')
        ->assertSee('bg-green-100');
});

it('badge column unknown value uses default style safely', function (): void {
    TestUser::query()->create([
        'name' => 'Badge Unknown',
        'email' => 'badge-unknown@example.com',
        'status' => 'mystery',
        'is_active' => true,
    ]);

    pest_test_case()->get('/admin/resources/badge-column-users')
        ->assertOk()
        ->assertSee('mystery')
        ->assertSee('bg-gray-100');
});

it('badge column null value renders placeholder', function (): void {
    TestUser::query()->create([
        'name' => 'Badge Null',
        'email' => 'badge-null@example.com',
        'status' => '',
        'is_active' => true,
    ]);

    pest_test_case()->get('/admin/resources/badge-column-users')
        ->assertOk()
        ->assertSee('—');
});

it('badge column ignores unsafe color names safely', function (): void {
    TestUser::query()->create([
        'name' => 'Badge Unsafe',
        'email' => 'badge-unsafe@example.com',
        'status' => 'blocked',
        'is_active' => true,
    ]);

    pest_test_case()->get('/admin/resources/badge-column-users')
        ->assertOk()
        ->assertSee('Blocked')
        ->assertSee('bg-gray-100')
        ->assertDontSee('javascript:');
});

it('status column renders mapped label and color', function (): void {
    TestUser::query()->create([
        'name' => 'Status Active',
        'email' => 'status-active@example.com',
        'status' => 'active',
        'is_active' => true,
    ]);

    pest_test_case()->get('/admin/resources/status-column-users')
        ->assertOk()
        ->assertSee('Active')
        ->assertSee('bg-green-100');
});
