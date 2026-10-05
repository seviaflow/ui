@php
    $modelPath = $modelPath ?? 'fieldValues';
    $fieldPath = $modelPath.'.'.$field->name();
    $fieldId = 'field-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $modelPath === 'fieldValues' ? $field->name() : $fieldPath);
    $errorPath = $fieldPath;
    $helpId = $fieldId.'-help';
    $errorId = $fieldId.'-error';
    $hasError = $errors->has($errorPath);
    $formState = $formState ?? null;
    $fieldValue = data_get($modelValues ?? [], $field->name());
    $inputValue = $field->formatForInput($fieldValue);
    $isRequired = $field->isRequiredForState($context, $formState);
    $isDisabled = $field->isDisabledForState($context, $formState);
    $isReadonly = $field->isReadonly($context);
    $help = $field->getHelp();
    if ($help === null && $field instanceof \SeviaFlow\Core\Resources\Fields\PasswordField) {
        $help = $context === \SeviaFlow\Core\Resources\ResourceContext::Edit
            ? 'Leave blank to keep the current password.'
            : 'Use a strong password.';
    }
    $describedBy = trim(($help ? $helpId : '').($hasError ? ' '.$errorId : ''));
@endphp

<div class="sf-field sf-field-span-{{ $field->getColumnSpan() ?? 1 }}" data-field-type="{{ $field->type() }}" @if($isReadonly) aria-readonly="true" @endif>
    <label for="{{ $fieldId }}" class="sf-label">
        {{ $field->getLabel() }}
        @if($isRequired)
            <span class="sf-required" aria-label="{{ __('seviaflow-ui::ui.fields.required') }}">*</span>
        @endif
    </label>

    @include($renderer->view('edit'), array_merge([
        'field' => $field,
        'context' => $context,
        'modelPath' => $modelPath,
        'fieldPath' => $fieldPath,
        'fieldId' => $fieldId,
        'inputValue' => $inputValue,
        'describedBy' => $describedBy,
        'hasError' => $hasError,
        'isDisabled' => $isDisabled,
        'isReadonly' => $isReadonly,
    ], $renderer->data(), $rendererData ?? []))

    @if($help)
        <p id="{{ $helpId }}" class="sf-help">{{ $help }}</p>
    @endif

    @error($errorPath)
        <p id="{{ $errorId }}" class="sf-error">{{ $message }}</p>
    @enderror
</div>
