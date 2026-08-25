<x-seviaflow-ui::app.layout :title="$title ?? 'Profile'">
    <x-slot:pageTitle>{{ $title ?? 'Account settings' }}</x-slot:pageTitle>
    <x-slot:pageDescription>{{ $description ?? 'Manage your admin account security and profile details.' }}</x-slot:pageDescription>
    <x-slot:breadcrumbs>
        @php
            $routeNamePrefix = config('seviaflow-ui.route_name_prefix', 'seviaflow.');
        @endphp
        @if (Route::has($routeNamePrefix.'home'))
            <a href="{{ route($routeNamePrefix.'home') }}" class="sf-link !text-sm">Admin</a>
            <span class="text-slate-400">/</span>
        @endif
        <span class="font-medium text-slate-900">Profile</span>
    </x-slot:breadcrumbs>

    {{ $slot }}
</x-seviaflow-ui::app.layout>
