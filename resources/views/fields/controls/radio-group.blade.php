<fieldset class="sf-choice-group" @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
    <legend class="sr-only">{{ $field->getLabel() }}</legend>
    @foreach($field->getOptions() as $value => $label)
        @php $optionId = $fieldId.'-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $value); @endphp
        <label for="{{ $optionId }}" class="sf-choice-option">
            <input
                id="{{ $optionId }}"
                type="radio"
                name="{{ $fieldId }}"
                value="{{ $value }}"
                wire:model.live="{{ $fieldPath }}"
                @disabled($isDisabled || $isReadonly)
                @if($isRequired) aria-required="true" @endif
                @if($hasError) aria-invalid="true" @endif
                class="sf-radio"
            >
            <span>
                <span class="sf-choice-label">{{ $label }}</span>
                @if(isset($field->getOptionDescriptions()[$value]))
                    <span class="sf-choice-description">{{ $field->getOptionDescriptions()[$value] }}</span>
                @endif
            </span>
        </label>
    @endforeach
</fieldset>
