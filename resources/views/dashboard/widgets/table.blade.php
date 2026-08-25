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
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-slate-500">
                        @foreach($columns as $columnLabel)
                            <th class="px-2 py-2 font-medium">{{ $columnLabel }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="border-b border-slate-100 last:border-0">
                            @foreach($row['cells'] as $index => $cell)
                                <td class="px-2 py-2 text-slate-700">
                                    @if($index === 0 && is_string($row['showUrl']) && $row['showUrl'] !== '')
                                        <a href="{{ $row['showUrl'] }}" class="sf-link">{!! $cell !!}</a>
                                    @else
                                        {!! $cell !!}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

