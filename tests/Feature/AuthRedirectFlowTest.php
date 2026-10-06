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

it('does not grant panel access when the host gate denies the user', function (): void {
    config()->set('seviaflow.permissions.admin_gate', static fn (): bool => false);
    $user = AuthRedirectUser::query()->create([
        'name' => 'Denied Panel User',
        'email' => 'denied-panel@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin')->assertForbidden();
    pest_test_case()->get('/admin/login')->assertOk();
});

it('requires a two-factor challenge before creating the panel session', function (): void {
    config()->set('auth.providers.users.model', TwoFactorLoginUser::class);
    config()->set('seviaflow-ui.auth.two_factor_login.verify', static fn (TwoFactorLoginUser $user, string $code): bool => $code === '123456');

    $user = TwoFactorLoginUser::query()->create([
        'name' => 'Two Factor Login User',
        'email' => 'two-factor-login@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    pest_test_case()->post('/admin/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertRedirect('/admin/login/two-factor');

    pest_test_case()->assertGuest('web');
    pest_test_case()->get('/admin/login/two-factor')
        ->assertOk()
        ->assertSee('Verify your sign in');

    pest_test_case()->post('/admin/login/two-factor', ['code' => 'wrong-code'])
        ->assertSessionHasErrors('code');
    pest_test_case()->assertGuest('web');

    pest_test_case()->post('/admin/login/two-factor', ['code' => '123456'])
        ->assertRedirect('/admin');
    pest_test_case()->assertAuthenticatedAs($user, 'web');
});

it('throttles repeated login attempts', function (): void {
    config()->set('seviaflow-ui.auth.login_throttle.max_attempts', 1);

    pest_test_case()->post('/admin/login', [
        'email' => 'throttled@example.com',
        'password' => 'wrong-password',
    ])->assertRedirect();

    pest_test_case()->post('/admin/login', [
        'email' => 'throttled@example.com',
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
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

/**
 * @property string $email
 */
final class TwoFactorLoginUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];

    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return true;
    }
}
