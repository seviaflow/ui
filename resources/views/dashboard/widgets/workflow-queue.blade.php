<div class="sf-card-body">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-slate-900">{{ $widget['label'] }}</h3>
            @if(is_string($widget['description']) && trim($widget['description']) !== '')
                <p class="mt-1 text-sm text-slate-500">{{ $widget['description'] }}</p>
            @endif
        </div>
    </div>

    @if($rows === [])
        <div class="sf-dashboard-empty mt-4">{{ $emptyMessage }}</div>
    @else
        <ul class="mt-4 space-y-2">
            @foreach($rows as $row)
                <li class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            @if(is_string($row['showUrl']) && $row['showUrl'] !== '')
                                <a href="{{ $row['showUrl'] }}" class="sf-link text-sm font-semibold">{{ $row['identifier'] }}</a>
                            @else
                                <p class="text-sm font-semibold text-slate-900">{{ $row['identifier'] }}</p>
                            @endif
                            <p class="mt-1 text-xs text-slate-500">
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
