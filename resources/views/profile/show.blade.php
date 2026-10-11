<x-seviaflow-ui::profile.layout :title="$title">
    @php
        $routeNamePrefix = config('seviaflow-ui.route_name_prefix', 'seviaflow.');
        $profileUser = auth(config('seviaflow-ui.auth.guard', 'web'))->user();
    @endphp

    <section class="sf-profile-layout">
        <aside class="sf-card">
            <div class="sf-card-body">
                <nav aria-label="Profile sections" class="sf-profile-nav">
                    <a href="{{ route($routeNamePrefix.'profile.index') }}" @class(['sf-profile-nav-link', 'sf-profile-nav-link-active' => $section === 'profile'])>Profile information</a>
                    <a href="{{ route($routeNamePrefix.'profile.password') }}" @class(['sf-profile-nav-link', 'sf-profile-nav-link-active' => $section === 'password'])>Password</a>
                    <a href="{{ route($routeNamePrefix.'profile.two-factor-authentication') }}" @class(['sf-profile-nav-link', 'sf-profile-nav-link-active' => $section === 'two_factor_authentication'])>Two-factor authentication</a>
                    <a href="{{ route($routeNamePrefix.'profile.browser-sessions') }}" @class(['sf-profile-nav-link', 'sf-profile-nav-link-active' => $section === 'browser_sessions'])>Browser sessions</a>
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
                            Please check the form and try again.
                        </p>
                    @endif

                    <form class="sf-form" method="POST" action="{{ route($routeNamePrefix.'profile.update') }}">
                        @csrf
                        <div class="sf-form-grid">
                            <div class="sf-field">
                                <label class="sf-label" for="name">Name</label>
                                <input class="sf-input" id="name" name="name" type="text" value="{{ old('name', $profileUser?->name) }}" required autocomplete="name">
                                @error('name')<p class="sf-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="email">Email address</label>
                                <input class="sf-input" id="email" name="email" type="email" value="{{ old('email', $profileUser?->email) }}" required autocomplete="email">
                                @error('email')<p class="sf-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="sf-resource-actions">
                            <button class="sf-button sf-button-primary" type="submit">
                                Save changes
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
                            Please check the form and try again.
                        </p>
                    @endif

                    <form class="sf-form" method="POST" action="{{ route($routeNamePrefix.'profile.password.update') }}">
                        @csrf
                        <div class="sf-form-grid">
                            <div class="sf-field">
                                <label class="sf-label" for="current_password">Current password</label>
                                <input class="sf-input" id="current_password" name="current_password" type="password" required autocomplete="current-password">
                                @error('current_password')<p class="sf-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="password">New password</label>
                                <input class="sf-input" id="password" name="password" type="password" required autocomplete="new-password">
                                @error('password')<p class="sf-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="sf-field">
                                <label class="sf-label" for="password_confirmation">Confirm new password</label>
                                <input class="sf-input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                            </div>
                        </div>

                        <div class="sf-resource-actions">
                            <button class="sf-button sf-button-primary" type="submit">
                                Update password
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
                            Please check the form and try again.
                        </p>
                    @endif

                    <div class="sf-inset-panel">
                        <p class="sf-item-body">Add additional security to your account.</p>
                        @if (! $twoFactorFeatureEnabled)
                            <p class="sf-item-body sf-text-warning mt-2 font-semibold">Two-factor authentication is disabled by configuration.</p>
                        @elseif (! $twoFactorBackendAvailable)
                            <p class="sf-item-body sf-text-warning mt-2 font-semibold">Two-factor authentication backend support is not installed or configured.</p>
                        @elseif ($twoFactorEnabled)
                            <p class="sf-item-body sf-text-success mt-2 font-semibold">Two-factor authentication is enabled.</p>
                        @elseif ($twoFactorSetupPending)
                            <p class="sf-item-body sf-text-warning mt-2 font-semibold">Confirm setup with a code from your authenticator app.</p>
                        @else
                            <p class="sf-item-body mt-2 font-semibold">Two-factor authentication is not enabled.</p>
                        @endif
                    </div>

                    @if ($twoFactorFeatureEnabled && $twoFactorBackendAvailable && ! $twoFactorEnabled && ! $twoFactorSetupPending)
                        <form class="mt-4" method="POST" action="{{ route($routeNamePrefix.'profile.two-factor-authentication.enable') }}">
                            @csrf
                            <label class="sf-label" for="two_factor_enable_current_password">Current password</label>
                            <input class="sf-input mt-2" id="two_factor_enable_current_password" name="current_password" type="password" required autocomplete="current-password">
                            @error('current_password')<p class="sf-error">{{ $message }}</p>@enderror
                            <button class="sf-button sf-button-primary" type="submit">
                                Enable two-factor authentication
                            </button>
                        </form>
                    @endif

                    @if ($twoFactorFeatureEnabled && $twoFactorBackendAvailable && ($twoFactorEnabled || $twoFactorSetupPending))
                        @if ($twoFactorQrCodeSvg)
                            <section class="mt-4">
                                <h3 class="sf-card-title">Scan QR code</h3>
                                <div class="sf-qr-frame mt-2">
                                    {!! $twoFactorQrCodeSvg !!}
                                </div>
                            </section>
                        @endif

                        @if ($twoFactorSetupPending)
                            <form class="sf-form mt-4" method="POST" action="{{ route($routeNamePrefix.'profile.two-factor-authentication.confirm') }}">
                                @csrf
                                <label class="sf-label" for="two_factor_setup_code">Authentication code</label>
                                <input class="sf-input mt-2" id="two_factor_setup_code" name="code" type="text" required autocomplete="one-time-code" inputmode="numeric">
                                @error('code')<p class="sf-error">{{ $message }}</p>@enderror
                                <label class="sf-label mt-3" for="two_factor_confirm_current_password">Current password</label>
                                <input class="sf-input mt-2" id="two_factor_confirm_current_password" name="current_password" type="password" required autocomplete="current-password">
                                @error('current_password')<p class="sf-error">{{ $message }}</p>@enderror
                                <button class="sf-button sf-button-primary" type="submit">Confirm two-factor authentication</button>
                            </form>
                        @endif

                        @if ($twoFactorEnabled && $twoFactorRecoveryCodes !== [])
                            <section class="mt-4">
                                <h3 class="sf-card-title">Recovery codes</h3>
                                <p class="sf-card-description sf-text-warning mt-1">Store these codes in a safe place. Each code can be used once.</p>
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
                            <label class="sf-label" for="two_factor_recovery_current_password">Current password</label>
                            <input class="sf-input mt-2" id="two_factor_recovery_current_password" name="current_password" type="password" required autocomplete="current-password">
                            @error('current_password')<p class="sf-error">{{ $message }}</p>@enderror
                            <button class="sf-button sf-button-secondary" type="submit">
                                Regenerate recovery codes
                            </button>
                        </form>
                        @endif

                        <form class="sf-form mt-4" method="POST" action="{{ route($routeNamePrefix.'profile.two-factor-authentication.disable') }}">
                            @csrf
                            @method('DELETE')
                            <label class="sf-checkbox-label" for="confirm_disable">
                                <input id="confirm_disable" name="confirm_disable" type="checkbox" value="1" class="sf-checkbox">
                                <span>I understand this will disable two-factor authentication.</span>
                            </label>
                            @error('confirm_disable')<p class="sf-error">{{ $message }}</p>@enderror
                            <label class="sf-label mt-3" for="two_factor_disable_current_password">Current password</label>
                            <input class="sf-input mt-2" id="two_factor_disable_current_password" name="current_password" type="password" required autocomplete="current-password">
                            @error('current_password')<p class="sf-error">{{ $message }}</p>@enderror
                            <div class="sf-resource-actions">
                                <button class="sf-button sf-button-danger" type="submit">
                                    Disable two-factor authentication
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
                            Please check the form and try again.
                        </p>
                    @endif

                    <div class="sf-inset-panel">
                        <h3 class="sf-card-title">This device</h3>
                        <p class="sf-muted mt-1">Last active: now</p>
                        <p class="sf-card-description mt-2">
                            Detailed session listing is not available in every Laravel session configuration.
                        </p>
                    </div>

                    <form class="sf-form mt-4" method="POST" action="{{ route($routeNamePrefix.'profile.browser-sessions.logout') }}">
                        @csrf
                        <div class="sf-field">
                            <label class="sf-label" for="browser_sessions_current_password">Current password</label>
                            <input class="sf-input" id="browser_sessions_current_password" name="current_password" type="password" required autocomplete="current-password">
                            @error('current_password')<p class="sf-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="sf-resource-actions">
                            <button class="sf-button sf-button-primary" type="submit">
                                Log out other browser sessions
                            </button>
                        </div>
                    </form>
                @else
                    <div class="sf-empty-state">
                        This section is available in the admin profile area. Detailed forms and actions will be added here.
                    </div>
                @endif
            </div>
        </article>
    </section>
</x-seviaflow-ui::profile.layout>
