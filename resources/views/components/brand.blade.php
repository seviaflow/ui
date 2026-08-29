@props([
    'collapsed' => false,
    'href' => null,
])

@php
    $assetRoot = trim((string) config('seviaflow-ui.assets.path', 'vendor/seviaflow/ui'), '/');
    $logoUrl = asset($assetRoot.'/brand/seviaflow-logo.png');
    $markUrl = asset($assetRoot.'/brand/seviaflow-mark.png');
@endphp

<a {{ $attributes->class(['sf-brand', 'sf-brand-collapsed' => $collapsed])->merge(['href' => $href ?? '#', 'aria-label' => 'SeviaFlow home']) }}>
    <img class="sf-brand-logo" src="{{ $logoUrl }}" alt="SeviaFlow">
    <img class="sf-brand-mark" src="{{ $markUrl }}" alt="" aria-hidden="true">
    <span class="sf-brand-text">SeviaFlow</span>
</a>
