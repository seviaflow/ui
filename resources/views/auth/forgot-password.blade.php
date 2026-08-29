<x-seviaflow-ui::auth.layout title="Forgot password — SeviaFlow">
    <div class="sf-auth-header">
        <h1 class="sf-auth-title">Reset your password</h1>
        <p class="sf-auth-description">Enter your email address and we will send you a reset link.</p>
    </div>

    @if (session('status'))
        <p class="sf-alert sf-alert-success" role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="sf-alert sf-alert-danger" role="alert">
            <p>Please check the form and try again.</p>
        </div>
    @endif

    <form method="POST" action="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'password.email') }}" class="sf-form">
        @csrf
        <div class="sf-field">
            <label class="sf-label" for="email">Email address</label>
            <input class="sf-input" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus autocomplete="email">
            @error('email')
                <p class="sf-error">{{ $message }}</p>
            @enderror
        </div>

        <button class="sf-button sf-button-primary" type="submit">Send reset link</button>
    </form>

    <p class="sf-muted mt-5 text-center">
        <a class="sf-link" href="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'login') }}">Back to sign in</a>
    </p>
</x-seviaflow-ui::auth.layout>
