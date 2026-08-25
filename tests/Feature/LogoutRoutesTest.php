<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Ui\SeviaFlowUiServiceProvider;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Schema::create('seviaflow_dashboard_layouts', function (Blueprint $table): void {
        $table->id();
        $table->string('dashboard');
        $table->string('user_type')->nullable();
        $table->string('user_id')->nullable();
        $table->json('layout');
        $table->timestamps();
    });
});

it('registers logout route name', function (): void {
    expect(Route::has('seviaflow.logout'))->toBeTrue();
});

it('authenticated user can logout and is redirected to admin login', function (): void {
    $user = LogoutTestUser::query()->create([
        'name' => 'Logout User',
        'email' => 'logout@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    $response = pest_test_case()->post('/admin/logout');

    $response->assertRedirect('/admin/login');
    expect(auth('web')->check())->toBeFalse();
});

it('logout invalidates session and regenerates csrf token', function (): void {
    $user = LogoutTestUser::query()->create([
        'name' => 'Session User',
        'email' => 'session@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');
    pest_test_case()->withSession(['logout_probe' => 'present']);

    $oldToken = session()->token();

    pest_test_case()->post('/admin/logout')->assertRedirect('/admin/login');

    expect(session()->get('logout_probe'))->toBeNull()
        ->and(session()->token())->not->toBe($oldToken);
});

it('logout route respects custom prefix and route name prefix', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice-admin.');

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    $user = LogoutTestUser::query()->create([
        'name' => 'Backoffice Logout User',
        'email' => 'backoffice-logout@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->post('/backoffice/logout')->assertRedirect('/backoffice/login');
    expect(Route::has('backoffice-admin.logout'))->toBeTrue();
});

it('get logout is not allowed', function (): void {
    $user = LogoutTestUser::query()->create([
        'name' => 'Get Logout User',
        'email' => 'get-logout@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/logout')->assertStatus(405);
});

it('logout uses fallback redirect when auth ui is disabled', function (): void {
    config()->set('seviaflow-ui.auth.enabled', false);
    config()->set('seviaflow-ui.auth.redirect_after_logout', '/');

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    $user = LogoutTestUser::query()->create([
        'name' => 'Fallback Logout User',
        'email' => 'fallback-logout@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->post('/admin/logout')->assertRedirect('/');
});

it('logout button form renders in admin layout', function (): void {
    $user = LogoutTestUser::query()->create([
        'name' => 'Layout Logout User',
        'email' => 'layout-logout@example.com',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSee('action="'.route('seviaflow.logout').'"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('Sign out');
});

final class LogoutTestUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}
