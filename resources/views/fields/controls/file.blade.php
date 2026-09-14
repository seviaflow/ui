@php
    $controlClasses = ['sf-input', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly];
    $fileHasValue = (bool) ($fileHasValues[$field->name()] ?? false);
    $fileDownloadUrl = $fileDownloadUrls[$field->name()] ?? null;
    $fileAction = $fileActions[$field->name()] ?? 'retain';
@endphp
<div class="sf-file-drop">
    <input
        id="{{ $fieldId }}"
        type="file"
        wire:model="{{ $fieldPath }}"
        @disabled($isDisabled)
        @if($field->getAcceptedFileTypes() !== []) accept="{{ implode(',', $field->getAcceptedFileTypes()) }}" @endif
        @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @class($controlClasses)
    >
    @if($modelPath === 'fieldValues' && $fileHasValue)
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
            <span class="sf-help">A file is currently stored.</span>
            @if($fileDownloadUrl)
                <a href="{{ $fileDownloadUrl }}" class="sf-link">Download current file</a>
            @endif
            @if($fileAction === 'remove')
                <span class="sf-item-meta">Removal will be applied on save.</span>
                @if(! $isDisabled && ! $isReadonly)
                    <button type="button" wire:click="retainFile('{{ $field->name() }}')" class="sf-button sf-button-secondary !px-3 !py-1.5">Keep current file</button>
                @endif
            @elseif(! $isDisabled && ! $isReadonly)
                <button type="button" wire:click="removeFile('{{ $field->name() }}')" class="sf-button sf-button-secondary !px-3 !py-1.5">Remove current file</button>
            @endif
        </div>
    @endif
    <p class="sf-help mt-2">Upload a file from your device.</p>
</div>
