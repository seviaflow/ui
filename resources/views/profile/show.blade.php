<x-seviaflow-ui::profile.layout :title="$title">
    @php
        $routeNamePrefix = config('seviaflow-ui.route_name_prefix', 'seviaflow.');
        $profileUser = auth(config('seviaflow-ui.auth.guard', 'web'))->user();
    @endphp

    <section class="sf-profile-layout">
        <aside class="sf-card">
            <div class="sf-card-body">
                <nav aria-label="{{ __('seviaflow-ui::panel.profile_sections') }}" class="sf-profile-nav">
                    <a href="{{ route($routeNamePrefix.'profile.index') }}" @class(['sf-profile-nav-link', 'sf-profile-nav-link-active' => $section === 'profile'])>{{ __('seviaflow-ui::panel.profile_information') }}</a>
                    <a href="{{ route($routeNamePrefix.'profile.password') }}" @class(['sf-profile-nav-link', 'sf-profile-nav-link-active' => $section === 'password'])>{{ __('seviaflow-ui::panel.password') }}</a>
                    <a href="{{ route($routeNamePrefix.'profile.two-factor-authentication') }}" @class(['sf-profile-nav-link', 'sf-profile-nav-link-active' => $section === 'two_factor_authentication'])>{{ __('seviaflow-ui::panel.two_factor_authentication') }}</a>
                    <a href="{{ route($routeNamePrefix.'profile.browser-sessions') }}" @class(['sf-profile-nav-link', 'sf-profile-nav-link-active' => $section === 'browser_sessions'])>{{ __('seviaflow-ui::panel.browser_sessions') }}</a>
                </nav>
            </div>
        </aside>

        <article class="sf-card">
            <div class="sf-card-header">
                <h2 class="sf-card-title">{{ $title }}</h2>
                <p class="sf-card-description">{{ $description }}</p>
            </div>
            <div class="sf-card-body">
                @if ($section === 'profile')
                    @if (session('status'))
                        <p class="sf-alert sf-alert-success mb-4" role="status">
                            {{ session('status') }}
                        </p>
                    @endif

                    @if ($errors->any())
                        <p class="sf-alert sf-alert-danger mb-4" role="alert">
                            {{ __('seviaflow-ui::panel.please_check_the_form_and_try_again') }}
                        </p>
                    @endif

                    <form class="sf-form" method="POST" action="{{ route($routeNamePrefix.'profile.update') }}">
                        @csrf
                        <div class="sf-form-grid">
                            <div class="sf-field">
                                <label class="sf-label" for="name">{{ __('seviaflow-ui::panel.name') }}</label>
                                <input class="sf-input" id="name" name="name" type="text" value="{{ old('name', $profileUser?->name) }}" required autocomplete="name">
                                @error('name')<p class="sf-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="email">{{ __('seviaflow-ui::panel.email_address') }}</label>
                                <input class="sf-input" id="email" name="email" type="email" value="{{ old('email', $profileUser?->email) }}" required autocomplete="email">
                                @error('email')<p class="sf-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="sf-resource-actions">
                            <button class="sf-button sf-button-primary" type="submit">
                                {{ __('seviaflow-ui::panel.save_changes') }}
                            </button>
                        </div>
                    </form>
                @elseif ($section === 'password')
                    @if (session('status'))
                        <p class="sf-alert sf-alert-success mb-4" role="status">
                            {{ session('status') }}
                        </p>
                    @endif

                    @if ($errors->any())
                        <p class="sf-alert sf-alert-danger mb-4" role="alert">
                            {{ __('seviaflow-ui::panel.please_check_the_form_and_try_again') }}
                        </p>
                    @endif

                    <form class="sf-form" method="POST" action="{{ route($routeNamePrefix.'profile.password.update') }}">
                        @csrf
                        <div class="sf-form-grid">
                            <div class="sf-field">
                                <label class="sf-label" for="current_password">{{ __('seviaflow-ui::panel.current_password') }}</label>
                                <input class="sf-input" id="current_password" name="current_password" type="password" required autocomplete="current-password">
                                @error('current_password')<p class="sf-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="password">{{ __('seviaflow-ui::panel.new_password') }}</label>
                                <input class="sf-input" id="password" name="password" type="password" required autocomplete="new-password">
                                @error('password')<p class="sf-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="password_confirmation">{{ __('seviaflow-ui::panel.confirm_new_password') }}</label>
                                <input class="sf-input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                            </div>
                        </div>

                        <div class="sf-resource-actions">
                            <button class="sf-button sf-button-primary" type="submit">
                                {{ __('seviaflow-ui::panel.update_password') }}
                            </button>
                        </div>
                    </form>
                @elseif ($section === 'two_factor_authentication')
                    @if (session('status'))
                        <p class="sf-alert sf-alert-success mb-4" role="status">
                            {{ session('status') }}
                        </p>
                    @endif

                    @if ($errors->any())
                        <p class="sf-alert sf-alert-danger mb-4" role="alert">
                            {{ __('seviaflow-ui::panel.please_check_the_form_and_try_again') }}
                        </p>
                    @endif

                    <div class="sf-inset-panel">
                        <p class="sf-item-body">{{ __('seviaflow-ui::panel.add_additional_security_to_your_account') }}</p>
                        @if (! $twoFactorFeatureEnabled)
                            <p class="sf-item-body sf-text-warning mt-2 font-semibold">{{ __('seviaflow-ui::panel.two_factor_authentication_is_disabled_by_configuration') }}</p>
                        @elseif (! $twoFactorBackendAvailable)
                            <p class="sf-item-body sf-text-warning mt-2 font-semibold">{{ __('seviaflow-ui::panel.two_factor_authentication_backend_support_is_not_installed_or_configured') }}</p>
                        @elseif ($twoFactorEnabled)
                            <p class="sf-item-body sf-text-success mt-2 font-semibold">{{ __('seviaflow-ui::panel.two_factor_authentication_is_enabled') }}</p>
                        @elseif ($twoFactorSetupPending)
                            <p class="sf-item-body sf-text-warning mt-2 font-semibold">{{ __('seviaflow-ui::panel.confirm_setup_with_a_code_from_your_authenticator_app') }}</p>
                        @else
                            <p class="sf-item-body mt-2 font-semibold">{{ __('seviaflow-ui::panel.two_factor_authentication_is_not_enabled') }}</p>
                        @endif
                    </div>

                    @if ($twoFactorFeatureEnabled && $twoFactorBackendAvailable && ! $twoFactorEnabled && ! $twoFactorSetupPending)
                        <form class="mt-4" method="POST" action="{{ route($routeNamePrefix.'profile.two-factor-authentication.enable') }}">
                            @csrf
                            <label class="sf-label" for="two_factor_enable_current_password">{{ __('seviaflow-ui::panel.current_password') }}</label>
                            <input class="sf-input mt-2" id="two_factor_enable_current_password" name="current_password" type="password" required autocomplete="current-password">
                            @error('current_password')<p class="sf-error">{{ $message }}</p>@enderror
                            <button class="sf-button sf-button-primary" type="submit">
                                {{ __('seviaflow-ui::panel.enable_two_factor_authentication') }}
                            </button>
                        </form>
                    @endif

                    @if ($twoFactorFeatureEnabled && $twoFactorBackendAvailable && ($twoFactorEnabled || $twoFactorSetupPending))
                        @if ($twoFactorQrCodeSvg)
                            <section class="mt-4">
                                <h3 class="sf-card-title">{{ __('seviaflow-ui::panel.scan_qr_code') }}</h3>
                                <div class="sf-qr-frame mt-2">
                                    {!! $twoFactorQrCodeSvg !!}
                                </div>
                            </section>
                        @endif

                        @if ($twoFactorSetupPending)
                            <form class="sf-form mt-4" method="POST" action="{{ route($routeNamePrefix.'profile.two-factor-authentication.confirm') }}">
                                @csrf
                                <label class="sf-label" for="two_factor_setup_code">{{ __('seviaflow-ui::panel.authentication_code') }}</label>
                                <input class="sf-input mt-2" id="two_factor_setup_code" name="code" type="text" required autocomplete="one-time-code" inputmode="numeric">
                                @error('code')<p class="sf-error">{{ $message }}</p>@enderror
                                <label class="sf-label mt-3" for="two_factor_confirm_current_password">{{ __('seviaflow-ui::panel.current_password') }}</label>
                                <input class="sf-input mt-2" id="two_factor_confirm_current_password" name="current_password" type="password" required autocomplete="current-password">
                                @error('current_password')<p class="sf-error">{{ $message }}</p>@enderror
                                <button class="sf-button sf-button-primary" type="submit">{{ __('seviaflow-ui::panel.confirm_two_factor_authentication') }}</button>
                            </form>
                        @endif

                        @if ($twoFactorEnabled && $twoFactorRecoveryCodes !== [])
                            <section class="mt-4">
                                <h3 class="sf-card-title">{{ __('seviaflow-ui::panel.recovery_codes') }}</h3>
                                <p class="sf-card-description sf-text-warning mt-1">{{ __('seviaflow-ui::panel.store_these_codes_in_a_safe_place_each_code_can_be_used_once') }}</p>
                                <ul class="sf-code-list mt-2">
                                    @foreach ($twoFactorRecoveryCodes as $code)
                                        <li class="font-mono text-sm">{{ $code }}</li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif

                        @if ($twoFactorEnabled)
                        <form class="mt-4" method="POST" action="{{ route($routeNamePrefix.'profile.two-factor-authentication.recovery-codes.regenerate') }}">
                            @csrf
                            <label class="sf-label" for="two_factor_recovery_current_password">{{ __('seviaflow-ui::panel.current_password') }}</label>
                            <input class="sf-input mt-2" id="two_factor_recovery_current_password" name="current_password" type="password" required autocomplete="current-password">
                            @error('current_password')<p class="sf-error">{{ $message }}</p>@enderror
                            <button class="sf-button sf-button-secondary" type="submit">
                                {{ __('seviaflow-ui::panel.regenerate_recovery_codes') }}
                            </button>
                        </form>
                        @endif

                        <form class="sf-form mt-4" method="POST" action="{{ route($routeNamePrefix.'profile.two-factor-authentication.disable') }}">
                            @csrf
                            @method('DELETE')
                            <label class="sf-checkbox-label" for="confirm_disable">
                                <input id="confirm_disable" name="confirm_disable" type="checkbox" value="1" class="sf-checkbox">
                                <span>{{ __('seviaflow-ui::panel.i_understand_this_will_disable_two_factor_authentication') }}</span>
                            </label>
                            @error('confirm_disable')<p class="sf-error">{{ $message }}</p>@enderror
                            <label class="sf-label mt-3" for="two_factor_disable_current_password">{{ __('seviaflow-ui::panel.current_password') }}</label>
                            <input class="sf-input mt-2" id="two_factor_disable_current_password" name="current_password" type="password" required autocomplete="current-password">
                            @error('current_password')<p class="sf-error">{{ $message }}</p>@enderror
                            <div class="sf-resource-actions">
                                <button class="sf-button sf-button-danger" type="submit">
                                    {{ __('seviaflow-ui::panel.disable_two_factor_authentication') }}
                                </button>
                            </div>
                        </form>
                    @endif
                @elseif ($section === 'browser_sessions')
                    @if (session('status'))
                        <p class="sf-alert sf-alert-success mb-4" role="status">
                            {{ session('status') }}
                        </p>
                    @endif

                    @if ($errors->any())
                        <p class="sf-alert sf-alert-danger mb-4" role="alert">
                            {{ __('seviaflow-ui::panel.please_check_the_form_and_try_again') }}
                        </p>
                    @endif

                    <div class="sf-inset-panel">
                        <h3 class="sf-card-title">{{ __('seviaflow-ui::panel.this_device') }}</h3>
                        <p class="sf-muted mt-1">{{ __('seviaflow-ui::panel.last_active_now') }}</p>
                        <p class="sf-card-description mt-2">
                            {{ __('seviaflow-ui::panel.detailed_session_listing_is_not_available_in_every_laravel_session_configuration') }}
                        </p>
                    </div>

                    <form class="sf-form mt-4" method="POST" action="{{ route($routeNamePrefix.'profile.browser-sessions.logout') }}">
                        @csrf
                        <div class="sf-field">
                            <label class="sf-label" for="browser_sessions_current_password">{{ __('seviaflow-ui::panel.current_password') }}</label>
                            <input class="sf-input" id="browser_sessions_current_password" name="current_password" type="password" required autocomplete="current-password">
                            @error('current_password')<p class="sf-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="sf-resource-actions">
                            <button class="sf-button sf-button-primary" type="submit">
                                {{ __('seviaflow-ui::panel.log_out_other_browser_sessions') }}
                            </button>
                        </div>
                    </form>
                @else
                    <div class="sf-empty-state">
                        {{ __('seviaflow-ui::panel.this_section_is_available_in_the_admin_profile_area_detailed_forms_and_actions_will_be_added_here') }}
                    </div>
                @endif
            </div>
        </article>
    </section>
</x-seviaflow-ui::profile.layout>
