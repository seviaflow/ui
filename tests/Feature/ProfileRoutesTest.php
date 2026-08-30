<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Ui\SeviaFlowUiServiceProvider;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });
});

it('renders profile page for authenticated user', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Profile User',
        'email' => 'profile@example.com',
        'password' => 'secret',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/profile')
        ->assertOk()
        ->assertSee('SeviaFlow')
        ->assertSee('sf-sidebar', false)
        ->assertSee('sf-topbar', false)
        ->assertSee('data-sf-content-width="narrow"', false)
        ->assertSee('sf-content-narrow', false)
        ->assertSee('sf-user-card sf-user-card-active', false)
        ->assertDontSee('sf-user-card sf-sidebar-link-active', false)
        ->assertSee('sf-profile-layout', false)
        ->assertSee('sf-profile-nav-link-active', false)
        ->assertSee('Account settings')
        ->assertSee('Profile information')
        ->assertSee('Name')
        ->assertSee('Email address')
        ->assertSee('/vendor/seviaflow/ui/brand/seviaflow-logo.png', false)
        ->assertSee('/vendor/seviaflow/ui/brand/seviaflow-mark.png', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.css', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.js', false)
        ->assertDontSee('@vite', false);
});

it('blocks guest from profile routes', function (): void {
    pest_test_case()->get('/admin/profile')
        ->assertStatus(302);
    pest_test_case()->get('/admin/profile/two-factor-authentication')
        ->assertStatus(302);

    pest_test_case()->post('/admin/profile', [
        'name' => 'Guest User',
        'email' => 'guest@example.com',
    ])->assertStatus(302);

    pest_test_case()->post('/admin/profile/password', [
        'current_password' => 'secret',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertStatus(302);

    pest_test_case()->delete('/admin/profile/two-factor-authentication', [
        'confirm_disable' => '1',
    ])->assertStatus(302);

    pest_test_case()->post('/admin/profile/browser-sessions', [
        'current_password' => 'secret',
    ])->assertStatus(302);
});

it('registers default profile route names', function (): void {
    expect(Route::has('seviaflow.profile.index'))->toBeTrue()
        ->and(Route::has('seviaflow.profile.password'))->toBeTrue()
        ->and(Route::has('seviaflow.profile.two-factor-authentication'))->toBeTrue()
        ->and(Route::has('seviaflow.profile.browser-sessions'))->toBeTrue();
});

it('supports custom prefix for profile routes', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    $user = ProfileTestUser::query()->create([
        'name' => 'Backoffice User',
        'email' => 'backoffice@example.com',
        'password' => 'secret',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/backoffice/profile')->assertOk();
    pest_test_case()->get('/backoffice/profile/password')->assertOk();
    pest_test_case()->get('/backoffice/profile/two-factor-authentication')->assertOk();
    pest_test_case()->get('/backoffice/profile/browser-sessions')->assertOk();
});

it('renders browser sessions page for authenticated user', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Browser Sessions User',
        'email' => 'browser-sessions@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/profile/browser-sessions')
        ->assertOk()
        ->assertSee('Browser sessions')
        ->assertSee('Detailed session listing is not available in every Laravel session configuration.')
        ->assertSee('This device')
        ->assertSee('Last active')
        ->assertSee('Log out other browser sessions');
});

it('renders two-factor authentication page for authenticated user', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Two Factor User',
        'email' => 'two-factor@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/profile/two-factor-authentication')
        ->assertOk()
        ->assertSee('Two-factor authentication')
        ->assertSee('Add additional security to your account.');
});

it('shows graceful message when two-factor backend support is missing', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'No Backend User',
        'email' => 'no-backend@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/profile/two-factor-authentication')
        ->assertOk()
        ->assertSee('Two-factor authentication backend support is not installed or configured.');
});

it('requires confirmation before disabling two-factor authentication', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Confirm Disable User',
        'email' => 'confirm-disable@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->from('/admin/profile/two-factor-authentication')
        ->delete('/admin/profile/two-factor-authentication', [])
        ->assertSessionHasErrors('confirm_disable');
});

it('authenticated user can access password page', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Password Page User',
        'email' => 'password-page@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/profile/password')
        ->assertOk()
        ->assertSee('sf-account-action sf-account-action-active', false)
        ->assertDontSee('sf-account-action sf-sidebar-link-active', false)
        ->assertSee('Current password')
        ->assertSee('New password')
        ->assertSee('Confirm new password')
        ->assertSee('Update password');
});

it('supports custom route name prefix for profile routes', function (): void {
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice-admin.');

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    expect(Route::has('backoffice-admin.profile.index'))->toBeTrue()
        ->and(Route::has('backoffice-admin.profile.password'))->toBeTrue()
        ->and(Route::has('backoffice-admin.profile.two-factor-authentication'))->toBeTrue()
        ->and(Route::has('backoffice-admin.profile.browser-sessions'))->toBeTrue();
});

