<x-seviaflow-ui::profile.layout :title="$title">
    @php
        $routeNamePrefix = config('seviaflow-ui.route_name_prefix', 'seviaflow.');
    @endphp

    <section class="grid gap-4 md:grid-cols-[16rem_minmax(0,1fr)]">
        <aside class="nav rounded-lg border border-gray-200 bg-white p-4">
            <nav aria-label="Profile sections" class="menu grid gap-1.5">
                <a href="{{ route($routeNamePrefix.'profile.index') }}" @class(['active rounded-md px-3 py-2 text-sm', 'text-gray-700' => $section !== 'profile'])>Profile information</a>
                <a href="{{ route($routeNamePrefix.'profile.password') }}" @class(['active rounded-md px-3 py-2 text-sm', 'text-gray-700' => $section !== 'password'])>Password</a>
                <a href="{{ route($routeNamePrefix.'profile.two-factor-authentication') }}" @class(['active rounded-md px-3 py-2 text-sm', 'text-gray-700' => $section !== 'two_factor_authentication'])>Two-factor authentication</a>
                <a href="{{ route($routeNamePrefix.'profile.browser-sessions') }}" @class(['active rounded-md px-3 py-2 text-sm', 'text-gray-700' => $section !== 'browser_sessions'])>Browser sessions</a>
            </nav>
        </aside>

        <article class="content rounded-lg border border-gray-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-gray-900">{{ $title }}</h2>
            <p class="muted mt-1 text-sm text-gray-600">{{ $description }}</p>

            @if ($section === 'profile')
                @if (session('status'))
                    <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800" role="status">
                        {{ session('status') }}
                    </p>
                @endif

                @if ($errors->any())
                    <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
                        Please check the form and try again.
                    </p>
                @endif

                <form class="mt-4 space-y-4" method="POST" action="{{ route($routeNamePrefix.'profile.update') }}">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="name">Name</label>
                        <input class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900" id="name" name="name" type="text" value="{{ old('name', auth(config('seviaflow-ui.auth.guard', 'web'))->user()?->name) }}" required autocomplete="name">
                        @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="email">Email address</label>
                        <input class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900" id="email" name="email" type="email" value="{{ old('email', auth(config('seviaflow-ui.auth.guard', 'web'))->user()?->email) }}" required autocomplete="email">
                        @error('email')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <button class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white" type="submit">
                        Save changes
                    </button>
                </form>
            @elseif ($section === 'password')
                @if (session('status'))
                    <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800" role="status">
                        {{ session('status') }}
                    </p>
                @endif

                @if ($errors->any())
                    <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
                        Please check the form and try again.
                    </p>
                @endif

                <form class="mt-4 space-y-4" method="POST" action="{{ route($routeNamePrefix.'profile.password.update') }}">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="current_password">Current password</label>
                        <input class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900" id="current_password" name="current_password" type="password" required autocomplete="current-password">
                        @error('current_password')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="password">New password</label>
                        <input class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900" id="password" name="password" type="password" required autocomplete="new-password">
                        @error('password')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="password_confirmation">Confirm new password</label>
                        <input class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                    </div>

                    <button class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:bg-gray-500" type="submit">
                        Update password
                    </button>
                </form>
            @elseif ($section === 'two_factor_authentication')
                @if (session('status'))
                    <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800" role="status">
                        {{ session('status') }}
                    </p>
                @endif

                @if ($errors->any())
                    <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
                        Please check the form and try again.
                    </p>
                @endif

                <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p class="text-sm text-gray-700">Add additional security to your account.</p>
                    @if (! $twoFactorFeatureEnabled)
                        <p class="mt-2 text-sm text-amber-700">Two-factor authentication is disabled by configuration.</p>
                    @elseif (! $twoFactorBackendAvailable)
                        <p class="mt-2 text-sm text-amber-700">Two-factor authentication backend support is not installed or configured.</p>
                    @elseif ($twoFactorEnabled)
                        <p class="mt-2 text-sm font-medium text-green-700">Two-factor authentication is enabled.</p>
                    @else
                        <p class="mt-2 text-sm font-medium text-gray-700">Two-factor authentication is not enabled.</p>
                    @endif
                </div>

                @if ($twoFactorFeatureEnabled && $twoFactorBackendAvailable && ! $twoFactorEnabled)
                    <form class="mt-4" method="POST" action="{{ route($routeNamePrefix.'profile.two-factor-authentication.enable') }}">
                        @csrf
                        <button class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:bg-gray-500" type="submit">
                            Enable two-factor authentication
                        </button>
                    </form>
                @endif

                @if ($twoFactorFeatureEnabled && $twoFactorBackendAvailable && $twoFactorEnabled)
                    @if ($twoFactorQrCodeSvg)
                        <section class="mt-4">
                            <h3 class="text-sm font-semibold text-gray-900">Scan QR code</h3>
                            <div class="mt-2 inline-block rounded-lg border border-gray-200 bg-white p-3">
                                {!! $twoFactorQrCodeSvg !!}
                            </div>
                        </section>
                    @endif

                    @if ($twoFactorRecoveryCodes !== [])
                        <section class="mt-4">
                            <h3 class="text-sm font-semibold text-gray-900">Recovery codes</h3>
                            <p class="mt-1 text-sm text-amber-700">Store these codes in a safe place. Each code can be used once.</p>
                            <ul class="mt-2 space-y-1 rounded-lg border border-gray-200 bg-white p-3">
                                @foreach ($twoFactorRecoveryCodes as $code)
                                    <li class="font-mono text-sm text-gray-800">{{ $code }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    <form class="mt-4" method="POST" action="{{ route($routeNamePrefix.'profile.two-factor-authentication.recovery-codes.regenerate') }}">
                        @csrf
                        <button class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-800" type="submit">
                            Regenerate recovery codes
                        </button>
                    </form>

                    <form class="mt-4 space-y-3" method="POST" action="{{ route($routeNamePrefix.'profile.two-factor-authentication.disable') }}">
                        @csrf
                        @method('DELETE')
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700" for="confirm_disable">
                            <input id="confirm_disable" name="confirm_disable" type="checkbox" value="1">
                            <span>I understand this will disable two-factor authentication.</span>
                        </label>
                        @error('confirm_disable')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        <button class="inline-flex items-center justify-center rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-700" type="submit">
                            Disable two-factor authentication
                        </button>
                    </form>
                @endif
            @elseif ($section === 'browser_sessions')
                @if (session('status'))
                    <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800" role="status">
                        {{ session('status') }}
                    </p>
                @endif

                @if ($errors->any())
                    <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
                        Please check the form and try again.
                    </p>
                @endif

                <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <h3 class="text-sm font-semibold text-gray-900">This device</h3>
                    <p class="mt-1 text-sm text-gray-700">Last active: now</p>
                    <p class="mt-2 text-sm text-gray-600">
                        Detailed session listing is not available in every Laravel session configuration.
                    </p>
                </div>

                <form class="mt-4 space-y-4" method="POST" action="{{ route($routeNamePrefix.'profile.browser-sessions.logout') }}">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="browser_sessions_current_password">Current password</label>
                        <input class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900" id="browser_sessions_current_password" name="current_password" type="password" required autocomplete="current-password">
                        @error('current_password')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <button class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white" type="submit">
                        Log out other browser sessions
                    </button>
                </form>
            @else
                <div class="mt-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-600">
                    This section is available in the admin profile area. Detailed forms and actions will be added here.
                </div>
            @endif
        </article>
    </section>
</x-seviaflow-ui::profile.layout>
