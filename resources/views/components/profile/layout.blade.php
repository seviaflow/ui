<x-seviaflow-ui::app.layout :title="$title ?? 'Profile'" contentWidth="narrow">
    <x-slot:pageTitle>{{ $title ?? 'Account settings' }}</x-slot:pageTitle>
    <x-slot:pageDescription>{{ $description ?? 'Manage your admin account security and profile details.' }}</x-slot:pageDescription>
    <x-slot:breadcrumbs>
        @php
            $routeNamePrefix = config('seviaflow-ui.route_name_prefix', 'seviaflow.');
        @endphp
        @if (\Illuminate\Support\Facades\Route::has($routeNamePrefix.'home'))
            <a href="{{ route($routeNamePrefix.'home') }}" class="sf-link !text-sm">Admin</a>
            <span class="sf-muted">/</span>
        @endif
        <span class="sf-item-title">Profile</span>
    </x-slot:breadcrumbs>

    {{ $slot }}
</x-seviaflow-ui::app.layout>
