@php
    $controlClasses = ['sf-input', 'sf-input-error' => $hasError, 'sf-control-readonly' => $isReadonly];
    $fileHasValue = (bool) ($fileHasValues[$field->name()] ?? false);
    $fileDownloadUrl = $fileDownloadUrls[$field->name()] ?? null;
    $fileAction = $fileActions[$field->name()] ?? 'retain';
    $fileMetadata = $fileMetadata[$field->name()] ?? null;
    $filePending = (bool) ($filePending[$field->name()] ?? false);
    $removeAction = $modelPath === 'relationModalValues' ? 'removeRelationModalFile' : 'removeFile';
    $retainAction = $modelPath === 'relationModalValues' ? 'retainRelationModalFile' : 'retainFile';
@endphp
<div class="sf-file-drop">
    @if($fileHasValue && $fileMetadata)
        <div class="sf-file-current" data-sf-file-current>
            @if($fileMetadata['image'] && $fileDownloadUrl)
                <img src="{{ $fileDownloadUrl }}" alt="{{ $fileMetadata['name'] }}" class="sf-file-preview">
            @endif
            <div class="sf-file-copy">
                <strong>{{ $fileMetadata['name'] }}</strong>
                <span class="sf-item-meta">
                    {{ $fileMetadata['size'] !== null ? number_format($fileMetadata['size'] / 1024, 1).' KB' : __('seviaflow-ui::panel.size_unavailable') }}
                    @if($fileMetadata['mime']) · {{ $fileMetadata['mime'] }} @endif
                </span>
            </div>
            @if($fileDownloadUrl)
                <a href="{{ $fileDownloadUrl }}" class="sf-link">{{ __('seviaflow-ui::panel.open') }}</a>
            @endif
        </div>
    @endif
    <input
        id="{{ $fieldId }}"
        type="file"
        wire:model="{{ $fieldPath }}"
        @disabled($isDisabled)
        @if($field->getAcceptedFileTypes() !== []) accept="{{ implode(',', $field->getAcceptedFileTypes()) }}" @endif
        @if($isRequired) aria-required="true" @endif
        @if($hasError) aria-invalid="true" @endif
        @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @class($controlClasses)
    >
    <div class="sf-upload-status" wire:loading wire:target="{{ $fieldPath }}" role="status" aria-live="polite">
        {{ __('seviaflow-ui::panel.uploading') }}
        <progress max="100" value="0" data-sf-upload-progress aria-label="{{ __('seviaflow-ui::panel.upload_progress') }}"></progress>
    </div>
    @if($filePending)
        <p class="sf-help" data-sf-file-pending>{{ __('seviaflow-ui::panel.replacement_selected_save_to_apply_it') }}</p>
    @endif
    @if(in_array($modelPath, ['fieldValues', 'relationModalValues'], true) && $fileHasValue)
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
            @if($fileAction === 'remove')
                <span class="sf-item-meta">{{ __('seviaflow-ui::panel.removal_will_be_applied_on_save') }}</span>
                @if(! $isDisabled && ! $isReadonly)
                    <button type="button" wire:click="{{ $retainAction }}('{{ $field->name() }}')" class="sf-button sf-button-secondary !px-3 !py-1.5">{{ __('seviaflow-ui::panel.keep_current_file') }}</button>
                @endif
            @elseif(! $isDisabled && ! $isReadonly)
                <button type="button" wire:click="{{ $removeAction }}('{{ $field->name() }}')" class="sf-button sf-button-secondary !px-3 !py-1.5">{{ __('seviaflow-ui::panel.remove_current_file') }}</button>
                <span class="sf-item-meta">{{ __('seviaflow-ui::panel.choose_another_file_above_to_replace_it') }}</span>
            @endif
        </div>
    @endif
    <p class="sf-help mt-2">{{ __('seviaflow-ui::panel.upload_a_file_from_your_device') }}</p>
</div>
