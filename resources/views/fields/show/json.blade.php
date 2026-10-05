@php
    $encoded = json_encode($value ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp
@if($value === null || $value === '')
    —
@else
    <pre class="sf-json-value">{{ is_string($encoded) ? $encoded : '[invalid JSON value]' }}</pre>
@endif
