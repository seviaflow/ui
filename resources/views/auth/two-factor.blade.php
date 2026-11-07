<x-seviaflow-ui::auth.layout title="{{ __('seviaflow-ui::panel.verify_sign_in_seviaflow') }}">
    <div class="sf-auth-header">
        <h1 class="sf-auth-title">{{ __('seviaflow-ui::panel.verify_your_sign_in') }}</h1>
        <p class="sf-auth-description">{{ __('seviaflow-ui::panel.enter_a_code_from_your_authenticator_app_or_use_one_recovery_code') }}</p>
    </div>

    @if ($errors->any())
        <div class="sf-alert sf-alert-danger">
            <p>{{ __('seviaflow-ui::panel.please_check_the_verification_code_and_try_again') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'login.two-factor.verify') }}" class="sf-form">
        @csrf

        <div class="sf-field">
            <label class="sf-label" for="code">{{ __('seviaflow-ui::panel.authentication_code') }}</label>
            <input
                id="code"
                name="code"
                type="text"
                autocomplete="one-time-code"
                required
                autofocus
                class="sf-input"
            >
            @error('code')
                <p class="sf-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="sf-button sf-button-primary">{{ __('seviaflow-ui::panel.verify_and_continue') }}</button>
    </form>

    <p class="sf-auth-footer">
        <a href="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'login') }}" class="sf-link">{{ __('seviaflow-ui::panel.return_to_sign_in') }}</a>
    </p>
</x-seviaflow-ui::auth.layout>
