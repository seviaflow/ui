@php $values = is_array($value ?? null) ? $value : []; @endphp
@if($values === [])
    —
@else
    <div class="sf-value-list">
        @foreach($values as $item)
            <span class="sf-value-chip">{{ (string) $item }}</span>
        @endforeach
    </div>
@endif
