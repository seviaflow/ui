<div class="space-y-4">
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

    <form wire:submit="save" enctype="multipart/form-data" class="space-y-4">
        <div class="sf-card">
            <div class="sf-card-body space-y-4">
                @if(count($sections) > 0)
                    <div class="space-y-4">
                        @foreach($sections as $section)
                            <section class="sf-card">
                                <div class="sf-card-header">
                                    <h2 class="text-sm font-semibold text-slate-900">{{ $section->label() }}</h2>
                                    @if($section->getDescription())
                                        <p class="mt-1 text-sm text-slate-500">{{ $section->getDescription() }}</p>
                                    @endif
                                </div>

                                <div class="sf-card-body space-y-4">
                                    @foreach($section->getFields() as $field)
                                        @include('seviaflow-ui::livewire.partials.form-field', ['field' => $field, 'context' => $context, 'belongsToOptions' => $belongsToOptions, 'inlineCreateAllowed' => $inlineCreateAllowed, 'belongsToPlaceholder' => $belongsToPlaceholder])
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($fields as $field)
                            @include('seviaflow-ui::livewire.partials.form-field', ['field' => $field, 'context' => $context, 'belongsToOptions' => $belongsToOptions, 'inlineCreateAllowed' => $inlineCreateAllowed, 'belongsToPlaceholder' => $belongsToPlaceholder])
                        @endforeach
                    </div>
                @endif

                <div class="sticky bottom-0 -mx-4 -mb-4 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:-mb-6 sm:px-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="submit" class="sf-button sf-button-primary">
                            {{ $isEditMode ? 'Save changes' : 'Create' }}
                        </button>

                        <a href="{{ $cancelUrl }}" class="sf-button sf-button-secondary">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if(($inlineCreateState['field'] ?? null) && ($inlineCreateState['visible'] ?? false))
        <div class="sf-card">
            <div class="sf-card-header">
                <h2 class="text-sm font-semibold text-slate-900">{{ $inlineCreateState['title'] }}</h2>
            </div>

            <div class="sf-card-body space-y-4">
                @foreach($inlineCreateState['fields'] as $field)
                    @include('seviaflow-ui::livewire.partials.form-field', [
                        'field' => $field,
                        'context' => \SeviaFlow\Core\Resources\ResourceContext::Create,
                        'belongsToOptions' => $inlineCreateState['belongsToOptions'],
                        'belongsToPlaceholder' => [],
                        'modelPath' => 'inlineCreateValues',
                    ])
                @endforeach

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="saveInlineCreate" class="sf-button sf-button-primary">
                        Save
                    </button>
                    <button type="button" wire:click="cancelInlineCreate" class="sf-button sf-button-secondary">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
