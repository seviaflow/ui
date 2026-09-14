<input
    id="{{ $fieldId }}"
    type="checkbox"
    wire:model="{{ $fieldPath }}"
    @disabled($isDisabled)
    @readonly($isReadonly)
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    class="sf-checkbox"
>
