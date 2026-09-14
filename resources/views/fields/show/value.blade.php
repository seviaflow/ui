@php
    $value = $value ?? null;
@endphp
@if($value === null || $value === '')
    —
@elseif(is_array($value))
    {!! json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[complex value]' !!}
@elseif(is_object($value) && ($value instanceof Stringable || method_exists($value, '__toString')))
    {!! (string) $value !!}
@elseif(is_object($value) && $value instanceof JsonSerializable)
    {!! json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[complex value]' !!}
@elseif(is_object($value))
    [complex value]
@else
    {{ (string) $value }}
@endif
