@php
    $selectClasses = ['sf-select', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly];
@endphp
<select
    id="{{ $fieldId }}"
    wire:model="{{ $fieldPath }}"
    @disabled($isDisabled)
    @readonly($isReadonly)
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    @class($selectClasses)
>
    <option value="">{{ $field->getPlaceholder() ?: 'Select an option' }}</option>
    @foreach($field->getOptions() as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
    @endforeach
</select>
