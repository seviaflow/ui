@php
    $richTextClasses = ['sf-richtext-storage', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly];
    $richTextToolbarId = $fieldId.'-toolbar';
    $richTextEditorId = $fieldId.'-editor';
@endphp
@if($isReadonly)
    @php($safeRichText = $field->sanitizeForDisplay($inputValue))
    <div class="sf-richtext-output sf-richtext-output-readonly">
        {!! $safeRichText !== '' ? $safeRichText : '—' !!}
    </div>
@else
    <div
        class="sf-richtext"
        data-sf-richtext
        data-sf-richtext-input-id="{{ $fieldId }}"
        data-sf-richtext-editor-id="{{ $richTextEditorId }}"
        data-sf-richtext-readonly="false"
        data-sf-richtext-max-bytes="{{ $field->getMaxBytes() }}"
    >
        <div id="{{ $richTextToolbarId }}" class="sf-richtext-toolbar" role="toolbar" aria-label="Rich text formatting">
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="paragraph" aria-label="Paragraph">P</button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="heading-2" aria-label="Heading 2">H2</button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="heading-3" aria-label="Heading 3">H3</button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="bold" aria-label="Bold"><strong>B</strong></button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="italic" aria-label="Italic"><em>I</em></button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="bullet-list" aria-label="Bulleted list">• List</button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="ordered-list" aria-label="Numbered list">1. List</button>
            <button type="button" class="sf-richtext-tool" data-sf-richtext-command="link" aria-label="Insert link">Link</button>
        </div>

        <div
            id="{{ $richTextEditorId }}"
            class="sf-richtext-editor {{ $hasError ? 'sf-input-error' : '' }}"
            data-sf-richtext-editor
            wire:ignore
            role="textbox"
            aria-multiline="true"
            aria-label="{{ $field->getLabel() }}"
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        ></div>
        <textarea
            id="{{ $fieldId }}"
            data-sf-richtext-input
            wire:model.live="{{ $fieldPath }}"
            @disabled($isDisabled)
            @if($isRequired) aria-required="true" @endif
            @if($hasError) aria-invalid="true" @endif
            class="sf-richtext-storage"
        >{{ $inputValue }}</textarea>
    </div>
@endif
