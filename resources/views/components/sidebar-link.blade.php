@props([
    'item',
])

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
        <x-seviaflow-ui::icon :name="$item->icon" class="sf-sidebar-icon" />
    </span>
    <span class="sf-sidebar-label">{{ $item->label }}</span>
</a>
