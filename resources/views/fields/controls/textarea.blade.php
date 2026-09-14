@php
    $textareaClasses = ['sf-textarea', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly];
@endphp
<textarea
    id="{{ $fieldId }}"
    rows="{{ $field->getRows() }}"
    wire:model="{{ $fieldPath }}"
    @disabled($isDisabled)
    @readonly($isReadonly)
    placeholder="{{ $field->getPlaceholder() }}"
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    @class($textareaClasses)
></textarea>
