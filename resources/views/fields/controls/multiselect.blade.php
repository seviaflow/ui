@php
    $selectClasses = ['sf-select', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly];
@endphp
<select
    id="{{ $fieldId }}"
    wire:model="{{ $fieldPath }}"
    multiple
    @disabled($isDisabled)
    @readonly($isReadonly)
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    @class($selectClasses)
>
    @foreach($field->getOptions() as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
    @endforeach
</select>
