@props([
    'item',
])

<a href="{{ $item->url }}"
   @class([
       'sf-sidebar-link',
       'sf-sidebar-link-active' => $item->active,
   ])
>
    <x-seviaflow-ui::icon :name="$item->icon" class="sf-sidebar-icon" />
    <span class="sf-sidebar-label">{{ $item->label }}</span>
</a>
