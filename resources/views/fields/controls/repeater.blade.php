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
        <p class="sf-help">Add and arrange items.</p>
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
                            <div class="sf-repeater-field" data-repeater-field-name="{{ $itemField->name() }}">
                            @include('seviaflow-ui::fields.field-wrapper', [
                                'field' => $itemField,
                                'fieldIdOverride' => $fieldId.'-'.$uiKey.'-'.$itemField->name(),
                                'context' => $itemContext,
                                'modelPath' => $fieldPath.'.'.$index,
                                'modelValues' => $item,
                                'parentDisabled' => $isDisabled,
                                'parentReadonly' => $isReadonly,
                                'formState' => new \SeviaFlow\Core\Forms\FormState($item, $itemContext),
                                'renderer' => app(\SeviaFlow\Ui\Fields\FieldRendererRegistry::class)->resolve($itemField, 'edit'),
                                'inputAttributes' => [],
                                'inputType' => 'text',
                                'rendererData' => [],
                            ])
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
