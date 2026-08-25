<div class="space-y-4">
    @if($statusMessage)
        @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'success', 'message' => $statusMessage])
    @endif
    @if($errorMessage)
        @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'danger', 'message' => $errorMessage])
    @endif

    <div class="sf-card">
        <div class="sf-card-body space-y-4">
            <div class="flex flex-wrap items-center gap-2 text-sm text-slate-700">
                <span>{{ $selectedCount }} {{ $selectedCount === 1 ? 'record' : 'records' }} selected</span>
                @if($selectedCount > 0)
                    <button
                        type="button"
                        wire:click="clearSelection"
                        class="sf-button sf-button-secondary !px-3 !py-1.5"
                    >
                        Clear selection
                    </button>
                @endif
                @if(count($bulkActions) > 0)
                    @foreach($bulkActions as $action)
                        @if($pendingBulkActionName === $action->name())
                            <div class="w-full">
                                @component('seviaflow-ui::livewire.partials.action-confirmation', [
                                    'variant' => $action->isDanger() ? 'danger' : 'safe',
                                    'title' => $action->isDanger() ? 'Confirm dangerous bulk action' : 'Confirm bulk action',
                                    'message' => 'You are about to run this action on '.$selectedCount.' '.($selectedCount === 1 ? 'record' : 'records').'.',
                                    'consequences' => $action->isDanger()
                                        ? ['Selected records may be changed immediately.', 'This action may be hard to undo.']
                                        : [],
                                ])
                                    <button
                                        type="button"
                                        wire:click="executeBulkAction('{{ $action->name() }}', true)"
                                        class="sf-button {{ $action->isDanger() ? 'sf-button-danger' : 'sf-button-primary' }} !px-3 !py-1.5"
                                    >
                                        {{ $action->getConfirmButton() ?? 'Confirm' }}
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="$set('pendingBulkActionName', null)"
                                        class="sf-button sf-button-secondary !px-3 !py-1.5"
                                    >
                                        {{ $action->getCancelButton() ?? 'Cancel' }}
                                    </button>
                                @endcomponent
                            </div>
                        @else
                            <button
                                type="button"
                                wire:click="executeBulkAction('{{ $action->name() }}')"
                                @class([
                                    'sf-button !px-3 !py-1.5',
                                    'sf-button-danger' => $action->isDanger(),
                                    'sf-button-secondary' => ! $action->isDanger(),
                                ])
                            >
                                {{ $action->getLabel() }}
                            </button>
                        @endif
                    @endforeach
                @endif
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="w-full sm:max-w-sm">
                    <label for="resource-search" class="sr-only">Search records</label>
                    <input
                        id="resource-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search records"
                        class="sf-input"
                    >
                </div>
                <div class="w-full sm:w-auto">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        @if($hasForm && $canCreate)
                            <a href="{{ $createUrl }}" class="sf-button sf-button-primary">
                                Create
                            </a>
                        @endif
                        @if($canExport)
                            <button type="button" wire:click="openExportModal" class="sf-button sf-button-secondary">
                                Export
                            </button>
                        @endif
                        @if($canImport)
                            <button type="button" wire:click="openImportModal" class="sf-button sf-button-secondary">
                                Import
                            </button>
                        @endif

                        <label for="resource-per-page" class="sr-only">Records per page</label>
                        <select
                            id="resource-per-page"
                            wire:model.live="perPage"
                            class="sf-select sm:w-36"
                        >
                            @foreach($perPageOptions as $option)
                                <option value="{{ $option }}">{{ $option }} per page</option>
                            @endforeach
                        </select>

                        @if($supportsSoftDeletes)
                            <label for="resource-trashed-scope" class="sr-only">Trash scope</label>
                            <select
                                id="resource-trashed-scope"
                                wire:model.live="trashedScope"
                                class="sf-select sm:w-44"
                            >
                                <option value="active">Active records</option>
                                <option value="trashed">Trashed records</option>
                                <option value="with_trashed">All records</option>
                            </select>
                        @endif
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="w-full sm:max-w-md">
                        <label for="saved-view-select" class="sf-label">Saved views</label>
                        <select id="saved-view-select" wire:model.live="selectedSavedViewId" class="sf-select">
                            <option value="">Select saved view</option>
                            @foreach($savedViews as $savedView)
                                <option value="{{ $savedView->id }}">{{ $savedView->name }}</option>
                            @endforeach
                        </select>
                        @if($savedViews->isEmpty())
                            <p class="mt-1 text-xs text-slate-500">No saved views yet.</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="openSaveViewForm" class="sf-button sf-button-secondary !px-3 !py-1.5">
                            Save current view
                        </button>
                        <button
                            type="button"
                            wire:click="applySavedView(selectedSavedViewId)"
                            class="sf-button sf-button-secondary !px-3 !py-1.5"
                            @disabled($selectedSavedViewId === null || $selectedSavedViewId === '')
                        >
                            Apply
                        </button>
                        <button
                            type="button"
                            wire:click="deleteSavedView(selectedSavedViewId)"
                            wire:confirm="Delete this saved view?"
                            class="sf-button sf-button-danger !px-3 !py-1.5"
                            @disabled($selectedSavedViewId === null || $selectedSavedViewId === '')
                        >
                            Delete
                        </button>
                    </div>
                </div>

                @if($showSaveViewForm)
                    <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                        <div>
                            <label for="saved-view-name" class="sf-label">View name</label>
                            <input
                                id="saved-view-name"
                                type="text"
                                wire:model.live="savedViewName"
                                placeholder="e.g. Published this month"
                                class="sf-input"
                            >
                        </div>
                        <button type="button" wire:click="saveCurrentView" class="sf-button sf-button-primary !px-3 !py-1.5">
                            Save view
                        </button>
                        <button type="button" wire:click="cancelSaveView" class="sf-button sf-button-secondary !px-3 !py-1.5">
                            Cancel
                        </button>
                    </div>
                @endif
            </div>

            @if($showExportModal && $canExport)
                <div class="rounded-lg border border-slate-300 bg-white p-4 shadow-sm">
                    <div class="space-y-3">
                        <h3 class="text-sm font-semibold text-slate-900">Export records</h3>

                        <div>
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <label class="sf-label mb-0">Fields</label>
                                <button type="button" wire:click="selectAllExportFields" class="sf-button sf-button-secondary !px-3 !py-1.5">
                                    Select all
                                </button>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach($exportFields as $field)
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            wire:model.live="selectedExportFields"
                                            value="{{ $field->name() }}"
                                            class="sf-checkbox"
                                        >
                                        <span>{{ $field->getLabel() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="export-format" class="sf-label">Format</label>
                                <select id="export-format" wire:model.live="selectedExportFormat" class="sf-select">
                                    @foreach($exportFormats as $format)
                                        <option value="{{ $format }}">{{ strtoupper($format) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="export-scope" class="sf-label">Scope</label>
                                <select id="export-scope" wire:model.live="exportScope" class="sf-select">
                                    <option value="current_view">Current view</option>
                                    <option value="selected_rows">Selected rows</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="exportRecords" class="sf-button sf-button-primary !px-3 !py-1.5">
                                Export
                            </button>
                            <button type="button" wire:click="closeExportModal" class="sf-button sf-button-secondary !px-3 !py-1.5">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            @if($showImportModal && $canImport)
                <div class="rounded-lg border border-slate-300 bg-white p-4 shadow-sm space-y-3">
                    <h3 class="text-sm font-semibold text-slate-900">Import CSV preview</h3>

                    <div>
                        <label for="import-file" class="sf-label">CSV file</label>
                        <input id="import-file" type="file" wire:model="importFile" accept=".csv,text/csv" class="sf-input">
                        <p class="mt-1 text-xs text-slate-500">Headers must match allowed fields exactly.</p>
                        @error('importFile')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="text-xs text-slate-600">
                        Allowed fields: {{ implode(', ', $importAllowedFields) }}
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="previewImport" class="sf-button sf-button-primary !px-3 !py-1.5">
                            Preview
                        </button>
                        <button type="button" wire:click="executeImport" class="sf-button sf-button-primary !px-3 !py-1.5">
                            Import now
                        </button>
                        <button type="button" wire:click="closeImportModal" class="sf-button sf-button-secondary !px-3 !py-1.5">
                            Cancel
                        </button>
                    </div>

                    @if(count($importPreviewErrors) > 0)
                        <div class="rounded border border-amber-200 bg-amber-50 p-3">
                            <p class="text-xs font-semibold text-amber-900">Preview errors</p>
                            <ul class="mt-1 list-disc space-y-1 pl-5 text-xs text-amber-800">
                                @foreach($importPreviewErrors as $previewError)
                                    <li>{{ $previewError }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(count($importPreviewHeaders) > 0)
                        <div>
                            <p class="text-xs font-semibold text-slate-700">Detected headers</p>
                            <p class="mt-1 text-xs text-slate-600">{{ implode(', ', $importPreviewHeaders) }}</p>
                        </div>
                    @endif

                    @if(count($importPreviewRows) > 0)
                        <div class="overflow-x-auto rounded border border-slate-200">
                            <table class="sf-table">
                                <thead>
                                    <tr>
                                        <th class="sf-table-header">Row</th>
                                        @foreach($importPreviewHeaders as $header)
                                            <th class="sf-table-header">{{ $header }}</th>
                                        @endforeach
                                        <th class="sf-table-header">Errors</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($importPreviewRows as $previewRow)
                                        <tr>
                                            <td class="sf-table-cell">{{ $previewRow['index'] }}</td>
                                            @foreach($importPreviewHeaders as $header)
                                                <td class="sf-table-cell">{{ $previewRow['values'][$header] ?? '' }}</td>
                                            @endforeach
                                            <td class="sf-table-cell">
                                                @if(count($previewRow['errors']) > 0)
                                                    {{ implode('; ', $previewRow['errors']) }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if(count($filters) > 0)
        <div class="sf-card">
            <div class="sf-card-body space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-medium text-slate-700">
                        {{ $activeFiltersCount }} {{ $activeFiltersCount === 1 ? 'filter active' : 'filters active' }}
                    </p>
                    <button
                        type="button"
                        wire:click="resetFilters"
                        class="sf-button sf-button-secondary !px-3 !py-1.5"
                    >
                        Reset filters
                    </button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($filters as $filter)
                    <div>
                        <label for="filter-{{ $filter->name() }}" class="sf-label">{{ $filter->getLabel() }}</label>
                        @if($filter instanceof \SeviaFlow\Core\Resources\Filters\BooleanFilter)
                            <select
                                id="filter-{{ $filter->name() }}"
                                wire:model.live="filterValues.{{ $filter->name() }}"
                                class="sf-select"
                            >
                                <option value="">{{ $filter->getPlaceholder() ?? 'Any' }}</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        @elseif($filter instanceof \SeviaFlow\Core\Resources\Filters\BelongsToFilter)
                            <select
                                id="filter-{{ $filter->name() }}"
                                wire:model.live="filterValues.{{ $filter->name() }}"
                                class="sf-select"
                            >
                                <option value="">{{ $filter->getPlaceholder() ?? 'Any' }}</option>
                                @foreach($filter->getOptionsFor($resource::model()) as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @elseif($filter instanceof \SeviaFlow\Core\Resources\Filters\SelectFilter)
                            <select
                                id="filter-{{ $filter->name() }}"
                                wire:model.live="filterValues.{{ $filter->name() }}"
                                class="sf-select"
                            >
                                <option value="">{{ $filter->getPlaceholder() ?? 'Any' }}</option>
                                @foreach($filter->getOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @elseif($filter instanceof \SeviaFlow\Core\Resources\Filters\MultiSelectFilter)
                            <select
                                id="filter-{{ $filter->name() }}"
                                multiple
                                wire:model.live="filterValues.{{ $filter->name() }}"
                                class="sf-select"
                            >
                                @foreach($filter->getOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @elseif($filter instanceof \SeviaFlow\Core\Resources\Filters\TextFilter)
                            <input
                                id="filter-{{ $filter->name() }}"
                                type="text"
                                wire:model.live="filterValues.{{ $filter->name() }}"
                                placeholder="{{ $filter->getPlaceholder() ?? 'Type to filter' }}"
                                class="sf-input"
                            >
                        @elseif($filter instanceof \SeviaFlow\Core\Resources\Filters\DateFilter)
                            <input
                                id="filter-{{ $filter->name() }}"
                                type="date"
                                wire:model.live="filterValues.{{ $filter->name() }}"
                                class="sf-input"
                            >
                        @elseif($filter instanceof \SeviaFlow\Core\Resources\Filters\DateTimeRangeFilter)
                            <div class="grid grid-cols-2 gap-2">
                                <input
                                    id="filter-{{ $filter->name() }}-from"
                                    type="datetime-local"
                                    wire:model.live="filterValues.{{ $filter->name() }}.from"
                                    placeholder="From"
                                    class="sf-input"
                                >
                                <input
                                    id="filter-{{ $filter->name() }}-to"
                                    type="datetime-local"
                                    wire:model.live="filterValues.{{ $filter->name() }}.to"
                                    placeholder="To"
                                    class="sf-input"
                                >
                            </div>
                        @elseif($filter instanceof \SeviaFlow\Core\Resources\Filters\DateRangeFilter)
                            <div class="grid grid-cols-2 gap-2">
                                <input
                                    id="filter-{{ $filter->name() }}-from"
                                    type="date"
                                    wire:model.live="filterValues.{{ $filter->name() }}.from"
                                    placeholder="From"
                                    class="sf-input"
                                >
                                <input
                                    id="filter-{{ $filter->name() }}-to"
                                    type="date"
                                    wire:model.live="filterValues.{{ $filter->name() }}.to"
                                    placeholder="To"
                                    class="sf-input"
                                >
                            </div>
                        @elseif($filter instanceof \SeviaFlow\Core\Resources\Filters\TimeFilter)
                            <input
                                id="filter-{{ $filter->name() }}"
                                type="time"
                                wire:model.live="filterValues.{{ $filter->name() }}"
                                class="sf-input"
                            >
                        @elseif($filter instanceof \SeviaFlow\Core\Resources\Filters\NumberFilter && ! $filter instanceof \SeviaFlow\Core\Resources\Filters\NumberRangeFilter)
                            <input
                                id="filter-{{ $filter->name() }}"
                                type="number"
                                step="any"
                                wire:model.live="filterValues.{{ $filter->name() }}"
                                placeholder="{{ $filter->getPlaceholder() ?? 'Any value' }}"
                                class="sf-input"
                            >
                        @elseif($filter instanceof \SeviaFlow\Core\Resources\Filters\NumberRangeFilter)
                            <div class="grid grid-cols-2 gap-2">
                                <input
                                    id="filter-{{ $filter->name() }}-min"
                                    type="number"
                                    step="any"
                                    wire:model.live="filterValues.{{ $filter->name() }}.min"
                                    placeholder="Min"
                                    class="sf-input"
                                >
                                <input
                                    id="filter-{{ $filter->name() }}-max"
                                    type="number"
                                    step="any"
                                    wire:model.live="filterValues.{{ $filter->name() }}.max"
                                    placeholder="Max"
                                    class="sf-input"
                                >
                            </div>
                        @endif
                    </div>
                @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="sf-table-wrapper">
        <table class="sf-table">
            <thead>
                <tr>
                    <th scope="col" class="sf-table-header px-4">
                        <label for="select-all-visible" class="sr-only">Select all visible records</label>
                        <input
                            id="select-all-visible"
                            type="checkbox"
                            wire:model.live="selectPage"
                            class="sf-checkbox"
                        >
                    </th>
                    @foreach($columns as $column)
                        <th scope="col" class="sf-table-header">
                            @if($column->isSortable())
                                <button type="button" wire:click="sortBy('{{ $column->name() }}')" class="inline-flex items-center gap-1 text-left hover:text-slate-900">
                                    <span>{{ $column->getLabel() }}</span>
                                    @if($sortColumn === $column->name())
                                        <span aria-hidden="true">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                    @endif
                                </button>
                            @else
                                {{ $column->getLabel() }}
                            @endif
                        </th>
                    @endforeach
                    <th scope="col" class="sf-table-header">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $record)
                    <tr>
                        <td class="sf-table-cell px-4">
                            <label for="record-select-{{ $record->getKey() }}" class="sr-only">Select record {{ $record->getKey() }}</label>
                            <input
                                id="record-select-{{ $record->getKey() }}"
                                type="checkbox"
                                wire:model.live="selectedRecords"
                                value="{{ $record->getKey() }}"
                                class="sf-checkbox"
                            >
                        </td>
                        @foreach($columns as $column)
                            <td class="sf-table-cell">{!! $formatValue($record, $column) !!}</td>
                        @endforeach
                        <td class="sf-table-cell">
                            <div class="flex flex-wrap items-center gap-2">
                                @if($canViewRecord($record))
                                    <a href="{{ $showUrl($record) }}" class="sf-button sf-button-secondary !px-3 !py-1.5">
                                        View
                                    </a>
                                @endif
                                @if($hasForm && $canUpdateRecord($record))
                                    <a href="{{ $editUrl($record) }}" class="sf-button sf-button-secondary !px-3 !py-1.5">
                                        Edit
                                    </a>
                                @endif
                                @if($canDeleteRecord($record))
                                    <button
                                        type="button"
                                        wire:click="deleteRecord({{ $record->getKey() }})"
                                        wire:confirm="{{ $supportsSoftDeletes ? 'Are you sure you want to move this record to trash?' : 'Are you sure you want to delete this record?' }}"
                                        class="sf-button sf-button-danger !px-3 !py-1.5"
                                    >
                                        {{ $supportsSoftDeletes ? 'Move to trash' : 'Delete' }}
                                    </button>
                                @endif
                                @if($canRestoreRecord($record))
                                    <button
                                        type="button"
                                        wire:click="restoreRecord({{ $record->getKey() }})"
                                        wire:confirm="Are you sure you want to restore this record?"
                                        class="sf-button sf-button-secondary !px-3 !py-1.5"
                                    >
                                        Restore
                                    </button>
                                @endif
                                @foreach($rowActions as $action)
                                    @if(! $canExecuteAction($action, $record))
                                        @continue
                                    @endif
                                    @if($pendingActionName === $action->name() && (string) $pendingActionRecordId === (string) $record->getKey())
                                        <div class="min-w-64">
                                            @component('seviaflow-ui::livewire.partials.action-confirmation', [
                                                'variant' => $action->isDanger() ? 'danger' : 'safe',
                                                'title' => $action->isDanger() ? 'Confirm dangerous action' : 'Confirm action',
                                                'message' => 'Please confirm before continuing.',
                                                'consequences' => $action->isDanger()
                                                    ? ['This action affects the selected record immediately.']
                                                    : [],
                                            ])
                                                <button
                                                    type="button"
                                                    wire:click="executeAction('{{ $record->getKey() }}', '{{ $action->name() }}', true)"
                                                    class="sf-button {{ $action->isDanger() ? 'sf-button-danger' : 'sf-button-primary' }} !px-3 !py-1.5"
                                                >
                                                    {{ $action->getConfirmButton() ?? 'Confirm' }}
                                                </button>
                                                <button
                                                    type="button"
                                                    wire:click="$set('pendingActionName', null); $set('pendingActionRecordId', null);"
                                                    class="sf-button sf-button-secondary !px-3 !py-1.5"
                                                >
                                                    {{ $action->getCancelButton() ?? 'Cancel' }}
                                                </button>
                                            @endcomponent
                                        </div>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="executeAction('{{ $record->getKey() }}', '{{ $action->name() }}')"
                                            @class([
                                                'sf-button !px-3 !py-1.5',
                                                'sf-button-danger' => $action->isDanger(),
                                                'sf-button-secondary' => ! $action->isDanger(),
                                            ])
                                        >
                                            {{ $action->getLabel() }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(count($columns) + 2, 1) }}" class="sf-table-empty">
                            {{ $emptyMessage }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="sf-card sf-pagination">
        {{ $records->links() }}
    </div>
</div>
