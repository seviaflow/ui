<x-seviaflow-ui::app.layout :title="$title ?? __('seviaflow-ui::panel.profile')" contentWidth="narrow">
    <x-slot:pageTitle>{{ $title ?? __('seviaflow-ui::panel.account_settings') }}</x-slot:pageTitle>
    <x-slot:pageDescription>{{ $description ?? __('seviaflow-ui::panel.manage_your_admin_account_security_and_profile_details') }}</x-slot:pageDescription>
    <x-slot:breadcrumbs>
        @php
            $routeNamePrefix = config('seviaflow-ui.route_name_prefix', 'seviaflow.');
        @endphp
        @if (\Illuminate\Support\Facades\Route::has($routeNamePrefix.'home'))
            <a href="{{ route($routeNamePrefix.'home') }}" class="sf-link !text-sm">{{ __('seviaflow-ui::panel.admin') }}</a>
            <span class="sf-muted">/</span>
        @endif
        <span class="sf-item-title">{{ __('seviaflow-ui::panel.profile') }}</span>
    </x-slot:breadcrumbs>

    {{ $slot }}
</x-seviaflow-ui::app.layout>
