<div class="sf-card-body">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="sf-card-title">{{ $widget['label'] }}</h3>
            @if(is_string($widget['description']) && trim($widget['description']) !== '')
                <p class="sf-card-description mt-1">{{ $widget['description'] }}</p>
            @endif
        </div>
    </div>

    @if($rows === [])
        <div class="sf-dashboard-empty mt-4">{{ $emptyMessage }}</div>
    @else
        <ul class="sf-widget-list mt-4">
            @foreach($rows as $row)
                <li class="sf-widget-list-item">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            @if(is_string($row['showUrl']) && $row['showUrl'] !== '')
                                <a href="{{ $row['showUrl'] }}" class="sf-link text-sm font-semibold">{{ $row['identifier'] }}</a>
                            @else
                                <p class="sf-item-title">{{ $row['identifier'] }}</p>
                            @endif
                            <p class="sf-item-meta mt-1">
                                <span class="{{ $row['stateBadgeClass'] }}">{{ $row['stateLabel'] }}</span>
                                <span class="ml-2">Transitions: {{ $row['availableTransitionCount'] }}</span>
                            </p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
