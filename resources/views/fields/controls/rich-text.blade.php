@php
    $richTextToolbarId = $fieldId.'-toolbar';
    $richTextEditorId = $fieldId.'-editor';
    $richTextStatusId = $fieldId.'-status';
@endphp
@if($isReadonly)
    @php($safeRichText = $field->sanitizeForDisplay($inputValue))
    <div
        class="sf-richtext-output sf-richtext-output-readonly"
        data-sf-richtext-output
        aria-labelledby="{{ $fieldId }}-label"
    >
        {!! $safeRichText !== '' ? $safeRichText : '—' !!}
    </div>
@else
    <div
        class="sf-richtext"
        data-sf-richtext
        data-sf-richtext-input-id="{{ $fieldId }}"
        data-sf-richtext-editor-id="{{ $richTextEditorId }}"
        data-sf-richtext-readonly="false"
        data-sf-richtext-disabled="{{ $isDisabled ? 'true' : 'false' }}"
        data-sf-richtext-state="loading"
        data-sf-richtext-max-bytes="{{ $field->getMaxBytes() }}"
    >
        <div
            id="{{ $richTextToolbarId }}"
            class="sf-richtext-toolbar"
            role="toolbar"
            aria-label="Rich text formatting"
            aria-disabled="{{ $isDisabled ? 'true' : 'false' }}"
        >
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="paragraph" aria-label="Paragraph" @disabled($isDisabled)>P</button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="heading-2" aria-label="Heading 2" @disabled($isDisabled)>H2</button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="heading-3" aria-label="Heading 3" @disabled($isDisabled)>H3</button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="bold" aria-label="Bold" @disabled($isDisabled)><strong>B</strong></button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="italic" aria-label="Italic" @disabled($isDisabled)><em>I</em></button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="bullet-list" aria-label="Bulleted list" @disabled($isDisabled)>• List</button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="ordered-list" aria-label="Numbered list" @disabled($isDisabled)>1. List</button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="link" aria-label="Insert link" @disabled($isDisabled)>Link</button>
        </div>

        <p
            id="{{ $richTextStatusId }}"
            class="sf-richtext-status"
            data-sf-richtext-status
            data-state="loading"
            role="status"
            aria-live="polite"
        >Loading rich text editor…</p>
        <button type="button" class="sf-richtext-retry" data-sf-richtext-retry hidden>Retry</button>

        <div
            id="{{ $richTextEditorId }}"
            class="sf-richtext-editor {{ $hasError ? 'sf-input-error' : '' }}"
            data-sf-richtext-editor
            wire:ignore
            role="textbox"
            aria-multiline="true"
            aria-labelledby="{{ $fieldId }}-label"
            aria-disabled="{{ $isDisabled ? 'true' : 'false' }}"
            tabindex="{{ $isDisabled ? '-1' : '0' }}"
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        ></div>
        <textarea
            id="{{ $fieldId }}"
            data-sf-richtext-input
            wire:model.live="{{ $fieldPath }}"
            @disabled($isDisabled)
            @if($isRequired) aria-required="true" @endif
            @if($hasError) aria-invalid="true" @endif
            aria-hidden="true"
            tabindex="-1"
            class="sf-richtext-storage"
        >{{ $inputValue }}</textarea>
    </div>
@endif
