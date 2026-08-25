<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

final class ForgotPasswordController
{
    public function create(): View
    {
        return view('seviaflow-ui::auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::broker(
            (string) config('seviaflow-ui.auth.password_broker', 'users')
        )->sendResetLink($credentials);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['email' => __($status)]);
        }

        return back()->with('status', __($status));
    }
}
