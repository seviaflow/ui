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

        return back()->with('status', __('seviaflow-ui::panel.profile_information_updated_successfully'));
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

        return back()->with('status', __('seviaflow-ui::panel.password_updated_successfully'));
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
            return back()->withErrors(['two_factor_authentication' => __('seviaflow-ui::panel.two_factor_authentication_is_disabled_by_configuration')]);
        }

        if (! $this->twoFactorAuthenticationManager->enable($user)) {
            return back()->withErrors(['two_factor_authentication' => __('seviaflow-ui::panel.two_factor_authentication_backend_support_is_not_available')]);
        }

        return back()->with('status', $this->twoFactorAuthenticationManager->setupPending($user)
            ? __('seviaflow-ui::panel.scan_the_qr_code_and_confirm_setup_with_an_authentication_code')
            : __('seviaflow-ui::panel.two_factor_authentication_enabled_successfully'));
    }

    public function confirmTwoFactorAuthentication(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser();
        $this->validateFreshPassword($request);
        $validated = $request->validate(['code' => ['required', 'string', 'max:255']]);

        if (! $this->twoFactorFeatureEnabled() || ! $this->twoFactorAuthenticationManager->confirm($user, $validated['code'])) {
            return back()->withErrors(['code' => __('seviaflow-ui::panel.the_verification_code_is_invalid_or_setup_is_unavailable')]);
        }

        return back()->with('status', __('seviaflow-ui::panel.two_factor_authentication_enabled_successfully'));
    }

    public function disableTwoFactorAuthentication(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser();

        $request->validate([
            'confirm_disable' => ['accepted'],
        ], [
            'confirm_disable.accepted' => __('seviaflow-ui::panel.please_confirm_that_you_want_to_disable_two_factor_authentication'),
        ]);
        $this->validateFreshPassword($request);

        if (! $this->twoFactorFeatureEnabled()) {
            return back()->withErrors(['two_factor_authentication' => __('seviaflow-ui::panel.two_factor_authentication_is_disabled_by_configuration')]);
        }

        if (! $this->twoFactorAuthenticationManager->disable($user)) {
            return back()->withErrors(['two_factor_authentication' => __('seviaflow-ui::panel.two_factor_authentication_backend_support_is_not_available')]);
        }

        return back()->with('status', __('seviaflow-ui::panel.two_factor_authentication_disabled_successfully'));
    }

    public function regenerateTwoFactorRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser();

        $this->validateFreshPassword($request);

        if (! $this->twoFactorFeatureEnabled()) {
            return back()->withErrors(['two_factor_authentication' => __('seviaflow-ui::panel.two_factor_authentication_is_disabled_by_configuration')]);
        }

        if (! $this->twoFactorAuthenticationManager->regenerateRecoveryCodes($user)) {
            return back()->withErrors(['two_factor_authentication' => __('seviaflow-ui::panel.two_factor_authentication_backend_support_is_not_available')]);
        }

        return back()->with('status', __('seviaflow-ui::panel.recovery_codes_regenerated_successfully'));
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
                    'browser_sessions' => __('seviaflow-ui::panel.log_out_other_browser_sessions_is_not_available_for_the_current_authentication_setup'),
                ]);
            }
        } catch (Throwable) {
            return back()->withErrors([
                'browser_sessions' => __('seviaflow-ui::panel.log_out_other_browser_sessions_is_not_available_for_the_current_authentication_setup'),
            ]);
        }

        return back()->with('status', __('seviaflow-ui::panel.logged_out_from_other_browser_sessions_successfully'));
    }

    private function render(string $section): View
    {
        /** @var array<string, array{title: string, description: string}> $content */
        $content = [
            'profile' => [
                'title' => __('seviaflow-ui::panel.profile_information'),
                'description' => __('seviaflow-ui::panel.update_your_account_details_so_your_team_can_identify_your_account_clearly'),
            ],
            'password' => [
                'title' => __('seviaflow-ui::panel.password'),
                'description' => __('seviaflow-ui::panel.use_a_strong_password_and_change_it_regularly_to_keep_your_admin_account_secure'),
            ],
            'two_factor_authentication' => [
                'title' => __('seviaflow-ui::panel.two_factor_authentication'),
                'description' => __('seviaflow-ui::panel.add_an_extra_sign_in_verification_step_for_stronger_account_protection'),
            ],
            'browser_sessions' => [
                'title' => __('seviaflow-ui::panel.browser_sessions'),
                'description' => __('seviaflow-ui::panel.review_active_sessions_and_sign_out_from_devices_you_no_longer_use'),
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
