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
<p class="sf-help">{{ __('seviaflow-ui::panel.key_value_help') }}</p>
