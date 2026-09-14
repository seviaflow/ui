@php
    $modelPath = $modelPath ?? 'fieldValues';
    $fieldPath = $modelPath.'.'.$field->name();
    $fieldId = 'field-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $modelPath === 'fieldValues' ? $field->name() : $fieldPath);
    $errorPath = $fieldPath;
    $helpId = $fieldId.'-help';
    $errorId = $fieldId.'-error';
    $hasError = $errors->has($errorPath);
    $isRequired = $field->isRequired($context);
    $isDisabled = $field->isDisabled($context);
    $isReadonly = $field->isReadonly($context);
    $describedBy = trim(($field->getHelp() ? $helpId : '').($hasError ? ' '.$errorId : ''));
@endphp

<div class="sf-field" data-field-type="{{ $field->type() }}" @if($isReadonly) aria-readonly="true" @endif>
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
        'describedBy' => $describedBy,
        'hasError' => $hasError,
        'isDisabled' => $isDisabled,
        'isReadonly' => $isReadonly,
    ], $renderer->data(), $rendererData ?? []))

    @if($field->getHelp())
        <p id="{{ $helpId }}" class="sf-help">{{ $field->getHelp() }}</p>
    @endif

    @error($errorPath)
        <p id="{{ $errorId }}" class="sf-error">{{ $message }}</p>
    @enderror
</div>
