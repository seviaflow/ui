@php
    $textareaClasses = ['sf-textarea', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly];
@endphp
<textarea
    id="{{ $fieldId }}"
    rows="{{ $field->getRows() }}"
    wire:model.live="{{ $fieldPath }}"
    @disabled($isDisabled)
    @readonly($isReadonly)
    @if($isRequired) aria-required="true" @endif
    @if($hasError) aria-invalid="true" @endif
    placeholder="{{ $field->getPlaceholder() }}"
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    @class($textareaClasses)
></textarea>
