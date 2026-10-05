@php
    $selectClasses = ['sf-select', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly];
@endphp
<select
    id="{{ $fieldId }}"
    wire:model.live="{{ $fieldPath }}"
    @disabled($isDisabled || $isReadonly)
    @if($isRequired) aria-required="true" @endif
    @if($hasError) aria-invalid="true" @endif
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    @class($selectClasses)
>
    <option value="">{{ $field->getPlaceholder() ?: 'Select an option' }}</option>
    @foreach($field->getOptions() as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
    @endforeach
</select>
