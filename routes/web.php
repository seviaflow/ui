<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SeviaFlow\Ui\Http\Controllers\Auth\ForgotPasswordController;
use SeviaFlow\Ui\Http\Controllers\Auth\LoginController;
use SeviaFlow\Ui\Http\Controllers\Auth\ProfileController;
use SeviaFlow\Ui\Http\Controllers\Auth\ResetPasswordController;
use SeviaFlow\Ui\Http\Controllers\ResourceFileController;
use SeviaFlow\Ui\Livewire\AdminUserIndex;
use SeviaFlow\Ui\Livewire\AdminUserRolesForm;
use SeviaFlow\Ui\Livewire\Dashboard;
use SeviaFlow\Ui\Livewire\ResourceForm;
use SeviaFlow\Ui\Livewire\ResourceIndex;
use SeviaFlow\Ui\Livewire\ResourceShow;
use SeviaFlow\Ui\Livewire\RoleForm;
use SeviaFlow\Ui\Livewire\RoleIndex;

/** @var string $routeNamePrefix */
$routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');

/**
 * @return array<int, string>
 */
$resolveAdminMiddleware = static function (): array {
    /** @var mixed $adminMiddleware */
    $adminMiddleware = config('seviaflow-ui.admin_middleware');
    /** @var mixed $legacyMiddleware */
    $legacyMiddleware = config('seviaflow-ui.middleware');

    if (is_array($adminMiddleware) && $adminMiddleware !== []) {
        return array_values(array_filter($adminMiddleware, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }

    if (is_array($legacyMiddleware) && $legacyMiddleware !== []) {
        return array_values(array_filter($legacyMiddleware, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }

    return ['web', 'auth'];
};

/**
 * @return array<int, string>
 */
$resolveAuthMiddleware = static function (): array {
    /** @var mixed $authMiddleware */
    $authMiddleware = config('seviaflow-ui.auth_middleware');

    if (is_array($authMiddleware) && $authMiddleware !== []) {
        return array_values(array_filter($authMiddleware, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }

    return ['web'];
};

Route::prefix(config('seviaflow-ui.route_prefix', 'admin'))
    ->as($routeNamePrefix)
    ->group(function () use ($resolveAdminMiddleware, $resolveAuthMiddleware): void {
        $guard = (string) config('seviaflow-ui.auth.guard', 'web');
        $adminMiddleware = $resolveAdminMiddleware();
        $authMiddleware = $resolveAuthMiddleware();
        $adminMiddleware = array_values(array_map(
            static fn (string $middleware): string => $middleware === 'auth'
                ? 'seviaflow.auth'
                : ($middleware === 'auth:'.$guard ? 'seviaflow.auth:'.$guard : $middleware),
            $adminMiddleware
        ));

        $hasAuthMiddleware = collect($adminMiddleware)
            ->contains(static fn (string $middleware): bool => $middleware === 'seviaflow.auth' || $middleware === 'seviaflow.auth:'.$guard);

        if (! $hasAuthMiddleware) {
            $adminMiddleware[] = 'seviaflow.auth:'.$guard;
        }

        $adminMiddleware = array_values(array_unique($adminMiddleware));
        $publicAuthMiddleware = array_values(array_unique(array_merge($authMiddleware, ['seviaflow.guest:'.$guard])));
        if ((bool) config('seviaflow-ui.auth.login_throttle.enabled', true)) {
            $publicAuthMiddleware[] = 'throttle:seviaflow.login';
        }
        $publicAuthMiddleware = array_values(array_unique($publicAuthMiddleware));
        $authEnabled = (bool) config('seviaflow-ui.auth.enabled', true);

        Route::middleware($adminMiddleware)->group(function (): void {
            if ((bool) config('seviaflow-ui.dashboard.enabled', true)) {
                Route::get('/', Dashboard::class)
                    ->name('dashboard');
                Route::get('/dashboards/{dashboard}', Dashboard::class)
                    ->name('dashboards.show');
            }

            Route::get('/resources/{resource}/create', ResourceForm::class)
                ->name('resources.create');
            Route::get('/resources/{resource}/{record}/edit', ResourceForm::class)
                ->name('resources.edit');
            Route::get('/resources/{resource}/{record}/files/{field}', [ResourceFileController::class, 'download'])
                ->name('resources.files.download');
            Route::get('/resources/{resource}/{record}', ResourceShow::class)
                ->name('resources.show');
            Route::get('/resources/{resource}', ResourceIndex::class)
                ->name('resources.index');

            Route::get('/settings/roles', RoleIndex::class)
                ->name('settings.roles.index');
            Route::get('/settings/roles/create', RoleForm::class)
                ->name('settings.roles.create');
            Route::get('/settings/roles/{role}/edit', RoleForm::class)
                ->name('settings.roles.edit');
            Route::get('/settings/admin-users', AdminUserIndex::class)
                ->name('settings.admin-users.index');
            Route::get('/settings/admin-users/{user}/roles', AdminUserRolesForm::class)
                ->name('settings.admin-users.roles');

            Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
        });

        if ((bool) config('seviaflow-ui.auth.enabled', true)) {
            Route::middleware($publicAuthMiddleware)->group(function (): void {
                Route::get('/login', [LoginController::class, 'create'])->name('login');
                Route::post('/login', [LoginController::class, 'store'])->name('login.store');
                Route::get('/login/two-factor', [LoginController::class, 'twoFactor'])->name('login.two-factor');
                Route::post('/login/two-factor', [LoginController::class, 'verifyTwoFactor'])
                    ->middleware('throttle:seviaflow.two-factor')
                    ->name('login.two-factor.verify');

                Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
                Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

                Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
                Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
            });

            Route::middleware($adminMiddleware)->group(function (): void {
                Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
                Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
                Route::get('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
                Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
                Route::get('/profile/two-factor-authentication', [ProfileController::class, 'twoFactorAuthentication'])->name('profile.two-factor-authentication');
                Route::post('/profile/two-factor-authentication', [ProfileController::class, 'enableTwoFactorAuthentication'])->name('profile.two-factor-authentication.enable');
                Route::delete('/profile/two-factor-authentication', [ProfileController::class, 'disableTwoFactorAuthentication'])->name('profile.two-factor-authentication.disable');
                Route::post('/profile/two-factor-authentication/recovery-codes', [ProfileController::class, 'regenerateTwoFactorRecoveryCodes'])->name('profile.two-factor-authentication.recovery-codes.regenerate');
                Route::get('/profile/browser-sessions', [ProfileController::class, 'browserSessions'])->name('profile.browser-sessions');
                Route::post('/profile/browser-sessions', [ProfileController::class, 'logoutOtherBrowserSessions'])->name('profile.browser-sessions.logout');
            });
        }
    });
