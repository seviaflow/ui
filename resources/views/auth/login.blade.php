<x-seviaflow-ui::auth.layout title="Sign in — SeviaFlow">
    <div class="sf-auth-header">
        <h1 class="sf-auth-title">Sign in to your account</h1>
        <p class="sf-auth-description">
            Welcome back! Please enter your details.
        </p>
    </div>

    @if ($errors->any())
        <div class="sf-alert sf-alert-danger">
            <p>Please check the form and try again.</p>
        </div>
    @endif

    <form method="POST" action="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'login.store') }}" class="sf-form">
        @csrf

        <div class="sf-field">
            <label class="sf-label" for="email">Email address</label>

            <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    placeholder="you@example.com"
                    required
                    autofocus
                    autocomplete="email"
                    class="sf-input"
            >

            @error('email')
            <p class="sf-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="sf-field">
            <label class="sf-label" for="password">Password</label>

            <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                    class="sf-input"
            >

            @error('password')
            <p class="sf-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="sf-auth-options">
            <label class="sf-checkbox-label" for="remember">
                <input
                        id="remember"
                        name="remember"
                        type="checkbox"
                        value="1"
                        class="sf-checkbox"
                >

                <span>Remember me</span>
            </label>

            <a href="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'password.request') }}" class="sf-link">
                Forgot password?
            </a>
        </div>

        <button type="submit" class="sf-button sf-button-primary">
            Sign in
        </button>
    </form>
</x-seviaflow-ui::auth.layout>
