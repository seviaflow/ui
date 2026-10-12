@php
    $value = $value ?? null;
    $options = $field->getOptions(new \SeviaFlow\Core\Forms\FormState(($record ?? null)?->getAttributes() ?? [], \SeviaFlow\Core\Resources\ResourceContext::Show, $record ?? null));
    $key = (string) $value;
@endphp
{!! array_key_exists($key, $options) ? $options[$key] : (($value === null || $value === '') ? '—' : $key) !!}
