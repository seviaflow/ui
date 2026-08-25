<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use SeviaFlow\Ui\SeviaFlowUiServiceProvider;

require __DIR__.'/../Support/ResourceShowSetup.php';
beforeEach(function (): void {
    pest_test_case()->actingAs(resourceShowBootstrap(), 'web');
});
it('index table shows view action link', function (): void {
    $user = ShowUser::query()->create([
        'name' => 'Index Link User',
        'email' => 'index-link@example.com',
    ]);

    pest_test_case()->get('/admin/resources/show-users')
        ->assertOk()
        ->assertSee('View')
        ->assertSee('/admin/resources/show-users/'.$user->getKey(), false);
});

it('registers default show route name', function (): void {
    expect(Route::has('seviaflow.resources.show'))->toBeTrue();
});

it('show route respects custom admin prefix and route name prefix', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice-admin.');

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    $user = ShowUser::query()->create([
        'name' => 'Backoffice Show User',
        'email' => 'backoffice-show@example.com',
    ]);

    pest_test_case()->get('/backoffice/resources/show-users/'.$user->getKey())
        ->assertOk()
        ->assertSee('Backoffice Show User');

    expect(Route::has('backoffice-admin.resources.show'))->toBeTrue();
});
