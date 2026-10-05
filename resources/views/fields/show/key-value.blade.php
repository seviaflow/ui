@php $values = is_array($value ?? null) ? $value : []; @endphp
@if($values === [])
    —
@else
    <dl class="sf-value-map">
        @foreach($values as $key => $item)
            <div><dt>{{ (string) $key }}</dt><dd>{{ is_scalar($item) ? (string) $item : '[invalid value]' }}</dd></div>
        @endforeach
    </dl>
@endif
