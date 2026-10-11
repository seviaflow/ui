<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Http\Controllers\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use SeviaFlow\Ui\Auth\TwoFactorAuthenticationManager;
use Throwable;

final class ProfileController
{
    public function __construct(
        private readonly TwoFactorAuthenticationManager $twoFactorAuthenticationManager
    ) {}

    public function index(): View
    {
        return $this->render('profile');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser();

        $validated = $request->validate([
            'name' => ['required', 'string'],
            'email' => [
                'required',
                'email',
                Rule::unique($user->getTable(), 'email')->ignore($user->getKey(), $user->getKeyName()),
            ],
        ]);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ])->save();

        return back()->with('status', 'Profile information updated successfully.');
    }

    public function password(): View
    {
        return $this->render('password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $guard = (string) config('seviaflow-ui.auth.guard', 'web');
        $user = $this->authenticatedUser();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password:'.$guard],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return back()->with('status', 'Password updated successfully.');
    }

    public function twoFactorAuthentication(): View
    {
        return $this->render('two_factor_authentication');
    }

    public function enableTwoFactorAuthentication(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser();

        $this->validateFreshPassword($request);

        if (! $this->twoFactorFeatureEnabled()) {
            return back()->withErrors(['two_factor_authentication' => 'Two-factor authentication is disabled by configuration.']);
        }

        if (! $this->twoFactorAuthenticationManager->enable($user)) {
            return back()->withErrors(['two_factor_authentication' => 'Two-factor authentication backend support is not available.']);
        }

        return back()->with('status', $this->twoFactorAuthenticationManager->setupPending($user)
            ? 'Scan the QR code and confirm setup with an authentication code.'
            : 'Two-factor authentication enabled successfully.');
    }

    public function confirmTwoFactorAuthentication(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser();
        $this->validateFreshPassword($request);
        $validated = $request->validate(['code' => ['required', 'string', 'max:255']]);

        if (! $this->twoFactorFeatureEnabled() || ! $this->twoFactorAuthenticationManager->confirm($user, $validated['code'])) {
            return back()->withErrors(['code' => 'The verification code is invalid or setup is unavailable.']);
        }

        return back()->with('status', 'Two-factor authentication enabled successfully.');
    }

    public function disableTwoFactorAuthentication(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser();

        $request->validate([
            'confirm_disable' => ['accepted'],
        ], [
            'confirm_disable.accepted' => 'Please confirm that you want to disable two-factor authentication.',
        ]);
        $this->validateFreshPassword($request);

        if (! $this->twoFactorFeatureEnabled()) {
            return back()->withErrors(['two_factor_authentication' => 'Two-factor authentication is disabled by configuration.']);
        }

        if (! $this->twoFactorAuthenticationManager->disable($user)) {
            return back()->withErrors(['two_factor_authentication' => 'Two-factor authentication backend support is not available.']);
        }

        return back()->with('status', 'Two-factor authentication disabled successfully.');
    }

    public function regenerateTwoFactorRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser();

        $this->validateFreshPassword($request);

        if (! $this->twoFactorFeatureEnabled()) {
            return back()->withErrors(['two_factor_authentication' => 'Two-factor authentication is disabled by configuration.']);
        }

        if (! $this->twoFactorAuthenticationManager->regenerateRecoveryCodes($user)) {
            return back()->withErrors(['two_factor_authentication' => 'Two-factor authentication backend support is not available.']);
        }

        return back()->with('status', 'Recovery codes regenerated successfully.');
    }

    public function browserSessions(): View
    {
        return $this->render('browser_sessions');
    }

    public function logoutOtherBrowserSessions(Request $request): RedirectResponse
    {
        $guard = (string) config('seviaflow-ui.auth.guard', 'web');

        $request->validate([
            'current_password' => ['required', 'current_password:'.$guard],
        ]);

        try {
            $authGuard = Auth::guard($guard);

            if (method_exists($authGuard, 'logoutOtherDevices')) {
                $authGuard->logoutOtherDevices((string) $request->string('current_password'));
            } elseif (method_exists(Auth::class, 'logoutOtherDevices')) {
                Auth::logoutOtherDevices((string) $request->string('current_password'));
            } else {
                return back()->withErrors([
                    'browser_sessions' => 'Log out other browser sessions is not available for the current authentication setup.',
                ]);
            }
        } catch (Throwable) {
            return back()->withErrors([
                'browser_sessions' => 'Log out other browser sessions is not available for the current authentication setup.',
            ]);
        }

        return back()->with('status', 'Logged out from other browser sessions successfully.');
    }

    private function render(string $section): View
    {
        /** @var array<string, array{title: string, description: string}> $content */
        $content = [
            'profile' => [
                'title' => 'Profile information',
                'description' => 'Update your account details so your team can identify your account clearly.',
            ],
            'password' => [
                'title' => 'Password',
                'description' => 'Use a strong password and change it regularly to keep your admin account secure.',
            ],
            'two_factor_authentication' => [
                'title' => 'Two-factor authentication',
                'description' => 'Add an extra sign-in verification step for stronger account protection.',
            ],
            'browser_sessions' => [
                'title' => 'Browser sessions',
                'description' => 'Review active sessions and sign out from devices you no longer use.',
            ],
        ];

        return view('seviaflow-ui::profile.show', [
            'section' => $section,
            'title' => $content[$section]['title'] ?? $content['profile']['title'],
            'description' => $content[$section]['description'] ?? $content['profile']['description'],
            'twoFactorFeatureEnabled' => $this->twoFactorFeatureEnabled(),
            'twoFactorBackendAvailable' => $this->twoFactorAuthenticationManager->available($this->authenticatedUser()),
            'twoFactorEnabled' => $section === 'two_factor_authentication' ? $this->twoFactorAuthenticationManager->enabled($this->authenticatedUser()) : false,
            'twoFactorSetupPending' => $section === 'two_factor_authentication' ? $this->twoFactorAuthenticationManager->setupPending($this->authenticatedUser()) : false,
            'twoFactorQrCodeSvg' => $section === 'two_factor_authentication' ? $this->twoFactorAuthenticationManager->qrCodeSvg($this->authenticatedUser()) : null,
            'twoFactorRecoveryCodes' => $section === 'two_factor_authentication' ? $this->twoFactorAuthenticationManager->recoveryCodes($this->authenticatedUser()) : [],
        ]);
    }

    private function authenticatedUser(): Model
    {
        $guard = (string) config('seviaflow-ui.auth.guard', 'web');
        $user = Auth::guard($guard)->user();

        if (! $user instanceof Model) {
            abort(403);
        }

        return $user;
    }

    private function twoFactorFeatureEnabled(): bool
    {
        return (bool) config('seviaflow-ui.auth.two_factor_authentication', true);
    }

    private function validateFreshPassword(Request $request): void
    {
        $guard = (string) config('seviaflow-ui.auth.guard', 'web');

        $request->validate([
            'current_password' => ['required', 'current_password:'.$guard],
        ]);
    }
}
