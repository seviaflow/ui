<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;
use PragmaRX\Google2FA\Google2FA;
use SeviaFlow\Ui\Auth\TwoFactorAuthenticationManager;
use SeviaFlow\Ui\SeviaFlowUiServiceProvider;

beforeEach(function (): void {
    Schema::create('fortify_panel_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->timestamps();
    });

    config()->set('auth.providers.users.model', FortifyPanelUser::class);
    config()->set('fortify.features', [Features::twoFactorAuthentication(['confirm' => true])]);
    config()->set('seviaflow-ui.auth.login_throttle.max_attempts', 100);
    config()->set('seviaflow-ui.auth.two_factor_login.verify', null);
    config()->set('seviaflow-ui.auth.two_factor_login.consume_recovery_code', null);
});

function fortifyPanelUser(bool $confirmed = true): FortifyPanelUser
{
    $user = FortifyPanelUser::query()->create([
        'name' => 'Fortify Panel User',
        'email' => 'fortify-panel@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    if ($confirmed) {
        $manager = app(TwoFactorAuthenticationManager::class);
        expect($manager->enable($user))->toBeTrue();
        // Login fixtures must not pre-consume the same TOTP through setup confirmation.
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
    }

    return $user;
}

function fortifyPanelOtp(FortifyPanelUser $user): string
{
    $secret = Fortify::currentEncrypter()->decrypt($user->getAttribute('two_factor_secret'));

    return (new Google2FA)->getCurrentOtp($secret);
}

function startFortifyPanelLogin(FortifyPanelUser $user): void
{
    Auth::forgetGuards();
    pest_test_case()->post('/admin/login', [
        'email' => $user->getAttribute('email'),
        'password' => 'secret-password',
    ])->assertRedirect('/admin/login/two-factor');
    pest_test_case()->assertGuest('web');
}

it('completes password and real encrypted Fortify TOTP login', function (): void {
    $user = fortifyPanelUser();
    startFortifyPanelLogin($user);

    pest_test_case()->get('/admin')->assertRedirect('/admin/login');
    pest_test_case()->get('/admin/login/two-factor')->assertOk();
    pest_test_case()->post('/admin/login/two-factor', ['code' => 'invalid'])
        ->assertSessionHasErrors('code')
        ->assertSessionMissing('_old_input.code');
    pest_test_case()->assertGuest('web');

    pest_test_case()->post('/admin/login/two-factor', ['code' => fortifyPanelOtp($user)])
        ->assertRedirect('/admin')
        ->assertSessionMissing('seviaflow.auth.pending_two_factor');
    pest_test_case()->assertAuthenticatedAs($user, 'web');
});

it('consumes a real recovery code once and rejects its reuse', function (): void {
    $user = fortifyPanelUser();
    $code = $user->recoveryCodes()[0];
    startFortifyPanelLogin($user);

    pest_test_case()->post('/admin/login/two-factor', ['code' => $code])->assertRedirect('/admin');
    pest_test_case()->assertAuthenticatedAs($user, 'web');
    expect($user->fresh()->recoveryCodes())->not->toContain($code);

    pest_test_case()->post('/admin/logout')->assertRedirect('/admin/login');
    startFortifyPanelLogin($user);
    pest_test_case()->post('/admin/login/two-factor', ['code' => $code])->assertSessionHasErrors('code');
    pest_test_case()->assertGuest('web');
});

it('supports setup confirmation regeneration and disabling through profile routes', function (): void {
    $user = fortifyPanelUser(false);
    pest_test_case()->actingAs($user, 'web');
    $profile = '/admin/profile/two-factor-authentication';

    pest_test_case()->get($profile)->assertOk()->assertSee('Enable two-factor authentication');
    pest_test_case()->from($profile)->post($profile, ['current_password' => 'wrong'])
        ->assertSessionHasErrors('current_password');
    expect($user->fresh()->getAttribute('two_factor_secret'))->toBeNull();

    pest_test_case()->from($profile)->post($profile, ['current_password' => 'secret-password'])
        ->assertRedirect($profile);
    $user->refresh();
    expect($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
    pest_test_case()->get($profile)->assertOk()->assertSee('Scan QR code')
        ->assertSee('Confirm two-factor authentication')->assertDontSee('Regenerate recovery codes');

    pest_test_case()->from($profile)->post($profile.'/confirm', [
        'current_password' => 'secret-password', 'code' => 'invalid',
    ])->assertSessionHasErrors('code');
    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
    pest_test_case()->from($profile)->post($profile.'/confirm', [
        'current_password' => 'wrong', 'code' => fortifyPanelOtp($user),
    ])->assertSessionHasErrors('current_password');
    pest_test_case()->from($profile)->post($profile.'/confirm', [
        'current_password' => 'secret-password', 'code' => fortifyPanelOtp($user),
    ])->assertRedirect($profile);
    $user->refresh();
    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue();
    pest_test_case()->get($profile)->assertOk()->assertSee('Two-factor authentication is enabled.')
        ->assertSee('Recovery codes');

    $oldCodes = $user->recoveryCodes();
    pest_test_case()->from($profile)->post($profile.'/recovery-codes', ['current_password' => 'wrong'])
        ->assertSessionHasErrors('current_password');
    expect($user->fresh()->recoveryCodes())->toBe($oldCodes);
    pest_test_case()->from($profile)->post($profile.'/recovery-codes', ['current_password' => 'secret-password'])
        ->assertRedirect($profile);
    expect($user->fresh()->recoveryCodes())->not->toBe($oldCodes);

    pest_test_case()->from($profile)->delete($profile, ['current_password' => 'secret-password'])
        ->assertSessionHasErrors('confirm_disable');
    pest_test_case()->from($profile)->delete($profile, ['current_password' => 'wrong', 'confirm_disable' => '1'])
        ->assertSessionHasErrors('current_password');
    pest_test_case()->from($profile)->delete($profile, ['current_password' => 'secret-password', 'confirm_disable' => '1'])
        ->assertRedirect($profile);
    $user->refresh();
    expect($user->hasEnabledTwoFactorAuthentication())->toBeFalse()
        ->and($user->getAttribute('two_factor_secret'))->toBeNull()
        ->and($user->getAttribute('two_factor_recovery_codes'))->toBeNull();
});

it('expires the challenge and requires password authentication again', function (): void {
    config()->set('seviaflow-ui.auth.two_factor_login.challenge_timeout_seconds', 1);
    $user = fortifyPanelUser();
    startFortifyPanelLogin($user);
    pest_test_case()->travel(2)->seconds();

    pest_test_case()->post('/admin/login/two-factor', ['code' => fortifyPanelOtp($user)])
        ->assertSessionHasErrors('code')->assertSessionMissing('seviaflow.auth.pending_two_factor');
    pest_test_case()->assertGuest('web');
    pest_test_case()->get('/admin/login/two-factor')->assertRedirect('/admin/login');
});

it('invalidates pending login after a password change or access revocation', function (string $change): void {
    $user = fortifyPanelUser();
    startFortifyPanelLogin($user);

    if ($change === 'password') {
        $user->forceFill(['password' => Hash::make('changed-password')])->save();
    } else {
        config()->set('seviaflow.permissions.admin_gate', static fn (): bool => false);
    }

    pest_test_case()->post('/admin/login/two-factor', ['code' => fortifyPanelOtp($user)])
        ->assertSessionHasErrors('code')->assertSessionMissing('seviaflow.auth.pending_two_factor');
    pest_test_case()->assertGuest('web');
})->with(['password', 'access']);

it('clears a previous challenge when a new password login fails', function (): void {
    $user = fortifyPanelUser();
    startFortifyPanelLogin($user);
    pest_test_case()->post('/admin/login', ['email' => $user->getAttribute('email'), 'password' => 'wrong'])
        ->assertSessionMissing('seviaflow.auth.pending_two_factor');
    pest_test_case()->post('/admin/login/two-factor', ['code' => fortifyPanelOtp($user)])
        ->assertSessionHasErrors('code');
    pest_test_case()->assertGuest('web');
});

it('throttles the second factor independently of password login throttling', function (): void {
    config()->set('seviaflow-ui.auth.login_throttle.enabled', false);
    config()->set('seviaflow-ui.auth.login_throttle.max_attempts', 2);
    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, true);
    $user = fortifyPanelUser();
    startFortifyPanelLogin($user);

    pest_test_case()->post('/admin/login/two-factor', ['code' => 'invalid'])->assertSessionHasErrors('code');
    pest_test_case()->post('/admin/login/two-factor', ['code' => 'invalid'])->assertSessionHasErrors('code');
    pest_test_case()->post('/admin/login/two-factor', ['code' => fortifyPanelOtp($user)])->assertTooManyRequests();
    pest_test_case()->assertGuest('web');
});

it('supports Fortify configurations without setup confirmation', function (): void {
    config()->set('fortify.features', [Features::twoFactorAuthentication(['confirm' => false])]);
    $user = fortifyPanelUser(false);
    $manager = app(TwoFactorAuthenticationManager::class);

    expect($manager->enable($user))->toBeTrue()
        ->and($manager->enabled($user))->toBeTrue()
        ->and($manager->setupPending($user))->toBeFalse();
    startFortifyPanelLogin($user);
    pest_test_case()->post('/admin/login/two-factor', ['code' => fortifyPanelOtp($user)])->assertRedirect('/admin');
    pest_test_case()->assertAuthenticatedAs($user, 'web');
});

it('fails closed on malformed encrypted secrets', function (): void {
    $user = fortifyPanelUser();
    $user->forceFill(['two_factor_secret' => 'not-an-encrypted-secret'])->save();
    startFortifyPanelLogin($user);
    pest_test_case()->post('/admin/login/two-factor', ['code' => '123456'])->assertSessionHasErrors('code');
    pest_test_case()->assertGuest('web');
});

it('preserves Fortify protection against replaying an accepted TOTP', function (): void {
    $user = fortifyPanelUser();
    $code = fortifyPanelOtp($user);
    $manager = app(TwoFactorAuthenticationManager::class);

    expect($manager->verifyLoginChallenge($user, $code))->toBeTrue()
        ->and($manager->verifyLoginChallenge($user, $code))->toBeFalse();
});

it('does not spend login or OTP attempts when rendering authentication forms', function (): void {
    config()->set('seviaflow-ui.auth.login_throttle.max_attempts', 3);
    $user = fortifyPanelUser();
    startFortifyPanelLogin($user);

    for ($visit = 0; $visit < 5; $visit++) {
        pest_test_case()->get('/admin/login/two-factor')->assertOk();
    }

    for ($attempt = 0; $attempt < 2; $attempt++) {
        pest_test_case()->from('/admin/login/two-factor')->post('/admin/login/two-factor', ['code' => 'invalid'])
            ->assertSessionHasErrors('code');
        pest_test_case()->get('/admin/login/two-factor')->assertOk();
    }

    pest_test_case()->post('/admin/login/two-factor', ['code' => fortifyPanelOtp($user)])->assertRedirect('/admin');
    pest_test_case()->assertAuthenticatedAs($user, 'web');
});

it('does not flash invalid challenge payloads into session input', function (): void {
    $user = fortifyPanelUser();
    startFortifyPanelLogin($user);

    pest_test_case()->post('/admin/login/two-factor', ['code' => str_repeat('1', 256)])
        ->assertSessionHasErrors('code')->assertSessionMissing('_old_input.code');
    pest_test_case()->assertGuest('web');
});

it('uses the configured panel guard and custom route prefixes', function (): void {
    config()->set('auth.guards.panel', ['driver' => 'session', 'provider' => 'users']);
    config()->set('seviaflow-ui.auth.guard', 'panel');
    config()->set('seviaflow-ui.route_prefix', 'backoffice');
    config()->set('seviaflow-ui.route_name_prefix', 'backoffice.');
    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, true);
    $user = fortifyPanelUser();

    pest_test_case()->post('/backoffice/login', [
        'email' => $user->getAttribute('email'), 'password' => 'secret-password', 'remember' => true,
    ])->assertRedirect('/backoffice/login/two-factor');
    pest_test_case()->assertGuest('panel');
    pest_test_case()->get('/backoffice/login/two-factor')->assertOk();
    pest_test_case()->post('/backoffice/login/two-factor', ['code' => fortifyPanelOtp($user)])
        ->assertRedirect('/backoffice');
    pest_test_case()->assertAuthenticatedAs($user, 'panel');
    pest_test_case()->assertGuest('web');
});

final class FortifyPanelUser extends AuthenticatableUser
{
    use TwoFactorAuthenticatable;

    protected $table = 'fortify_panel_users';

    protected $guarded = [];
}
