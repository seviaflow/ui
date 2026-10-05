@php
    $searchPath = $belongsToSearchPath ?? match ($modelPath) {
        'fieldValues' => 'belongsToSearch',
        'inlineCreateValues' => 'inlineCreateBelongsToSearch',
        'relationModalValues' => 'relationModalBelongsToSearch',
        'relationInlineCreateValues' => 'relationInlineCreateBelongsToSearch',
        default => 'belongsToSearch',
    };
    $selectAction = $belongsToSelectAction ?? match ($modelPath) {
        'fieldValues' => 'selectBelongsToOption',
        'inlineCreateValues' => 'selectInlineCreateBelongsToOption',
        'relationModalValues' => 'selectRelationModalBelongsToOption',
        'relationInlineCreateValues' => 'selectRelationInlineCreateBelongsToOption',
        default => 'selectBelongsToOption',
    };
    $clearAction = $belongsToClearAction ?? match ($modelPath) {
        'fieldValues' => 'clearBelongsToOption',
        'inlineCreateValues' => 'clearInlineCreateBelongsToOption',
        'relationModalValues' => 'clearRelationModalBelongsToOption',
        'relationInlineCreateValues' => 'clearRelationInlineCreateBelongsToOption',
        default => 'clearBelongsToOption',
    };
    $inlineCreateAction = $inlineCreateAction ?? match ($modelPath) {
        'fieldValues' => 'beginInlineCreate',
        'relationModalValues' => 'beginRelationInlineCreate',
        default => null,
    };
    $searchValues = match ($modelPath) {
        'fieldValues' => $belongsToSearch ?? [],
        'inlineCreateValues' => $inlineCreateBelongsToSearch ?? [],
        'relationModalValues' => $relationModalBelongsToSearch ?? [],
        'relationInlineCreateValues' => $relationInlineCreateBelongsToSearch ?? [],
        default => [],
    };
    $searchValue = data_get($searchValues, $field->name(), '');
    $selectedValue = data_get($modelValues ?? [], $field->name());
    $selectedKey = $selectedValue === null ? '' : (string) $selectedValue;
    $options = $belongsToOptions[$field->name()] ?? [];
    $selectedLabel = $selectedKey !== '' && array_key_exists($selectedKey, $options)
        ? $options[$selectedKey]
        : ($selectedKey !== '' ? 'Selected option is not available.' : null);
    $searchModel = $searchPath.'.'.$field->name();
    $listId = $fieldId.'-options';
    $fieldArgument = addslashes($field->name());
@endphp

<div
    class="sf-combobox"
    data-sf-combobox
    data-sf-combobox-query="{{ $searchValue }}"
    data-sf-combobox-field="{{ $fieldId }}"
>
    <div class="sf-combobox-input-row">
        <input
            id="{{ $fieldId }}"
            type="search"
            role="combobox"
            aria-controls="{{ $listId }}"
            aria-expanded="true"
            autocomplete="off"
            wire:model.live.debounce.300ms="{{ $searchModel }}"
            placeholder="{{ $selectedLabel ?: ($belongsToPlaceholder[$field->name()] ?? null) ?: $field->getPlaceholder() ?: 'Select an option' }}"
            @disabled($isDisabled || $isReadonly)
            @if($isRequired) aria-required="true" @endif
            @if($hasError) aria-invalid="true" @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @class(['sf-input', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly])
        >
        @if($selectedKey !== '' && ! $isDisabled && ! $isReadonly)
            <button
                type="button"
                class="sf-combobox-clear"
                aria-label="Clear selected {{ $field->getLabel() }}"
                wire:click="{{ $clearAction }}('{{ $fieldArgument }}')"
            >
                ×
            </button>
        @endif
    </div>

    @if($selectedLabel)
        <p class="sf-combobox-selected" data-sf-combobox-selected>
            <span class="sf-item-meta">Selected:</span> {{ $selectedLabel }}
        </p>
    @endif

    <div id="{{ $listId }}" class="sf-combobox-options" role="listbox" aria-label="{{ $field->getLabel() }} options">
        @forelse($options as $value => $label)
            @php
                $valueArgument = addslashes((string) $value);
            @endphp
            <button
                type="button"
                role="option"
                aria-selected="{{ $selectedKey === (string) $value ? 'true' : 'false' }}"
                class="sf-combobox-option"
                wire:click="{{ $selectAction }}('{{ $fieldArgument }}', '{{ $valueArgument }}')"
            >
                <span>{{ $label }}</span>
                @if($selectedKey === (string) $value)<span aria-hidden="true">✓</span>@endif
            </button>
        @empty
            <p class="sf-combobox-empty">{{ $searchValue !== '' ? 'No matching options.' : 'No options available.' }}</p>
        @endforelse
    </div>
</div>

@if($inlineCreateAction !== null && ($inlineCreateAllowed[$field->name()] ?? false))
    <button type="button" wire:click="{{ $inlineCreateAction }}('{{ $fieldArgument }}')" class="sf-button sf-button-secondary mt-2 !px-3 !py-1.5">
        {{ $field->getInlineCreateLabel() ?? ('Create '.$field->getLabel()) }}
    </button>
@endif
