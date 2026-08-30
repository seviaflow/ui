<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use SeviaFlow\Ui\Livewire\ResourceIndex;
use SeviaFlow\Ui\SeviaFlowUiServiceProvider;

require __DIR__.'/../Support/ResourceIndexSetup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pest_test_case()->actingAs(resourceIndexBootstrap(), 'web');
});

it('renders configured resource records', function (): void {
    TestUser::query()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'status' => 'published',
        'is_active' => true,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'test-users'])
        ->assertSee('Jane Doe')
        ->assertSee('jane@example.com')
        ->assertSee('Yes');
});

it('renders configured column labels', function (): void {
    Livewire::test(ResourceIndex::class, ['resource' => 'test-users'])
        ->assertSee('Name')
        ->assertSee('Email')
        ->assertSee('Active');
});

it('renders empty state when no records exist', function (): void {
    Livewire::test(ResourceIndex::class, ['resource' => 'test-users'])
        ->assertSee('No records to display.');
});

it('resolves model through resource', function (): void {
    TestUser::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'status' => 'draft',
        'is_active' => false,
    ]);

    Livewire::test(ResourceIndex::class, ['resource' => 'test-users'])
        ->assertSee('John Doe')
        ->assertSee('No');
});

it('renders through package route with default prefix', function (): void {
    pest_test_case()->get('/admin/resources/test-users')
        ->assertOk()
        ->assertSeeLivewire(ResourceIndex::class)
        ->assertSee('SeviaFlow')
        ->assertSee('sf-sidebar', false)
        ->assertSee('sf-topbar', false)
        ->assertSee('data-sf-content-width="wide"', false)
        ->assertSee('sf-content-wide', false)
        ->assertSee('/admin/resources/test-users', false)
        ->assertSee('sf-sidebar-link-active', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.css', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.js', false)
        ->assertDontSee('@vite', false);
});

it('renders resource index with design-system table shell classes', function (): void {
    pest_test_case()->get('/admin/resources/test-users')
        ->assertOk()
        ->assertSee('sf-input', false)
        ->assertSee('sf-select', false)
        ->assertSee('sf-table-wrapper', false)
        ->assertSee('sf-table', false)
        ->assertSee('sf-table-header', false)
        ->assertSee('sf-table-empty', false)
        ->assertSee('sf-pagination', false);
});

it('registers route name with configured name prefix', function (): void {
    expect(Route::has('seviaflow.resources.index'))->toBeTrue();
});

it('uses custom configured route prefix and route name prefix', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice-admin.');

    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    pest_test_case()->get('/backoffice/resources/test-users')
        ->assertOk()
        ->assertSeeLivewire(ResourceIndex::class);

    expect(Route::has('backoffice-admin.resources.index'))->toBeTrue();
});

it('does not rely on old hardcoded admin prefix', function (): void {
    pest_test_case()->get('/seviaflow/resources/test-users')
        ->assertNotFound();
});

it('returns 404 for unknown resource slug', function (): void {
    pest_test_case()->get('/admin/resources/unknown-resource')
        ->assertNotFound();
});

it('does not accept raw resource class name as route resource identifier', function (): void {
    pest_test_case()->get('/admin/resources/'.rawurlencode(TestUserResource::class))
        ->assertNotFound();
});

it('shows create button when form exists', function (): void {
    pest_test_case()->get('/admin/resources/form-users')
        ->assertOk()
        ->assertSee('Create')
        ->assertSee('/admin/resources/form-users/create');
});

it('shows edit action when form exists', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Editable User',
        'email' => 'edit@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    pest_test_case()->get('/admin/resources/form-users')
        ->assertOk()
        ->assertSee('/admin/resources/form-users/'.$user->getKey().'/edit')
        ->assertSee('Edit');
});

it('create button url uses configured prefix', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice-admin.');

    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    pest_test_case()->get('/backoffice/resources/form-users')
        ->assertOk()
        ->assertSee('/backoffice/resources/form-users/create');
});

it('edit url uses configured prefix', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Prefixed Edit User',
        'email' => 'prefixed-edit@example.com',
        'status' => 'draft',
        'is_active' => true,
    ]);

    config()->set('seviaflow-ui.route_prefix', 'backoffice');
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice-admin.');

    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    pest_test_case()->get('/backoffice/resources/form-users')
        ->assertOk()
        ->assertSee('/backoffice/resources/form-users/'.$user->getKey().'/edit');
});
