<x-seviaflow-ui::auth.layout title="{{ __('seviaflow-ui::panel.reset_password_seviaflow') }}">
    <div class="sf-auth-header">
        <h1 class="sf-auth-title">{{ __('seviaflow-ui::panel.reset_password') }}</h1>
        <p class="sf-auth-description">{{ __('seviaflow-ui::panel.choose_a_new_password_for_your_account') }}</p>
    </div>

    @if ($errors->any())
        <div class="sf-alert sf-alert-danger" role="alert">
            <p>{{ __('seviaflow-ui::panel.please_review_the_form_and_try_again') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'password.update') }}" class="sf-form">
        @csrf
        <input name="token" type="hidden" value="{{ $token }}">

        <div class="sf-field">
            <label class="sf-label" for="email">{{ __('seviaflow-ui::panel.email_address') }}</label>
            <input class="sf-input" id="email" name="email" type="email" value="{{ old('email', $email) }}" required autofocus autocomplete="email">
            @error('email')
                <p class="sf-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="sf-field">
            <label class="sf-label" for="password">{{ __('seviaflow-ui::panel.password') }}</label>
            <input class="sf-input" id="password" name="password" type="password" placeholder="••••••••" required autocomplete="new-password">
            @error('password')
                <p class="sf-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="sf-field">
            <label class="sf-label" for="password_confirmation">{{ __('seviaflow-ui::panel.confirm_password') }}</label>
            <input class="sf-input" id="password_confirmation" name="password_confirmation" type="password" placeholder="••••••••" required autocomplete="new-password">
        </div>

        <button class="sf-button sf-button-primary" type="submit">{{ __('seviaflow-ui::panel.reset_password') }}</button>
    </form>
</x-seviaflow-ui::auth.layout>
