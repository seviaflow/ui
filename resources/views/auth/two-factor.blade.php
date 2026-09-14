<x-seviaflow-ui::auth.layout title="Verify sign in — SeviaFlow">
    <div class="sf-auth-header">
        <h1 class="sf-auth-title">Verify your sign in</h1>
        <p class="sf-auth-description">Enter a code from your authenticator app or use one recovery code.</p>
    </div>

    @if ($errors->any())
        <div class="sf-alert sf-alert-danger">
            <p>Please check the verification code and try again.</p>
        </div>
    @endif

    <form method="POST" action="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'login.two-factor.verify') }}" class="sf-form">
        @csrf

        <div class="sf-field">
            <label class="sf-label" for="code">Authentication code</label>
            <input
                id="code"
                name="code"
                type="text"
                value="{{ old('code') }}"
                inputmode="numeric"
                autocomplete="one-time-code"
                required
                autofocus
                class="sf-input"
            >
            @error('code')
                <p class="sf-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="sf-button sf-button-primary">Verify and continue</button>
    </form>

    <p class="sf-auth-footer">
        <a href="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'login') }}" class="sf-link">Return to sign in</a>
    </p>
</x-seviaflow-ui::auth.layout>
