<div class="sf-card-body">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="sf-card-kicker">{{ $widget['label'] }}</p>

            @if($state === 'loading')
                <p class="sf-dashboard-empty mt-2">Loading analytics...</p>
            @elseif($state === 'error')
                <p class="sf-dashboard-empty mt-2">{{ $message ?: 'Unable to load analytics.' }}</p>
            @elseif($state === 'empty')
                <p class="sf-dashboard-empty mt-2">No analytics data found.</p>
            @else
                <p class="sf-dashboard-metric sf-dashboard-stat-{{ $color }}">{!! $value !!}</p>
            @endif

            @if(is_string($widget['description']) && trim($widget['description']) !== '')
                <p class="sf-card-description mt-2">{{ $widget['description'] }}</p>
            @endif
        </div>
        @if(is_string($icon) && trim($icon) !== '')
            <span class="sf-dashboard-stat-icon" aria-hidden="true">{{ $icon }}</span>
        @endif
    </div>
</div>
