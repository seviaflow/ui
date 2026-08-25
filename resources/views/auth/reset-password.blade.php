<x-seviaflow-ui::auth.layout title="Reset password — SeviaFlow">
    <div class="sf-auth-header">
        <h1 class="sf-auth-title">Reset password</h1>
        <p class="sf-auth-description">Choose a new password for your account.</p>
    </div>

    @if ($errors->any())
        <div class="sf-alert sf-alert-danger" role="alert">
            <p>Please review the form and try again.</p>
        </div>
    @endif

    <form method="POST" action="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'password.update') }}" class="sf-form">
        @csrf
        <input name="token" type="hidden" value="{{ $token }}">

        <div class="sf-field">
            <label class="sf-label" for="email">Email address</label>
            <input class="sf-input" id="email" name="email" type="email" value="{{ old('email', $email) }}" required autofocus autocomplete="email">
            @error('email')
                <p class="sf-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="sf-field">
            <label class="sf-label" for="password">Password</label>
            <input class="sf-input" id="password" name="password" type="password" placeholder="••••••••" required autocomplete="new-password">
            @error('password')
                <p class="sf-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="sf-field">
            <label class="sf-label" for="password_confirmation">Confirm password</label>
            <input class="sf-input" id="password_confirmation" name="password_confirmation" type="password" placeholder="••••••••" required autocomplete="new-password">
        </div>

        <button class="sf-button sf-button-primary" type="submit">Reset password</button>
    </form>
</x-seviaflow-ui::auth.layout>
