@php $colorValue = is_string($inputValue) && preg_match('/^#[0-9A-Fa-f]{6}$/', $inputValue) ? $inputValue : '#000000'; @endphp
<div class="sf-color-control">
    <input
        id="{{ $fieldId }}-picker"
        type="color"
        value="{{ $colorValue }}"
        wire:change="$set('{{ $fieldPath }}', $event.target.value)"
        @disabled($isDisabled || $isReadonly)
        aria-label="Pick {{ $field->getLabel() }} color"
        class="sf-color-picker"
    >
    <input
        id="{{ $fieldId }}"
        type="text"
        wire:model.live="{{ $fieldPath }}"
        value="{{ $inputValue }}"
        @disabled($isDisabled)
        @readonly($isReadonly)
        placeholder="#RRGGBB"
        @if($isRequired) aria-required="true" @endif
        @if($hasError) aria-invalid="true" @endif
        @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        class="sf-input sf-color-text {{ $hasError ? 'sf-input-error' : '' }} {{ $isReadonly ? 'sf-control-readonly' : '' }}"
    >
</div>
