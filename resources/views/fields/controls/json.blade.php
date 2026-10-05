<textarea
    id="{{ $fieldId }}"
    rows="8"
    wire:model.live="{{ $fieldPath }}"
    @disabled($isDisabled)
    @readonly($isReadonly)
    @if($isRequired) aria-required="true" @endif
    @if($hasError) aria-invalid="true" @endif
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    class="sf-textarea sf-json-input {{ $hasError ? 'sf-input-error' : '' }} {{ $isReadonly ? 'sf-control-readonly' : '' }}"
>{{ $inputValue }}</textarea>
<p class="sf-help">JSON root: {{ $field->getRootType() }}. Code is never executed.</p>