it('renders links to profile sections', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Navigation User',
        'email' => 'navigation@example.com',
        'password' => 'secret',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->get('/admin/profile')
        ->assertOk()
        ->assertSee('/admin/profile/password', false)
        ->assertSee('/admin/profile/two-factor-authentication', false)
        ->assertSee('/admin/profile/browser-sessions', false);
});

it('updates authenticated user name', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Old Name',
        'email' => 'old-name@example.com',
        'password' => 'secret',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->from('/admin/profile')
        ->post('/admin/profile', [
            'name' => 'New Name',
            'email' => 'old-name@example.com',
        ])
        ->assertRedirect('/admin/profile');

    expect($user->fresh()?->name)->toBe('New Name');
});

it('updates authenticated user email', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Same Name',
        'email' => 'before@example.com',
        'password' => 'secret',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->from('/admin/profile')
        ->post('/admin/profile', [
            'name' => 'Same Name',
            'email' => 'after@example.com',
        ])
        ->assertRedirect('/admin/profile');

    expect($user->fresh()?->email)->toBe('after@example.com');
});

it('shows validation error for invalid email', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Validation User',
        'email' => 'validation@example.com',
        'password' => 'secret',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->from('/admin/profile')
        ->post('/admin/profile', [
            'name' => 'Validation User',
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors('email');
});

it('supports custom prefix for profile update route', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    $user = ProfileTestUser::query()->create([
        'name' => 'Backoffice Update',
        'email' => 'backoffice-update@example.com',
        'password' => 'secret',
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->from('/backoffice/profile')
        ->post('/backoffice/profile', [
            'name' => 'Backoffice Updated',
            'email' => 'backoffice-updated@example.com',
        ])
        ->assertRedirect('/backoffice/profile');

    expect($user->fresh()?->name)->toBe('Backoffice Updated')
        ->and($user->fresh()?->email)->toBe('backoffice-updated@example.com');
});

it('updates password with correct current password', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Password Update User',
        'email' => 'password-update@example.com',
        'password' => Hash::make('old-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->from('/admin/profile/password')
        ->post('/admin/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect('/admin/profile/password');

    expect(Hash::check('new-password-123', (string) $user->fresh()?->password))->toBeTrue();
});

it('does not update password with wrong current password', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Wrong Current Password User',
        'email' => 'wrong-current@example.com',
        'password' => Hash::make('old-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->from('/admin/profile/password')
        ->post('/admin/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('old-password', (string) $user->fresh()?->password))->toBeTrue();
});

it('requires password confirmation for password update', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Password Confirmation User',
        'email' => 'password-confirmation@example.com',
        'password' => Hash::make('old-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->from('/admin/profile/password')
        ->post('/admin/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ])
        ->assertSessionHasErrors('password');
});

it('supports custom prefix for password update route', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    $user = ProfileTestUser::query()->create([
        'name' => 'Backoffice Password User',
        'email' => 'backoffice-password@example.com',
        'password' => Hash::make('old-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->from('/backoffice/profile/password')
        ->post('/backoffice/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect('/backoffice/profile/password');

    expect(Hash::check('new-password-123', (string) $user->fresh()?->password))->toBeTrue();
});

it('logs out other browser sessions with valid password', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Logout Sessions User',
        'email' => 'logout-sessions@example.com',
        'password' => Hash::make('current-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->from('/admin/profile/browser-sessions')
        ->post('/admin/profile/browser-sessions', [
            'current_password' => 'current-password',
        ])
        ->assertRedirect('/admin/profile/browser-sessions');

    pest_test_case()->assertAuthenticatedAs($user, 'web');
});

it('shows validation error for invalid password when logging out other browser sessions', function (): void {
    $user = ProfileTestUser::query()->create([
        'name' => 'Invalid Password Sessions User',
        'email' => 'invalid-sessions@example.com',
        'password' => Hash::make('current-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->from('/admin/profile/browser-sessions')
        ->post('/admin/profile/browser-sessions', [
            'current_password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('current_password');
});

it('supports custom prefix for browser sessions logout route', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, force: true);
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    $user = ProfileTestUser::query()->create([
        'name' => 'Backoffice Sessions User',
        'email' => 'backoffice-sessions@example.com',
        'password' => Hash::make('current-password'),
    ]);

    pest_test_case()->actingAs($user, 'web');

    pest_test_case()->from('/backoffice/profile/browser-sessions')
        ->post('/backoffice/profile/browser-sessions', [
            'current_password' => 'current-password',
        ])
        ->assertRedirect('/backoffice/profile/browser-sessions');
});

/**
 * @property string $name
 * @property string $email
 * @property string $password
 */
final class ProfileTestUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}
