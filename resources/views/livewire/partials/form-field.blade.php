@php
    $modelPath = $modelPath ?? 'fieldValues';
    $modelValues = match ($modelPath) {
        'fieldValues' => $fieldValues ?? [],
        'inlineCreateValues' => $inlineCreateValues ?? [],
        'relationModalValues' => $relationModalValues ?? [],
        'relationInlineCreateValues' => $relationInlineCreateValues ?? [],
        'pivotModalValues' => $pivotModalValues ?? [],
        default => [],
    };
    $formState = $formState ?? new \SeviaFlow\Core\Forms\FormState(
        $modelValues,
        $context,
        $modelPath === 'fieldValues' ? ($record ?? null) : null,
    );
    $renderer = app(\SeviaFlow\Ui\Fields\FieldRendererRegistry::class)->resolve($field, 'edit');
@endphp

@include('seviaflow-ui::fields.field-wrapper', [
    'field' => $field,
    'context' => $context,
    'modelPath' => $modelPath,
    'modelValues' => $modelValues,
    'renderer' => $renderer,
    'formState' => $formState,
    'rendererData' => [
        'belongsToOptions' => $belongsToOptions ?? [],
        'inlineCreateAllowed' => $inlineCreateAllowed ?? [],
        'belongsToPlaceholder' => $belongsToPlaceholder ?? [],
        'belongsToSearch' => $belongsToSearch ?? [],
        'relationModalBelongsToSearch' => $relationModalBelongsToSearch ?? [],
        'inlineCreateBelongsToSearch' => $inlineCreateBelongsToSearch ?? [],
        'relationInlineCreateBelongsToSearch' => $relationInlineCreateBelongsToSearch ?? [],
        'multiSelectSearch' => $multiSelectSearch ?? [],
        'relationModalMultiSelectSearch' => $relationModalMultiSelectSearch ?? [],
        'inlineCreateMultiSelectSearch' => $inlineCreateMultiSelectSearch ?? [],
        'relationInlineCreateMultiSelectSearch' => $relationInlineCreateMultiSelectSearch ?? [],
        'fileDownloadUrls' => $fileDownloadUrls ?? [],
        'fileHasValues' => $fileHasValues ?? [],
        'fileMetadata' => $fileMetadata ?? [],
        'filePending' => $filePending ?? [],
        'fileActions' => $fileActions ?? [],
        'repeaterUiKeys' => $repeaterUiKeys ?? [],
    ],
])
