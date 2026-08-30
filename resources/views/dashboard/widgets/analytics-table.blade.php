<div class="sf-card-body">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="sf-card-title">{{ $widget['label'] }}</h3>
            @if(is_string($widget['description']) && trim($widget['description']) !== '')
                <p class="sf-card-description mt-1">{{ $widget['description'] }}</p>
            @endif
        </div>
    </div>

    @if($state === 'loading')
        <div class="sf-dashboard-empty mt-4">Loading analytics...</div>
    @elseif($state === 'error')
        <div class="sf-dashboard-empty mt-4">{{ $message ?: 'Unable to load analytics.' }}</div>
    @elseif($state === 'empty' || $rows === [])
        <div class="sf-dashboard-empty mt-4">{{ $emptyMessage }}</div>
    @else
        <div class="sf-table-wrapper mt-4">
            <table class="sf-table !min-w-full">
                <thead>
                    <tr>
                        @foreach($columns as $columnLabel)
                            <th class="sf-table-header">{{ $columnLabel }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td class="sf-table-cell">{!! $cell !!}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
