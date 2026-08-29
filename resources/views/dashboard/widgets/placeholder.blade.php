<div class="sf-card-body">
    <h3 class="sf-card-title">{{ $widget['label'] }}</h3>
    @if(is_string($widget['description']) && trim($widget['description']) !== '')
        <p class="sf-card-description mt-1">{{ $widget['description'] }}</p>
    @endif

    <div class="sf-dashboard-empty mt-3">
        Widget placeholder for <span class="font-semibold">{{ $widget['name'] }}</span>.
    </div>

    @if($widget['cacheTtl'] !== null)
        <p class="sf-help mt-2">Cache TTL: {{ $widget['cacheTtl'] }}s</p>
    @endif
</div>
