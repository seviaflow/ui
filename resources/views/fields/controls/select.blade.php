@php
    $selectClasses = ['sf-select', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly];
    $searchTerm = mb_strtolower(trim((string) data_get($selectSearch ?? [], $fieldPath, '')));
    $groups = $field->getOptionGroups($formState);
@endphp
@if($field->isSearchable())
    <input type="search" id="{{ $fieldId }}-search" aria-label="Search {{ $field->getLabel() }}" aria-controls="{{ $fieldId }}" wire:model.live.debounce.200ms="selectSearch.{{ $fieldPath }}" class="sf-input" @disabled($isDisabled || $isReadonly)>
@endif
<select
    id="{{ $fieldId }}"
    wire:model.live="{{ $fieldPath }}"
    @disabled($isDisabled || $isReadonly)
    @if($isRequired) aria-required="true" @endif
    @if($hasError) aria-invalid="true" @endif
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    @class($selectClasses)
>
    <option value="">{{ $field->getPlaceholder() ?: 'Select an option' }}</option>
    @foreach($groups as $group => $options)
        @php
            $visibleOptions = array_filter($options, static fn (string $label, int|string $value): bool =>
                ! $field->isSearchable() || $searchTerm === '' || (string) $value === (string) $inputValue
                || str_contains(mb_strtolower($label), $searchTerm) || str_contains(mb_strtolower((string) $value), $searchTerm), ARRAY_FILTER_USE_BOTH);
        @endphp
        @if($group !== '' && $visibleOptions !== [])<optgroup label="{{ $group }}">@endif
        @foreach($visibleOptions as $value => $label)
            <option value="{{ $value }}" @disabled(in_array((string) $value, $field->getDisabledOptions(), true))>{{ $label }}</option>
        @endforeach
        @if($group !== '' && $visibleOptions !== [])</optgroup>@endif
    @endforeach
</select>

