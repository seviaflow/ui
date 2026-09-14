@php
    $modelPath = $modelPath ?? 'fieldValues';
    $fieldId = 'field-'.$field->name();
    $errorPath = $modelPath.'.'.$field->name();
    $hasError = $errors->has($errorPath);
    $isRequired = $field->isRequired($context);
    $isDisabled = $field->isDisabled($context);
    $isReadonly = $field->isReadonly($context);
    $fileHasValue = (bool) ($fileHasValues[$field->name()] ?? false);
    $fileDownloadUrl = $fileDownloadUrls[$field->name()] ?? null;
    $fileAction = $fileActions[$field->name()] ?? 'retain';

    $controlClasses = [
        'sf-input',
        'sf-input-error' => $hasError,
        'sf-control-readonly' => $isReadonly,
    ];

    $selectClasses = [
        'sf-select',
        'sf-input-error' => $hasError,
        'sf-control-readonly' => $isReadonly,
    ];

    $textareaClasses = [
        'sf-textarea',
        'sf-input-error' => $hasError,
        'sf-control-readonly' => $isReadonly,
    ];

    $inputType = match (true) {
        $field instanceof \SeviaFlow\Core\Resources\Fields\EmailField => 'email',
        $field instanceof \SeviaFlow\Core\Resources\Fields\UrlField => 'url',
        $field instanceof \SeviaFlow\Core\Resources\Fields\PhoneField => 'tel',
        $field instanceof \SeviaFlow\Core\Resources\Fields\NumberField => 'number',
        $field instanceof \SeviaFlow\Core\Resources\Fields\DateField => 'date',
        $field instanceof \SeviaFlow\Core\Resources\Fields\DateTimeField => 'datetime-local',
        $field instanceof \SeviaFlow\Core\Resources\Fields\TimeField => 'time',
        $field instanceof \SeviaFlow\Core\Resources\Fields\PasswordField => 'password',
        default => 'text',
    };
@endphp

<div class="sf-field">
    @unless($field instanceof \SeviaFlow\Core\Resources\Fields\ToggleField)
        <label for="{{ $fieldId }}" class="sf-label">
            {{ $field->getLabel() }}
            @if($isRequired)
                <span class="text-red-600" aria-hidden="true">*</span>
            @endif
        </label>
    @endunless

    @if($field instanceof \SeviaFlow\Core\Resources\Fields\TextareaField)
        <textarea
            id="{{ $fieldId }}"
            rows="{{ $field->getRows() }}"
            wire:model="{{ $modelPath }}.{{ $field->name() }}"
            @disabled($isDisabled)
            @readonly($isReadonly)
            placeholder="{{ $field->getPlaceholder() }}"
            @class($textareaClasses)
        ></textarea>
    @elseif($field instanceof \SeviaFlow\Core\Resources\Fields\ToggleField)
        <label class="sf-checkbox-label" for="{{ $fieldId }}">
            <input
                id="{{ $fieldId }}"
                type="checkbox"
                wire:model="{{ $modelPath }}.{{ $field->name() }}"
                @disabled($isDisabled)
                @readonly($isReadonly)
                class="sf-checkbox"
            >
            <span class="sf-label !mb-0">
                {{ $field->getLabel() }}
                @if($isRequired)
                    <span class="text-red-600" aria-hidden="true">*</span>
                @endif
            </span>
        </label>
    @elseif($field instanceof \SeviaFlow\Core\Resources\Fields\BelongsToField)
        @if($field->getSearchableOptionColumns() !== [])
            <input
                id="field-search-{{ $field->name() }}"
                type="search"
                wire:model.live.debounce.300ms="belongsToSearch.{{ $field->name() }}"
                placeholder="Search options"
                class="sf-input mb-2"
            >
        @endif
        <select
            id="{{ $fieldId }}"
            wire:model="{{ $modelPath }}.{{ $field->name() }}"
            @disabled($isDisabled)
            @readonly($isReadonly)
            @class($selectClasses)
        >
            <option value="">{{ ($belongsToPlaceholder[$field->name()] ?? null) ?: $field->getPlaceholder() ?: 'Select an option' }}</option>
            @foreach(($belongsToOptions[$field->name()] ?? []) as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        @if($modelPath === 'fieldValues' && ($inlineCreateAllowed[$field->name()] ?? false))
            <button
                type="button"
                wire:click="beginInlineCreate('{{ $field->name() }}')"
                class="sf-button sf-button-secondary mt-2 !px-3 !py-1.5"
            >
                {{ $field->getInlineCreateLabel() ?? ('Create '.$field->getLabel()) }}
            </button>
        @endif
    @elseif($field instanceof \SeviaFlow\Core\Resources\Fields\SelectField)
        <select
            id="{{ $fieldId }}"
            wire:model="{{ $modelPath }}.{{ $field->name() }}"
            @disabled($isDisabled)
            @readonly($isReadonly)
            @class($selectClasses)
        >
            <option value="">{{ $field->getPlaceholder() ?: 'Select an option' }}</option>
            @foreach($field->getOptions() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    @elseif($field instanceof \SeviaFlow\Core\Resources\Fields\MultiSelectField)
        <select
            id="{{ $fieldId }}"
            wire:model="{{ $modelPath }}.{{ $field->name() }}"
            multiple
            @disabled($isDisabled)
            @readonly($isReadonly)
            @class($selectClasses)
        >
            @foreach($field->getOptions() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    @elseif($field instanceof \SeviaFlow\Core\Resources\Fields\FileField)
        <div class="sf-file-drop">
            <input
                id="{{ $fieldId }}"
                type="file"
                wire:model="{{ $modelPath }}.{{ $field->name() }}"
                @disabled($isDisabled)
                @if($field->getAcceptedFileTypes() !== []) accept="{{ implode(',', $field->getAcceptedFileTypes()) }}" @endif
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
    @elseif($field instanceof \SeviaFlow\Core\Resources\Fields\MoneyField)
        <div class="relative">
            <span class="sf-input-prefix">
                {{ $field->getCurrency() }}
            </span>
            <input
                id="{{ $fieldId }}"
                type="number"
                step="0.01"
                wire:model="{{ $modelPath }}.{{ $field->name() }}"
                @disabled($isDisabled)
                @readonly($isReadonly)
                placeholder="{{ $field->getPlaceholder() }}"
                @if($field->getMin() !== null) min="{{ $field->getMin() }}" @endif
                @if($field->getMax() !== null) max="{{ $field->getMax() }}" @endif
                @class(array_merge($controlClasses, ['pl-14']))
            >
        </div>
    @else
        <input
            id="{{ $fieldId }}"
            type="{{ $inputType }}"
            wire:model="{{ $modelPath }}.{{ $field->name() }}"
            @disabled($isDisabled)
            @readonly($isReadonly)
            placeholder="{{ $field->getPlaceholder() }}"
            @if($field instanceof \SeviaFlow\Core\Resources\Fields\NumberField && $field->getMin() !== null) min="{{ $field->getMin() }}" @endif
            @if($field instanceof \SeviaFlow\Core\Resources\Fields\NumberField && $field->getMax() !== null) max="{{ $field->getMax() }}" @endif
            @class($controlClasses)
        >
    @endif

    @if($field->getHelp())
        <p class="sf-help">{{ $field->getHelp() }}</p>
    @endif

    @error($errorPath)
        <p class="sf-error">{{ $message }}</p>
    @enderror
</div>
