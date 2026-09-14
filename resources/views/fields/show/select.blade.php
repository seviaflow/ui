@php
    $value = $value ?? null;
    $options = $field->getOptions();
    $key = (string) $value;
@endphp
{!! array_key_exists($key, $options) ? $options[$key] : (($value === null || $value === '') ? '—' : $key) !!}
