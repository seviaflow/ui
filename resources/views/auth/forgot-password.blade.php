<x-seviaflow-ui::auth.layout title="{{ __('seviaflow-ui::panel.forgot_password_seviaflow') }}">
    <div class="sf-auth-header">
        <h1 class="sf-auth-title">{{ __('seviaflow-ui::panel.reset_your_password') }}</h1>
        <p class="sf-auth-description">{{ __('seviaflow-ui::panel.enter_your_email_address_and_we_will_send_you_a_reset_link') }}</p>
    </div>

    @if (session('status'))
        <p class="sf-alert sf-alert-success" role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="sf-alert sf-alert-danger" role="alert">
            <p>{{ __('seviaflow-ui::panel.please_check_the_form_and_try_again') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'password.email') }}" class="sf-form">
        @csrf
        <div class="sf-field">
            <label class="sf-label" for="email">{{ __('seviaflow-ui::panel.email_address') }}</label>
            <input class="sf-input" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus autocomplete="email">
            @error('email')
                <p class="sf-error">{{ $message }}</p>
            @enderror
        </div>

        <button class="sf-button sf-button-primary" type="submit">{{ __('seviaflow-ui::panel.send_reset_link') }}</button>
    </form>

    <p class="sf-muted mt-5 text-center">
        <a class="sf-link" href="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'login') }}">{{ __('seviaflow-ui::panel.back_to_sign_in') }}</a>
    </p>
</x-seviaflow-ui::auth.layout>
