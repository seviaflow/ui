<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Http\Controllers\Auth;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use SeviaFlow\Core\Auth\AdminAccessGate;
use SeviaFlow\Ui\Auth\TwoFactorAuthenticationManager;

final class LoginController
{
    public function __construct(
        private readonly TwoFactorAuthenticationManager $twoFactorAuthenticationManager,
        private readonly AdminAccessGate $adminAccessGate,
    ) {}

    public function create(): View|RedirectResponse
    {
        $guard = (string) config('seviaflow-ui.auth.guard', 'web');
        $user = Auth::guard($guard)->user();
        if ($user !== null && $this->adminAccessGate->allows($user)) {
            $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');

            return redirect()->route($routeNamePrefix.'dashboard');
        }

        return view('seviaflow-ui::auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->session()->forget('seviaflow.auth.pending_two_factor');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guard = (string) config('seviaflow-ui.auth.guard', 'web');
        $remember = $request->boolean('remember');

        $authGuard = Auth::guard($guard);
        $authenticated = method_exists($authGuard, 'once')
            ? (bool) $authGuard->once($credentials)
            : false;

        if (! $authenticated) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        $user = $authGuard->user();
        if (! $user instanceof Model || ! $user instanceof Authenticatable || ! $this->adminAccessGate->allows($user)) {
            $authGuard->logout();

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'These credentials do not provide admin access.']);
        }

        if ($this->twoFactorAuthenticationManager->challengeRequired($user)) {
            if (! $this->twoFactorAuthenticationManager->loginChallengeAvailable($user)) {
                $authGuard->logout();

                return back()
                    ->withInput($request->only('email', 'remember'))
                    ->withErrors(['email' => 'Two-factor authentication cannot be completed for this account.']);
            }

            $authGuard->logout();
            $request->session()->regenerate();
            $request->session()->put('seviaflow.auth.pending_two_factor', [
                'guard' => $guard,
                'id' => (string) $user->getAuthIdentifier(),
                'remember' => $remember,
                'expires_at' => now()->timestamp + max(1, (int) config('seviaflow-ui.auth.two_factor_login.challenge_timeout_seconds', 300)),
                'password_fingerprint' => hash('sha256', $user->getAuthPassword()),
            ]);

            $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');

            return redirect()->route($routeNamePrefix.'login.two-factor');
        }

        $authGuard->login($user, $remember);
        $request->session()->regenerate();

        $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');
        $defaultRedirect = route($routeNamePrefix.'dashboard');
        $configuredRedirect = config('seviaflow-ui.auth.redirect_after_login');

        if (is_string($configuredRedirect) && trim($configuredRedirect) !== '') {
            $defaultRedirect = $configuredRedirect;
        }

        return redirect()->intended($defaultRedirect);
    }

    public function twoFactor(Request $request): View|RedirectResponse
    {
        if ($this->pendingUser($request) === null) {
            $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');

            return redirect()->route($routeNamePrefix.'login');
        }

        return view('seviaflow-ui::auth.two-factor');
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);
        $user = $this->pendingUser($request);

        if (! $user instanceof Model || ! $user instanceof Authenticatable || ! $this->twoFactorAuthenticationManager->verifyLoginChallenge($user, (string) $validated['code'])) {
            return back()->withErrors(['code' => 'The verification code is invalid or has expired.']);
        }

        $pending = $request->session()->pull('seviaflow.auth.pending_two_factor', []);
        $guard = (string) ($pending['guard'] ?? config('seviaflow-ui.auth.guard', 'web'));
        $remember = (bool) ($pending['remember'] ?? false);

        if (! $this->adminAccessGate->allows($user)) {
            Auth::guard($guard)->logout();

            return redirect()->route((string) config('seviaflow-ui.route_name_prefix', 'seviaflow.').'login')
                ->withErrors(['email' => 'These credentials do not provide admin access.']);
        }

        Auth::guard($guard)->login($user, $remember);
        $request->session()->regenerate();

        $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');

        return redirect()->intended(route($routeNamePrefix.'dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $guard = (string) config('seviaflow-ui.auth.guard', 'web');

        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');
        $configuredRedirect = config('seviaflow-ui.auth.redirect_after_logout');
        if (is_string($configuredRedirect) && trim($configuredRedirect) !== '') {
            return redirect($configuredRedirect);
        }

        if ((bool) config('seviaflow-ui.auth.enabled', true) && app('router')->has($routeNamePrefix.'login')) {
            return redirect()->route($routeNamePrefix.'login');
        }

        return redirect('/');
    }

    private function pendingUser(Request $request): ?Model
    {
        $pending = $request->session()->get('seviaflow.auth.pending_two_factor');
        if (! is_array($pending)) {
            return null;
        }

        if (! is_numeric($pending['expires_at'] ?? null) || (int) $pending['expires_at'] <= now()->timestamp) {
            $request->session()->forget('seviaflow.auth.pending_two_factor');

            return null;
        }

        $guardName = (string) ($pending['guard'] ?? config('seviaflow-ui.auth.guard', 'web'));
        $id = $pending['id'] ?? null;
        if (! is_scalar($id) || (string) $id === '') {
            return null;
        }

        $providerName = config('auth.guards.'.$guardName.'.provider');
        if (! is_string($providerName) || $providerName === '') {
            return null;
        }

        $authManager = Auth::getFacadeRoot();
        if (! $authManager instanceof AuthManager) {
            return null;
        }

        $provider = $authManager->createUserProvider($providerName);
        if ($provider === null) {
            return null;
        }

        $user = $provider->retrieveById($id);

        if (! $user instanceof Model || ! $user instanceof Authenticatable
            || ! is_string($pending['password_fingerprint'] ?? null)
            || ! hash_equals($pending['password_fingerprint'], hash('sha256', $user->getAuthPassword()))
            || ! $this->twoFactorAuthenticationManager->challengeRequired($user)
            || ! $this->adminAccessGate->allows($user)) {
            $request->session()->forget('seviaflow.auth.pending_two_factor');

            return null;
        }

        return $user;
    }
}
