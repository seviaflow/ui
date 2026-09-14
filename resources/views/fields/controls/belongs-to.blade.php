@php
    $selectClasses = ['sf-select', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly];
@endphp
@if($field->getSearchableOptionColumns() !== [])
    <input
        id="field-search-{{ $field->name() }}"
        type="search"
        wire:model.live.debounce.300ms="belongsToSearch.{{ $field->name() }}"
        placeholder="Search options"
        class="sf-input mb-2"
    >
@endif
<select
    id="{{ $fieldId }}"
    wire:model="{{ $fieldPath }}"
    @disabled($isDisabled)
    @readonly($isReadonly)
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    @class($selectClasses)
>
    <option value="">{{ ($belongsToPlaceholder[$field->name()] ?? null) ?: $field->getPlaceholder() ?: 'Select an option' }}</option>
    @foreach(($belongsToOptions[$field->name()] ?? []) as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
    @endforeach
</select>
@if($modelPath === 'fieldValues' && ($inlineCreateAllowed[$field->name()] ?? false))
    <button type="button" wire:click="beginInlineCreate('{{ $field->name() }}')" class="sf-button sf-button-secondary mt-2 !px-3 !py-1.5">
        {{ $field->getInlineCreateLabel() ?? ('Create '.$field->getLabel()) }}
    </button>
@endif
