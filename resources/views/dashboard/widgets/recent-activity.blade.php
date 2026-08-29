<div class="sf-card-body">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="sf-card-title">{{ $widget['label'] }}</h3>
            @if(is_string($widget['description']) && trim($widget['description']) !== '')
                <p class="sf-card-description mt-1">{{ $widget['description'] }}</p>
            @endif
        </div>
    </div>

    @if($items === [])
        <div class="sf-dashboard-empty mt-4">{{ $emptyMessage }}</div>
    @else
        <ul class="sf-widget-list mt-4">
            @foreach($items as $item)
                <li class="sf-widget-list-item">
                    <p class="sf-item-title">{{ $item['resourceLabel'] }}</p>
                    <p class="sf-item-body mt-1">
                        {{ $item['transition'] }}:
                        {{ $item['fromState'] ?? 'None' }}
                        <span aria-hidden="true">→</span>
                        {{ $item['toState'] }}
                    </p>
                    <p class="sf-item-meta mt-1">
                        {{ $item['recordIdentifier'] }} · by {{ $item['userLabel'] }}
                        @if($item['happenedAt'])
                            · {{ $item['happenedAt'] }}
                        @endif
                    </p>
                    @if(is_string($item['comment']) && trim($item['comment']) !== '')
                        <p class="sf-item-meta mt-2">Comment: {{ $item['comment'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
