<textarea
    id="{{ $fieldId }}"
    rows="6"
    wire:model.live="{{ $fieldPath }}"
    @disabled($isDisabled)
    @readonly($isReadonly)
    @if($isRequired) aria-required="true" @endif
    @if($hasError) aria-invalid="true" @endif
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    class="sf-textarea sf-key-value-input {{ $hasError ? 'sf-input-error' : '' }} {{ $isReadonly ? 'sf-control-readonly' : '' }}"
>{{ $inputValue }}</textarea>
<p class="sf-help">Enter one flat JSON object, for example: {"key":"value"}. An empty object is valid.</p>
