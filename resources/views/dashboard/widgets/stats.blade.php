<div class="sf-card-body">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ $widget['label'] }}</p>
            <p class="sf-dashboard-metric sf-dashboard-stat-{{ $color }}">{{ $value }}</p>
            @if(is_string($widget['description']) && trim($widget['description']) !== '')
                <p class="mt-1 text-sm text-slate-500">{{ $widget['description'] }}</p>
            @endif
        </div>
        @if(is_string($icon) && trim($icon) !== '')
            <span class="sf-dashboard-stat-icon" aria-hidden="true">{{ $icon }}</span>
        @endif
    </div>
</div>

