@php
    $modelPath = $modelPath ?? 'fieldValues';
    $repeaterUiKeys = $repeaterUiKeys ?? [];
    $errors = $errors ?? new \Illuminate\Support\MessageBag;
    $formState = $formState ?? new \SeviaFlow\Core\Forms\FormState($modelValues ?? [], $context);
    $repeaterKeys = $repeaterUiKeys[$modelPath][$field->name()] ?? [];
    $items = is_array($modelValues[$field->name()] ?? null) ? array_values($modelValues[$field->name()]) : [];
    $itemContext = $context;
    $itemModelPath = $modelPath;
@endphp

<div id="{{ $fieldId }}" tabindex="-1" class="sf-repeater" data-sf-repeater data-repeater-field="{{ $field->name() }}" @if($hasError) aria-invalid="true" @endif>
    <div class="sf-repeater-header">
        <p class="sf-help">One-level JSON objects. Values are stored without UI row keys.</p>
        <button
            type="button"
            wire:click="addRepeaterItem('{{ addslashes($field->name()) }}', '{{ addslashes($modelPath) }}')"
            class="sf-button sf-button-secondary !px-3 !py-1.5"
            @disabled($isDisabled || $isReadonly || ($field->getMaxItems() !== null && count($items) >= $field->getMaxItems()))
        >
            Add item
        </button>
    </div>

    @if($items === [])
        <p class="sf-repeater-empty">No items yet.</p>
    @else
        <div class="sf-repeater-items">
            @foreach($items as $index => $item)
                @php
                    $uiKey = $repeaterKeys[$index] ?? $modelPath.'-'.$field->name().'-'.$index;
                    $item = is_array($item) ? $item : [];
                    $labelField = $field->getItemLabelField();
                    $itemLabel = $labelField !== null ? ($item[$labelField] ?? null) : null;
                    $itemLabel = is_scalar($itemLabel) && (string) $itemLabel !== '' ? (string) $itemLabel : 'Item '.($index + 1);
                @endphp
                <div id="{{ $fieldId.'-'.$uiKey }}" tabindex="-1" class="sf-repeater-item" wire:key="{{ $uiKey }}" data-repeater-ui-key="{{ $uiKey }}" data-repeater-row-index="{{ $index }}">
                    <div class="sf-repeater-item-header">
                        @if($field->isCollapsible())
                            <span class="sf-repeater-item-title">{{ $itemLabel }}</span>
                        @else
                            <span class="sf-repeater-item-title">Item {{ $index + 1 }}{{ $itemLabel !== 'Item '.($index + 1) ? ': '.$itemLabel : '' }}</span>
                        @endif
                        <div class="sf-repeater-actions">
                            @if($field->isReorderable())
                                <button
                                    type="button"
                                    wire:click="moveRepeaterItemUp('{{ addslashes($field->name()) }}', '{{ addslashes($uiKey) }}', '{{ addslashes($modelPath) }}')"
                                    class="sf-button sf-button-secondary !px-2 !py-1"
                                    aria-label="Move item {{ $index + 1 }} up"
                                    @disabled($isDisabled || $isReadonly || $index === 0)
                                >↑</button>
                                <button
                                    type="button"
                                    wire:click="moveRepeaterItemDown('{{ addslashes($field->name()) }}', '{{ addslashes($uiKey) }}', '{{ addslashes($modelPath) }}')"
                                    class="sf-button sf-button-secondary !px-2 !py-1"
                                    aria-label="Move item {{ $index + 1 }} down"
                                    @disabled($isDisabled || $isReadonly || $index === count($items) - 1)
                                >↓</button>
                            @endif
                            @if($field->isDeletable())
                                <button
                                    type="button"
                                    wire:click="removeRepeaterItem('{{ addslashes($field->name()) }}', '{{ addslashes($uiKey) }}', '{{ addslashes($modelPath) }}')"
                                    class="sf-button sf-button-secondary !px-2 !py-1"
                                    aria-label="Remove item {{ $index + 1 }}"
                                    @disabled($isDisabled || $isReadonly || ($field->getMinItems() !== null && count($items) <= $field->getMinItems()))
                                >Remove</button>
                            @endif
                        </div>
                    </div>

                    @if($field->isCollapsible())
                        <details open class="sf-repeater-details">
                            <summary class="sf-repeater-summary">{{ $itemLabel }}</summary>
                    @endif

                    <div class="sf-repeater-grid">
                        @foreach($field->getItemFields() as $itemField)
                            @php
                                $itemPath = $fieldPath.'.'.$index.'.'.$itemField->name();
                                $itemId = $fieldId.'-'.$uiKey.'-'.$itemField->name();
                                $itemValue = $item[$itemField->name()] ?? null;
                                $safeItemValue = is_scalar($itemValue) ? (string) $itemValue : '';
                                $itemError = $errors->has($itemPath);
                                $itemRequired = $itemField->isRequiredForState($itemContext, $formState);
                                $itemDisabled = $isDisabled || $itemField->isDisabledForState($itemContext, $formState);
                                $itemReadonly = $isReadonly || $itemField->isReadonly($itemContext);
                                $itemDescribedBy = $itemError ? $itemId.'-error' : '';
                            @endphp
                            <div class="sf-repeater-field" data-repeater-field-name="{{ $itemField->name() }}">
                                <label for="{{ $itemId }}" class="sf-label">
                                    {{ $itemField->getLabel() }}
                                    @if($itemRequired)<span class="sf-required" aria-label="Required">*</span>@endif
                                </label>
                                @if($itemField instanceof \SeviaFlow\Core\Resources\Fields\SelectField)
                                    <select
                                        id="{{ $itemId }}"
                                        wire:key="{{ $itemPath }}"
                                        wire:model.live="{{ $itemPath }}"
                                        class="sf-select {{ $itemError ? 'sf-input-error' : '' }}"
                                        @disabled($itemDisabled || $itemReadonly)
                                        @if($itemRequired) aria-required="true" @endif
                                        @if($itemError) aria-invalid="true" aria-describedby="{{ $itemDescribedBy }}" @endif
                                    >
                                        <option value="">Select an option</option>
                                        @foreach($itemField->getOptions() as $optionValue => $optionLabel)
                                            <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @elseif($itemField instanceof \SeviaFlow\Core\Resources\Fields\ToggleField)
                                    <label class="sf-toggle-control" for="{{ $itemId }}">
                                        <input
                                            id="{{ $itemId }}"
                                            type="checkbox"
                                            wire:key="{{ $itemPath }}"
                                            wire:model.live="{{ $itemPath }}"
                                            class="sf-toggle-input"
                                            @checked((bool) $itemValue)
                                            @disabled($itemDisabled || $itemReadonly)
                                        >
                                        <span>Enabled</span>
                                    </label>
                                @else
                                    <input
                                        id="{{ $itemId }}"
                                        type="{{ $itemField instanceof \SeviaFlow\Core\Resources\Fields\NumberField || $itemField instanceof \SeviaFlow\Core\Resources\Fields\MoneyField ? 'number' : 'text' }}"
                                        wire:key="{{ $itemPath }}"
                                        wire:model.live="{{ $itemPath }}"
                                        value="{{ $safeItemValue }}"
                                        class="sf-input {{ $itemError ? 'sf-input-error' : '' }}"
                                        @if($itemField instanceof \SeviaFlow\Core\Resources\Fields\NumberField) step="{{ $itemField->getStep() ?? 'any' }}" @endif
                                        @disabled($itemDisabled)
                                        @readonly($itemReadonly)
                                        @if($itemRequired) aria-required="true" @endif
                                        @if($itemError) aria-invalid="true" aria-describedby="{{ $itemDescribedBy }}" @endif
                                    >
                                @endif
                                @if($itemError)
                                    @error($itemPath)
                                        <p id="{{ $itemId }}-error" class="sf-error">{{ $message }}</p>
                                    @enderror
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if($field->isCollapsible())
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
