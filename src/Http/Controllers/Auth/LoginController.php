<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class LoginController
{
    public function create(): View|RedirectResponse
    {
        $guard = (string) config('seviaflow-ui.auth.guard', 'web');
        if (Auth::guard($guard)->check()) {
            $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');

            return redirect()->route($routeNamePrefix.'dashboard');
        }

        return view('seviaflow-ui::auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guard = (string) config('seviaflow-ui.auth.guard', 'web');
        $remember = $request->boolean('remember');

        if (! Auth::guard($guard)->attempt($credentials, $remember)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();

        $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');
        $defaultRedirect = route($routeNamePrefix.'dashboard');
        $configuredRedirect = config('seviaflow-ui.auth.redirect_after_login');

        if (is_string($configuredRedirect) && trim($configuredRedirect) !== '') {
            $defaultRedirect = $configuredRedirect;
        }

        return redirect()->intended($defaultRedirect);
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
}
