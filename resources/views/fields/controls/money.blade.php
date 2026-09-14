@php
    $controlClasses = ['sf-input', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly, 'pl-14'];
@endphp
<div class="relative">
    <span class="sf-input-prefix">{{ $field->getCurrency() }}</span>
    <input
        id="{{ $fieldId }}"
        type="number"
        step="{{ 1 / (10 ** $field->getScale()) }}"
        wire:model="{{ $fieldPath }}"
        @disabled($isDisabled)
        @readonly($isReadonly)
        placeholder="{{ $field->getPlaceholder() }}"
        @if($field->getMin() !== null) min="{{ $field->getMin() }}" @endif
        @if($field->getMax() !== null) max="{{ $field->getMax() }}" @endif
        @if($isRequired) aria-required="true" @endif
        @if($hasError) aria-invalid="true" @endif
        @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @class($controlClasses)
    >
</div>
