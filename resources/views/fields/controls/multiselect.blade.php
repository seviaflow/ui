@php
    $isRelationSelect = $field instanceof \SeviaFlow\Core\Resources\Fields\BelongsToManyField;
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
    if ($isRelationSelect) {
        $searchPath = str_replace('MultiSelect', 'BelongsTo', str_replace('multiSelect', 'belongsTo', $searchPath));
        $searchValues = match ($modelPath) {
            'inlineCreateValues' => $inlineCreateBelongsToSearch ?? [],
            'relationModalValues' => $relationModalBelongsToSearch ?? [],
            'relationInlineCreateValues' => $relationInlineCreateBelongsToSearch ?? [],
            default => $belongsToSearch ?? [],
        };
    }
    $allOptions = $isRelationSelect ? ($belongsToOptions[$field->name()] ?? []) : $field->getOptions($formState);
    $searchValue = data_get($searchValues, $field->name(), '');
    $selectedValues = data_get($modelValues ?? [], $field->name(), []);
    $selectedValues = is_array($selectedValues) ? array_values(array_map(static fn (mixed $value): string => (string) $value, $selectedValues)) : [];
    $optionGroupLabels = [];
    if (! $isRelationSelect) {
        foreach ($field->getOptionGroups($formState) as $group => $groupOptions) {
            foreach ($groupOptions as $key => $label) {
                $optionGroupLabels[(string) $key] = $group;
            }
        }
    }
    $searchTerm = mb_strtolower(trim((string) $searchValue));
    $visibleOptions = array_filter(
        $allOptions,
        static fn (mixed $label, int|string $value): bool => ! $field->isSearchable() || $searchTerm === ''
            || str_contains(mb_strtolower((string) $label), $searchTerm)
            || str_contains(mb_strtolower((string) $value), $searchTerm),
        ARRAY_FILTER_USE_BOTH
    );
    $selectedLabels = [];
    foreach ($selectedValues as $selectedValue) {
        $selectedLabels[$selectedValue] = $allOptions[$selectedValue] ?? 'Selected option is not available.';
    }
    $selectedCount = count($selectedValues);
    $atMaximum = $field->getMaxSelected() !== null && $selectedCount >= $field->getMaxSelected();
    $fieldArgument = addslashes($field->name());
    $listId = $fieldId.'-options';
@endphp

<div class="sf-multiselect" data-sf-multiselect>
    @if($field->isSearchable())
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

    @endif

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
        @php $previousGroup = null; @endphp
        @forelse($visibleOptions as $value => $label)
            @php $groupLabel = $optionGroupLabels[(string) $value] ?? ''; @endphp
            @if($groupLabel !== '' && $groupLabel !== $previousGroup)
                <p class="sf-help">{{ $groupLabel }}</p>
            @endif
            @php $previousGroup = $groupLabel; @endphp
            @php $valueArgument = addslashes((string) $value); @endphp
            <button
                type="button"
                role="option"
                aria-selected="{{ in_array((string) $value, $selectedValues, true) ? 'true' : 'false' }}"
                @disabled($isDisabled || $isReadonly || in_array((string) $value, $field->getDisabledOptions(), true) || ($atMaximum && ! in_array((string) $value, $selectedValues, true)))
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

