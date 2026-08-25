<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Ui\Livewire\Dashboard;
use SeviaFlow\Ui\SeviaFlowUiServiceProvider;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    if (! Schema::hasTable('seviaflow_dashboard_layouts')) {
        Schema::create('seviaflow_dashboard_layouts', function (Blueprint $table): void {
            $table->id();
            $table->string('dashboard');
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->json('layout');
            $table->timestamps();

            $table->unique(['dashboard', 'user_type', 'user_id'], 'seviaflow_dashboard_layouts_scope_unique');
            $table->index(['user_type', 'user_id'], 'seviaflow_dashboard_layouts_user_lookup_index');
        });
    }
});

it('guest visiting admin root is redirected to admin login', function (): void {
    pest_test_case()->get('/admin')
        ->assertRedirect('/admin/login');
});

it('authenticated user visiting admin root sees dashboard', function (): void {
    $user = AuthRedirectUser::query()->create([
        'name' => 'Dashboard Auth User',
        'email' => 'dashboard-auth@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(Dashboard::class);
});

it('successful login redirects to dashboard', function (): void {
    $user = AuthRedirectUser::query()->create([
        'name' => 'Login Redirect User',
        'email' => 'login-redirect@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    pest_test_case()->post('/admin/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertRedirect('/admin');
});

it('authenticated user visiting admin login is redirected to dashboard', function (): void {
    $user = AuthRedirectUser::query()->create([
        'name' => 'Login Page Auth User',
        'email' => 'login-page-auth@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/login')
        ->assertRedirect('/admin');
});

it('logout redirects to admin login', function (): void {
    $user = AuthRedirectUser::query()->create([
        'name' => 'Logout Redirect User',
        'email' => 'logout-redirect@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->post('/admin/logout')
        ->assertRedirect('/admin/login');
});

it('custom route prefix uses dashboard and login redirects', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice-admin.');

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, true);

    pest_test_case()->get('/backoffice')->assertRedirect('/backoffice/login');

    $user = AuthRedirectUser::query()->create([
        'name' => 'Backoffice Redirect User',
        'email' => 'backoffice-redirect@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    pest_test_case()->post('/backoffice/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertRedirect('/backoffice');
});

/**
 * @property string $name
 * @property string $email
 * @property string $password
 */
final class AuthRedirectUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}
