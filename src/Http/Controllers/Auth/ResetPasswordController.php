<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Http\Controllers\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class ResetPasswordController
{
    public function create(Request $request, string $token): View
    {
        return view('seviaflow-ui::auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::broker(
            (string) config('seviaflow-ui.auth.password_broker', 'users')
        )->reset($credentials, function ($user) use ($credentials): void {
            $user->forceFill([
                'password' => Hash::make($credentials['password']),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()
            ->route((string) config('seviaflow-ui.route_name_prefix', 'seviaflow.').'login')
            ->with('status', __($status));
    }
}
