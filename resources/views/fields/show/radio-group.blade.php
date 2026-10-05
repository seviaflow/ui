@php
    $value = $value ?? null;
    $options = $field->getOptions();
    $key = (string) $value;
@endphp
{{ $value === null || $value === '' ? '—' : ($options[$key] ?? $key) }}
