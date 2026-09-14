@php
    $value = $value ?? null;
@endphp
@if($value === null || $value === '')
    —
@elseif(method_exists($value, 'format'))
    {!! $value->format('Y-m-d') !!}
@else
    {!! (string) $value !!}
@endif
