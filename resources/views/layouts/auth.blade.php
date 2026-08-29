<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SeviaFlow' }}</title>

    <link rel="stylesheet" href="{{ asset(config('seviaflow-ui.assets.path', 'vendor/seviaflow/ui').'/seviaflow.css') }}">

    @livewireStyles
</head>

<body class="sf-auth-body">
<main class="sf-auth-page">
    <section class="sf-auth-shell">
        @php
            $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');
            $brandRoute = \Illuminate\Support\Facades\Route::has($routeNamePrefix.'dashboard')
                ? $routeNamePrefix.'dashboard'
                : null;
        @endphp
        <div class="sf-auth-brand">
            <x-seviaflow-ui::brand :href="$brandRoute ? route($brandRoute) : '#'" />
            <p class="sf-auth-product">SeviaFlow Admin</p>
            <p class="sf-auth-vendor">Balanced Flow workspace</p>
        </div>

        <div class="sf-auth-card">
            {{ $slot }}
        </div>

        <p class="sf-auth-footer">
            Protected by industry-standard encryption.
        </p>
    </section>
</main>

<script src="{{ asset(config('seviaflow-ui.assets.path', 'vendor/seviaflow/ui').'/seviaflow.js') }}" defer></script>

@livewireScripts
</body>
</html>
