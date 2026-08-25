<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SeviaFlow' }}</title>
    @include('seviaflow-ui::partials.assets-head')
</head>
<body class="sf-auth-body">
<main class="sf-auth-page">
    <section class="sf-auth-shell">
        <div class="sf-auth-brand">
            <div class="sf-auth-logo">SF</div>
            <p class="sf-auth-product">SeviaFlow</p>
            <p class="sf-auth-vendor">by SeviaFlow</p>
        </div>

        <div class="sf-auth-card">
            {{ $slot }}
        </div>

        <p class="sf-auth-footer">Protected by industry-standard encryption.</p>
    </section>
</main>
@include('seviaflow-ui::partials.assets-foot')
</body>
</html>
