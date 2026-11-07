<x-seviaflow-ui::auth.layout title="{{ __('seviaflow-ui::panel.sign_in_seviaflow') }}">
    <div class="sf-auth-header">
        <h1 class="sf-auth-title">{{ __('seviaflow-ui::panel.sign_in_to_your_account') }}</h1>
        <p class="sf-auth-description">
            {{ __('seviaflow-ui::panel.welcome_back_please_enter_your_details') }}
        </p>
    </div>

    @if ($errors->any())
        <div class="sf-alert sf-alert-danger">
            <p>{{ __('seviaflow-ui::panel.please_check_the_form_and_try_again') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'login.store') }}" class="sf-form">
        @csrf

        <div class="sf-field">
            <label class="sf-label" for="email">{{ __('seviaflow-ui::panel.email_address') }}</label>

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
            <label class="sf-label" for="password">{{ __('seviaflow-ui::panel.password') }}</label>

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

                <span>{{ __('seviaflow-ui::panel.remember_me') }}</span>
            </label>

            <a href="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'password.request') }}" class="sf-link">
                {{ __('seviaflow-ui::panel.forgot_password') }}
            </a>
        </div>

        <button type="submit" class="sf-button sf-button-primary">
            {{ __('seviaflow-ui::panel.sign_in') }}
        </button>
    </form>
</x-seviaflow-ui::auth.layout>
