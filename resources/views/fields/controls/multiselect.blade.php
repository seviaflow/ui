@php
    $searchPath = $multiSelectSearchPath ?? match ($modelPath) {
        'fieldValues' => 'multiSelectSearch',
        'inlineCreateValues' => 'inlineCreateMultiSelectSearch',
        'relationModalValues' => 'relationModalMultiSelectSearch',
        'relationInlineCreateValues' => 'relationInlineCreateMultiSelectSearch',
        default => 'multiSelectSearch',
    };
    $toggleAction = $multiSelectToggleAction ?? match ($modelPath) {
        'fieldValues' => 'toggleMultiSelectOption',
        'inlineCreateValues' => 'toggleInlineCreateMultiSelectOption',
        'relationModalValues' => 'toggleRelationModalMultiSelectOption',
        'relationInlineCreateValues' => 'toggleRelationInlineCreateMultiSelectOption',
        default => 'toggleMultiSelectOption',
    };
    $removeAction = $multiSelectRemoveAction ?? match ($modelPath) {
        'fieldValues' => 'removeMultiSelectOption',
        'inlineCreateValues' => 'removeInlineCreateMultiSelectOption',
        'relationModalValues' => 'removeRelationModalMultiSelectOption',
        'relationInlineCreateValues' => 'removeRelationInlineCreateMultiSelectOption',
        default => 'removeMultiSelectOption',
    };
    $searchValues = match ($modelPath) {
        'fieldValues' => $multiSelectSearch ?? [],
        'inlineCreateValues' => $inlineCreateMultiSelectSearch ?? [],
        'relationModalValues' => $relationModalMultiSelectSearch ?? [],
        'relationInlineCreateValues' => $relationInlineCreateMultiSelectSearch ?? [],
        default => [],
    };
    $searchValue = data_get($searchValues, $field->name(), '');
    $selectedValues = data_get($modelValues ?? [], $field->name(), []);
    $selectedValues = is_array($selectedValues) ? array_values(array_map(static fn (mixed $value): string => (string) $value, $selectedValues)) : [];
    $searchTerm = mb_strtolower(trim((string) $searchValue));
    $visibleOptions = array_filter(
        $field->getOptions(),
        static fn (mixed $label, int|string $value): bool => $searchTerm === ''
            || str_contains(mb_strtolower((string) $label), $searchTerm)
            || str_contains(mb_strtolower((string) $value), $searchTerm),
        ARRAY_FILTER_USE_BOTH
    );
    $selectedLabels = [];
    foreach ($selectedValues as $selectedValue) {
        $selectedLabels[$selectedValue] = $field->getOptions()[$selectedValue] ?? 'Selected option is not available.';
    }
    $selectedCount = count($selectedValues);
    $atMaximum = $field->getMaxSelected() !== null && $selectedCount >= $field->getMaxSelected();
    $fieldArgument = addslashes($field->name());
    $listId = $fieldId.'-options';
@endphp

<div class="sf-multiselect" data-sf-multiselect>
    <input
        id="{{ $fieldId }}-search"
        type="search"
        role="searchbox"
        autocomplete="off"
        placeholder="Search {{ strtolower($field->getLabel()) }}"
        wire:model.live.debounce.200ms="{{ $searchPath }}.{{ $field->name() }}"
        @disabled($isDisabled || $isReadonly)
        @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @class(['sf-input', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly])
    >

    <div class="sf-multiselect-selected" aria-label="Selected {{ $field->getLabel() }}">
        @forelse($selectedLabels as $value => $label)
            <span class="sf-multiselect-chip">
                <span>{{ $label }}</span>
                @if(! $isDisabled && ! $isReadonly)
                    <button
                        type="button"
                        aria-label="Remove {{ $label }}"
                        wire:click="{{ $removeAction }}('{{ $fieldArgument }}', '{{ addslashes((string) $value) }}')"
                    >×</button>
                @endif
            </span>
        @empty
            <span class="sf-combobox-empty">No selections.</span>
        @endforelse
    </div>

    <div id="{{ $listId }}" class="sf-multiselect-options" role="listbox" aria-multiselectable="true" aria-label="{{ $field->getLabel() }} options">
        @forelse($visibleOptions as $value => $label)
            @php $valueArgument = addslashes((string) $value); @endphp
            <button
                type="button"
                role="option"
                aria-selected="{{ in_array((string) $value, $selectedValues, true) ? 'true' : 'false' }}"
                @disabled($isDisabled || $isReadonly || ($atMaximum && ! in_array((string) $value, $selectedValues, true)))
                wire:click="{{ $toggleAction }}('{{ $fieldArgument }}', '{{ $valueArgument }}')"
                class="sf-multiselect-option"
            >
                <span>{{ $label }}</span>
                @if(in_array((string) $value, $selectedValues, true))<span aria-hidden="true">✓</span>@endif
            </button>
        @empty
            <p class="sf-combobox-empty">{{ $searchTerm === '' ? 'No options available.' : 'No matching options.' }}</p>
        @endforelse
    </div>

    <p class="sf-help" aria-live="polite">
        {{ $selectedCount }} selected{{ $field->getMaxSelected() !== null ? ' of '.$field->getMaxSelected() : '' }}.
        @if($atMaximum) Maximum reached.@endif
    </p>
</div>
