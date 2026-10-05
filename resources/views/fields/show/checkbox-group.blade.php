@php $values = is_array($value ?? null) ? $value : []; $options = $field->getOptions(); @endphp
@if($values === [])
    —
@else
    <div class="sf-value-list">
        @foreach($values as $item)
            <span class="sf-value-chip">{{ $options[(string) $item] ?? (string) $item }}</span>
        @endforeach
    </div>
@endif
