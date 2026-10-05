@php
    $controlClasses = ['sf-input', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly];
    $inputAttributes = isset($inputAttributes) && is_callable($inputAttributes)
        ? $inputAttributes($field)
        : (is_array($inputAttributes ?? null) ? $inputAttributes : []);
@endphp
<input
    id="{{ $fieldId }}"
    type="{{ $inputType }}"
    wire:model.live="{{ $fieldPath }}"
    value="{{ $inputValue }}"
    @disabled($isDisabled)
    @readonly($isReadonly)
    placeholder="{{ $field->getPlaceholder() }}"
    @foreach($inputAttributes as $attribute => $value)
        {{ $attribute }}="{{ $value }}"
    @endforeach
    @if($isRequired) aria-required="true" @endif
    @if($hasError) aria-invalid="true" @endif
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    @class($controlClasses)
>
