<fieldset class="sf-choice-group" @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
    <legend class="sr-only">{{ $field->getLabel() }}</legend>
    @foreach($field->getOptions() as $value => $label)
        @php $optionId = $fieldId.'-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $value); @endphp
        <label for="{{ $optionId }}" class="sf-choice-option">
            <input
                id="{{ $optionId }}"
                type="checkbox"
                value="{{ $value }}"
                wire:model.live="{{ $fieldPath }}"
                @disabled($isDisabled || $isReadonly)
                @if($isRequired) aria-required="true" @endif
                @if($hasError) aria-invalid="true" @endif
                class="sf-checkbox"
            >
            <span class="sf-choice-label">{{ $label }}</span>
        </label>
    @endforeach
</fieldset>
