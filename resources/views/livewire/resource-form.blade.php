@php
    $validationMessages = $errors->getMessages();
@endphp

<div
    class="grid gap-4"
    data-sf-resource-form
    data-sf-form-dirty="{{ $isDirty ? 'true' : 'false' }}"
    data-sf-form-submitting="false"
>
    <div class="sf-livewire-loading" wire:loading role="status" aria-live="polite">
        {{ __('seviaflow-ui::ui.states.loading') }}
    </div>
    @if($statusMessage)
        <div class="sf-alert sf-alert-success" role="status" aria-live="polite">
            {{ $statusMessage }}
        </div>
    @endif
    @if($errorMessage)
        <div class="sf-alert sf-alert-danger" role="alert">
            {{ $errorMessage }}
            @if($hasConflict)
                <button type="button" wire:click="reloadRecord" class="sf-button sf-button-secondary mt-3">
                    Refresh record and discard local changes
                </button>
            @endif
        </div>
    @endif
    @if($errors->any())
        <div class="sf-alert sf-alert-danger" data-sf-validation-summary role="alert" tabindex="-1">
            <p>Please correct the highlighted fields.</p>
            <p>{{ $errors->count() }} validation {{ $errors->count() === 1 ? 'error' : 'errors' }}.</p>
            <ul>
                @foreach($validationMessages as $errorPath => $messages)
                    @if(str_starts_with($errorPath, 'fieldValues.'))
                        @php
                            $errorField = substr($errorPath, strlen('fieldValues.'));
                            $errorParts = explode('.', $errorField);
                            $errorTarget = $errorParts[0] ?? $errorField;
                            $errorId = 'field-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $errorTarget);
                            $errorLabel = count($errorParts) > 1
                                ? str_replace('_', ' ', $errorTarget).' row '.((int) ($errorParts[1] ?? 0) + 1)
                                : str_replace('_', ' ', $errorField);
                        @endphp
                        <li><a href="#{{ $errorId }}" data-sf-validation-target="{{ $errorId }}">{{ $errorLabel }}</a></li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="saveAndView" enctype="multipart/form-data" class="sf-form">
        <section class="sf-card">
            <div class="sf-card-header">
                <div class="sf-form-header">
                    <div>
                        <p class="sf-card-kicker">{{ $isEditMode ? 'Edit record' : 'Create record' }}</p>
                        <h2 class="sf-card-title">{{ $isEditMode ? 'Record fields' : 'New record details' }}</h2>
                    </div>
                    <p class="sf-card-description">Required fields are marked with an asterisk.</p>
                </div>
                @if($isDirty)
                    <p class="sf-form-state sf-form-state-dirty" data-sf-form-state>Unsaved changes</p>
                @endif
            </div>
            <div class="sf-card-body">
                @if(count($tabs) > 0)
                    <div class="sf-form-tabs" data-sf-form-tabs>
                        <div class="sf-tab-list" role="tablist" aria-label="Form sections">
                            @foreach($tabs as $tab)
                                <button
                                    type="button"
                                    class="sf-tab-button"
                                    role="tab"
                                    wire:click="setActiveTab('{{ addslashes($tab->getKey()) }}')"
                                    aria-selected="{{ $activeTab === $tab->getKey() ? 'true' : 'false' }}"
                                    data-sf-tab-trigger="{{ $tab->getKey() }}"
                                >
                                    {{ $tab->label() }}
                                </button>
                            @endforeach
                        </div>

                        @foreach($tabs as $tab)
                            <div
                                class="sf-tab-panel"
                                role="tabpanel"
                                data-sf-tab-panel="{{ $tab->getKey() }}"
                                @if($activeTab !== $tab->getKey()) hidden @endif
                            >
                                @if($tab->getDescription())
                                    <p class="sf-section-description mb-4">{{ $tab->getDescription() }}</p>
                                @endif
                                @if(count($tab->getSections()) > 0)
                                    <div class="grid gap-4" @if($formColumns !== null) style="--sf-form-columns: repeat({{ $formColumns }}, minmax(0, 1fr));" @endif>
                                        @foreach($tab->getSections() as $section)
                                            @include('seviaflow-ui::livewire.partials.form-section', ['section' => $section, 'context' => $context, 'belongsToOptions' => $belongsToOptions, 'inlineCreateAllowed' => $inlineCreateAllowed, 'belongsToPlaceholder' => $belongsToPlaceholder, 'fileDownloadUrls' => $fileDownloadUrls, 'fileHasValues' => $fileHasValues, 'fileMetadata' => $fileMetadata, 'filePending' => $filePending, 'fileActions' => $fileActions])
                                        @endforeach
                                    </div>
                                @endif
                                @if(count($tab->getFields()) > 0)
                                    <div class="sf-form-grid" @if($formColumns !== null) style="--sf-form-columns: repeat({{ $formColumns }}, minmax(0, 1fr));" @endif>
                                        @foreach($tab->getFields() as $field)
                                            @include('seviaflow-ui::livewire.partials.form-field', ['field' => $field, 'context' => $context, 'belongsToOptions' => $belongsToOptions, 'inlineCreateAllowed' => $inlineCreateAllowed, 'belongsToPlaceholder' => $belongsToPlaceholder, 'fileDownloadUrls' => $fileDownloadUrls, 'fileHasValues' => $fileHasValues, 'fileMetadata' => $fileMetadata, 'filePending' => $filePending, 'fileActions' => $fileActions])
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @elseif(count($sections) > 0)
                    <div class="grid gap-4" @if($formColumns !== null) style="--sf-form-columns: repeat({{ $formColumns }}, minmax(0, 1fr));" @endif>
                        @foreach($sections as $section)
                            @include('seviaflow-ui::livewire.partials.form-section', ['section' => $section, 'context' => $context, 'belongsToOptions' => $belongsToOptions, 'inlineCreateAllowed' => $inlineCreateAllowed, 'belongsToPlaceholder' => $belongsToPlaceholder, 'fileDownloadUrls' => $fileDownloadUrls, 'fileHasValues' => $fileHasValues, 'fileMetadata' => $fileMetadata, 'filePending' => $filePending, 'fileActions' => $fileActions])
                        @endforeach
                    </div>
                @else
                    <div class="sf-form-grid" @if($formColumns !== null) style="--sf-form-columns: repeat({{ $formColumns }}, minmax(0, 1fr));" @endif>
                        @foreach($fields as $field)
                            @include('seviaflow-ui::livewire.partials.form-field', ['field' => $field, 'context' => $context, 'belongsToOptions' => $belongsToOptions, 'inlineCreateAllowed' => $inlineCreateAllowed, 'belongsToPlaceholder' => $belongsToPlaceholder, 'fileDownloadUrls' => $fileDownloadUrls, 'fileHasValues' => $fileHasValues, 'fileMetadata' => $fileMetadata, 'filePending' => $filePending, 'fileActions' => $fileActions])
                        @endforeach
                    </div>
                @endif

                <div class="sf-form-actions">
                    <div class="sf-resource-actions">
                        <button type="submit" class="sf-button sf-button-primary" wire:loading.attr="disabled">
                            {{ $isEditMode ? 'Save' : 'Create' }}
                        </button>
                        <button type="button" wire:click="saveAndReturn" class="sf-button sf-button-secondary" wire:loading.attr="disabled">
                            Save and return
                        </button>
                        <button type="button" wire:click="saveAndCreateAnother" class="sf-button sf-button-secondary" wire:loading.attr="disabled">
                            Save and add another
                        </button>
                        <a href="{{ $cancelUrl }}" class="sf-button sf-button-secondary" data-sf-form-exit>
                            Cancel
                        </a>
                    </div>
                    @if($isDirty)
                        <span class="sf-form-state" data-sf-form-state>Unsaved changes</span>
                    @elseif($statusMessage)
                        <span class="sf-form-state sf-form-state-success" data-sf-form-state>Saved</span>
                    @endif
                </div>
            </div>
        </section>
    </form>

    @if(($inlineCreateState['field'] ?? null) && ($inlineCreateState['visible'] ?? false))
        <section class="sf-card" data-sf-inline-create>
            <div class="sf-card-header">
                <h2 class="sf-card-title">{{ $inlineCreateState['title'] }}</h2>
            </div>

            <div class="sf-card-body">
                <div class="sf-form-grid">
                    @foreach($inlineCreateState['fields'] as $field)
                                @include('seviaflow-ui::livewire.partials.form-field', [
                            'field' => $field,
                            'context' => \SeviaFlow\Core\Resources\ResourceContext::Create,
                            'belongsToOptions' => $inlineCreateState['belongsToOptions'],
                            'belongsToPlaceholder' => [],
                                    'fileDownloadUrls' => [],
                                    'fileHasValues' => [],
                                    'fileMetadata' => [],
                                    'filePending' => [],
                                    'fileActions' => [],
                            'modelPath' => 'inlineCreateValues',
                            'formState' => new \SeviaFlow\Core\Forms\FormState($inlineCreateValues, \SeviaFlow\Core\Resources\ResourceContext::Create),
                        ])
                    @endforeach
                </div>

                <div class="sf-resource-actions mt-4">
                    <button type="button" wire:click="saveInlineCreate" class="sf-button sf-button-primary">
                        Save
                    </button>
                    <button type="button" wire:click="cancelInlineCreate" class="sf-button sf-button-secondary">
                        Cancel
                    </button>
                </div>
            </div>
        </section>
    @endif
</div>
