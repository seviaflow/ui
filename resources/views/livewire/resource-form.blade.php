<div class="grid gap-4">
    @if($statusMessage)
        <div class="sf-alert sf-alert-success">
            {{ $statusMessage }}
        </div>
    @endif
    @if($errorMessage)
        <div class="sf-alert sf-alert-danger">
            {{ $errorMessage }}
        </div>
    @endif
    @if($errors->any())
        <div class="sf-alert sf-alert-danger">
            Please correct the highlighted fields.
        </div>
    @endif

    <form wire:submit="save" enctype="multipart/form-data" class="sf-form">
        <section class="sf-card">
            <div class="sf-card-header">
                <div class="sf-form-header">
                    <div>
                        <p class="sf-card-kicker">{{ $isEditMode ? 'Edit record' : 'Create record' }}</p>
                        <h2 class="sf-card-title">{{ $isEditMode ? 'Record fields' : 'New record details' }}</h2>
                    </div>
                    <p class="sf-card-description">Required fields are marked with an asterisk.</p>
                </div>
            </div>
            <div class="sf-card-body">
                @if(count($sections) > 0)
                    <div class="grid gap-4">
                        @foreach($sections as $section)
                            <section class="sf-panel">
                                <div class="mb-4">
                                    <h2 class="sf-section-title">{{ $section->label() }}</h2>
                                    @if($section->getDescription())
                                        <p class="sf-section-description">{{ $section->getDescription() }}</p>
                                    @endif
                                </div>

                                <div class="sf-form-grid">
                                    @foreach($section->getFields() as $field)
                                        @include('seviaflow-ui::livewire.partials.form-field', ['field' => $field, 'context' => $context, 'belongsToOptions' => $belongsToOptions, 'inlineCreateAllowed' => $inlineCreateAllowed, 'belongsToPlaceholder' => $belongsToPlaceholder])
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                @else
                    <div class="sf-form-grid">
                        @foreach($fields as $field)
                            @include('seviaflow-ui::livewire.partials.form-field', ['field' => $field, 'context' => $context, 'belongsToOptions' => $belongsToOptions, 'inlineCreateAllowed' => $inlineCreateAllowed, 'belongsToPlaceholder' => $belongsToPlaceholder])
                        @endforeach
                    </div>
                @endif

                <div class="sf-form-actions">
                    <div class="sf-resource-actions">
                        <button type="submit" class="sf-button sf-button-primary">
                            {{ $isEditMode ? 'Save changes' : 'Create' }}
                        </button>

                        <a href="{{ $cancelUrl }}" class="sf-button sf-button-secondary">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </form>

    @if(($inlineCreateState['field'] ?? null) && ($inlineCreateState['visible'] ?? false))
        <section class="sf-card">
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
                            'modelPath' => 'inlineCreateValues',
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
