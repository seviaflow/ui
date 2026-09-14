@php
    $modelPath = $modelPath ?? 'fieldValues';
    $renderer = app(\SeviaFlow\Ui\Fields\FieldRendererRegistry::class)->resolve($field, 'edit');
@endphp

@include('seviaflow-ui::fields.field-wrapper', [
    'field' => $field,
    'context' => $context,
    'modelPath' => $modelPath,
    'renderer' => $renderer,
    'rendererData' => [
        'belongsToOptions' => $belongsToOptions ?? [],
        'inlineCreateAllowed' => $inlineCreateAllowed ?? [],
        'belongsToPlaceholder' => $belongsToPlaceholder ?? [],
        'fileDownloadUrls' => $fileDownloadUrls ?? [],
        'fileHasValues' => $fileHasValues ?? [],
        'fileActions' => $fileActions ?? [],
    ],
])
