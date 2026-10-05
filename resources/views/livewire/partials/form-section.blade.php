@php
    $sectionId = 'form-section-'.
        preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($section->label()));
    $sectionHasError = collect($section->getFields())
        ->contains(function ($field) use ($errors): bool {
            $prefix = 'fieldValues.'.$field->name();

            return collect($errors->keys())
                ->contains(fn (string $key): bool => $key === $prefix || str_starts_with($key, $prefix.'.'));
        });
    $sectionClass = ['sf-panel', 'sf-form-span-'.$section->getColumnSpan() => $section->getColumnSpan() !== null];
@endphp

@if($section->isCollapsible())
    <details id="{{ $sectionId }}" @class($sectionClass) @if(! $section->isCollapsed() || $sectionHasError) open @endif>
        <summary class="sf-section-summary">
            <span>
                <span class="sf-section-title">{{ $section->label() }}</span>
                @if($section->getDescription())
                    <span class="sf-section-description">{{ $section->getDescription() }}</span>
                @endif
            </span>
        </summary>
        <div class="sf-form-grid" @if(($formColumns ?? null) !== null) style="--sf-form-columns: repeat({{ $formColumns }}, minmax(0, 1fr));" @endif>
            @foreach($section->getFields() as $field)
                @include('seviaflow-ui::livewire.partials.form-field', ['field' => $field, 'context' => $context, 'belongsToOptions' => $belongsToOptions, 'inlineCreateAllowed' => $inlineCreateAllowed, 'belongsToPlaceholder' => $belongsToPlaceholder, 'fileDownloadUrls' => $fileDownloadUrls, 'fileHasValues' => $fileHasValues, 'fileMetadata' => $fileMetadata ?? [], 'filePending' => $filePending ?? [], 'fileActions' => $fileActions])
            @endforeach
        </div>
    </details>
@else
    <section id="{{ $sectionId }}" @class($sectionClass)>
        <div class="mb-4">
            <h2 class="sf-section-title">{{ $section->label() }}</h2>
            @if($section->getDescription())
                <p class="sf-section-description">{{ $section->getDescription() }}</p>
            @endif
        </div>

        <div class="sf-form-grid" @if(($formColumns ?? null) !== null) style="--sf-form-columns: repeat({{ $formColumns }}, minmax(0, 1fr));" @endif>
            @foreach($section->getFields() as $field)
                @include('seviaflow-ui::livewire.partials.form-field', ['field' => $field, 'context' => $context, 'belongsToOptions' => $belongsToOptions, 'inlineCreateAllowed' => $inlineCreateAllowed, 'belongsToPlaceholder' => $belongsToPlaceholder, 'fileDownloadUrls' => $fileDownloadUrls, 'fileHasValues' => $fileHasValues, 'fileMetadata' => $fileMetadata ?? [], 'filePending' => $filePending ?? [], 'fileActions' => $fileActions])
            @endforeach
        </div>
    </section>
@endif
