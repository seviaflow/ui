@props([
    'collapsed' => false,
    'href' => null,
    'surface' => 'light',
])

@php
    $assetRoot = trim((string) config('seviaflow-ui.assets.path', 'vendor/seviaflow/ui'), '/');
    $logoUrl = asset($assetRoot.'/brand/seviaflow-logo.png');
    $markUrl = asset($assetRoot.'/brand/seviaflow-mark.png');
    $surface = in_array($surface, ['light', 'dark'], true) ? $surface : 'light';
@endphp

<a {{ $attributes->class(['sf-brand', 'sf-brand-collapsed' => $collapsed, 'sf-brand-on-dark' => $surface === 'dark'])->merge(['href' => $href ?? '#', 'aria-label' => 'SeviaFlow home']) }}>
    @if ($surface === 'dark')
        <img class="sf-brand-mark sf-brand-mark-lockup" src="{{ $markUrl }}" alt="" aria-hidden="true">
        <span class="sf-brand-text sf-brand-text-lockup" aria-hidden="true">
            Sevia<span>Flow</span>
        </span>
    @else
        <img class="sf-brand-logo" src="{{ $logoUrl }}" alt="SeviaFlow">
        <img class="sf-brand-mark" src="{{ $markUrl }}" alt="" aria-hidden="true">
        <span class="sf-brand-text">SeviaFlow</span>
    @endif
</a>
