<input
    id="{{ $fieldId }}"
    type="text"
    wire:model.live="{{ $fieldPath }}"
    value="{{ $inputValue }}"
    @disabled($isDisabled)
    @readonly($isReadonly)
    placeholder="{{ $field->getPlaceholder() ?: 'tag-one, tag-two' }}"
    @if($isRequired) aria-required="true" @endif
    @if($hasError) aria-invalid="true" @endif
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    class="sf-input sf-tags-input {{ $hasError ? 'sf-input-error' : '' }} {{ $isReadonly ? 'sf-control-readonly' : '' }}"
>
<p class="sf-help">Separate tags with commas. Duplicate and blank tags are removed on save.</p>
