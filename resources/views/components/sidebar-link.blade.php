@props([
    'item',
])

@php
    $configuredIcon = is_string($item->icon) && trim($item->icon) !== '' ? trim($item->icon) : null;
    $sidebarIcon = $configuredIcon !== null && \SeviaFlow\Ui\Icons\IconRegistry::find($configuredIcon) !== null
        ? $configuredIcon
        : 'circle-stack';
@endphp

<a href="{{ $item->url }}"
   @class([
       'sf-sidebar-link',
       'sf-sidebar-link-active' => $item->active,
   ])
   @if($item->active) aria-current="page" @endif
   title="{{ $item->label }}"
   data-sf-nav-link
>
    <span class="sf-sidebar-node" aria-hidden="true">
        <x-seviaflow-ui::icon :name="$sidebarIcon" class="sf-sidebar-icon" />
    </span>
    <span class="sf-sidebar-label">{{ $item->label }}</span>
</a>
