<input
    id="{{ $fieldId }}"
    type="checkbox"
    wire:model.live="{{ $fieldPath }}"
    @disabled($isDisabled || $isReadonly)
    @if($isReadonly) aria-readonly="true" @endif
    @if($isRequired) aria-required="true" @endif
    @if($hasError) aria-invalid="true" @endif
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    class="sf-checkbox"
>
