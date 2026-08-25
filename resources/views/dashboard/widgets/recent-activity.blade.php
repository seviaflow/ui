<div class="sf-card-body">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-slate-900">{{ $widget['label'] }}</h3>
            @if(is_string($widget['description']) && trim($widget['description']) !== '')
                <p class="mt-1 text-sm text-slate-500">{{ $widget['description'] }}</p>
            @endif
        </div>
    </div>

    @if($items === [])
        <div class="sf-dashboard-empty mt-4">{{ $emptyMessage }}</div>
    @else
        <ul class="mt-4 space-y-2">
            @foreach($items as $item)
                <li class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-sm font-semibold text-slate-900">{{ $item['resourceLabel'] }}</p>
                    <p class="mt-1 text-sm text-slate-700">
                        {{ $item['transition'] }}:
                        {{ $item['fromState'] ?? 'None' }}
                        <span aria-hidden="true">→</span>
                        {{ $item['toState'] }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $item['recordIdentifier'] }} · by {{ $item['userLabel'] }}
                        @if($item['happenedAt'])
                            · {{ $item['happenedAt'] }}
                        @endif
                    </p>
                    @if(is_string($item['comment']) && trim($item['comment']) !== '')
                        <p class="mt-2 text-xs text-slate-600">Comment: {{ $item['comment'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
