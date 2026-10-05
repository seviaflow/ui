@php
    $items = is_array($value ?? null) ? array_values($value) : [];
@endphp

@if($items === [])
    —
@else
    <div class="sf-repeater-value" data-sf-repeater-value>
        @foreach($items as $index => $item)
            @php
                $item = is_array($item) ? $item : [];
                $labelField = $field->getItemLabelField();
                $itemLabel = $labelField !== null ? ($item[$labelField] ?? null) : null;
                $itemLabel = is_scalar($itemLabel) && (string) $itemLabel !== '' ? (string) $itemLabel : 'Item '.($index + 1);
            @endphp
            <section class="sf-repeater-value-item">
                <h3 class="sf-repeater-value-title">{{ $itemLabel }}</h3>
                <dl class="sf-repeater-value-list">
                    @foreach($field->getItemFields() as $itemField)
                        @php
                            $itemValue = $item[$itemField->name()] ?? null;
                            if ($itemField instanceof \SeviaFlow\Core\Resources\Fields\SelectField) {
                                $itemDisplay = $itemField->getOptions()[(string) $itemValue] ?? ($itemValue === null || $itemValue === '' ? '—' : (string) $itemValue);
                            } elseif ($itemField instanceof \SeviaFlow\Core\Resources\Fields\ToggleField) {
                                $itemDisplay = (bool) $itemValue ? 'Yes' : 'No';
                            } elseif (is_scalar($itemValue) && $itemValue !== '') {
                                $itemDisplay = (string) $itemValue;
                            } else {
                                $itemDisplay = '—';
                            }
                        @endphp
                        <div>
                            <dt>{{ $itemField->getLabel() }}</dt>
                            <dd>{{ $itemDisplay }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>
@endif
