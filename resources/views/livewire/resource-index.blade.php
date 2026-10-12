<div class="sf-resource-index">
    <div class="sf-livewire-loading" wire:loading wire:target="search,perPage,trashedScope,filterValues,selectedSavedViewId,applySavedView" role="status" aria-live="polite">
        {{ __('seviaflow-ui::ui.states.loading') }}
    </div>
    <div class="grid gap-4">
        @if($statusMessage)
            @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'success', 'message' => $statusMessage])
        @endif
        @if($errorMessage)
            @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'danger', 'message' => $errorMessage])
        @endif

        <section class="sf-panel sf-resource-toolbar-panel" aria-label="Record tools">
            <div class="sf-resource-toolbar sf-resource-toolbar-unified">
                <div class="sf-toolbar-search">
                    <label for="resource-search" class="sr-only">Search records</label>
                    <x-seviaflow-ui::icon name="magnifying-glass" class="sf-toolbar-search-icon" />
                    <input
                        id="resource-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search records"
                        class="sf-input"
                    >
                </div>

                @if(count($filters) > 0)
                    <button
                        type="button"
                        class="sf-button sf-button-secondary"
                        id="sf-resource-filters-trigger"
                        data-sf-drawer-target="sf-resource-filters"
                        aria-controls="sf-resource-filters"
                        aria-expanded="false"
                        title="Filters"
                    >
                        <x-seviaflow-ui::icon name="funnel" class="sf-action-icon" />
                        <span class="sf-toolbar-control-label">Filters</span>
                        @if($activeFiltersCount > 0)
                            <span class="sf-summary-pill sf-summary-pill-strong">{{ $activeFiltersCount }}</span>
                            <span class="sr-only">{{ $activeFiltersCount === 1 ? 'filter active' : 'filters active' }}</span>
                        @endif
                    </button>
                @endif

                <details class="sf-toolbar-dropdown" data-sf-toolbar-menu wire:ignore.self wire:key="resource-views-menu">
                    <summary class="sf-button sf-button-ghost" title="Views">
                        <x-seviaflow-ui::icon name="bookmark" class="sf-action-icon" />
                        <span class="sf-toolbar-control-label">Views</span>
                        <span class="sf-toolbar-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="sf-toolbar-menu" aria-label="Saved views">
                        <p class="sf-toolbar-menu-label">Saved views</p>
                        @if($savedViewModified)
                            <p class="sf-help sf-toolbar-menu-empty" role="status">Current view modified</p>
                        @endif
                        @forelse($savedViews as $savedView)
                            <button
                                type="button"
                                wire:key="saved-view-{{ $savedView->id }}"
                                wire:click="applySavedView('{{ $savedView->id }}')"
                                class="sf-toolbar-menu-item"
                                aria-pressed="{{ $selectedSavedViewId === (string) $savedView->id ? 'true' : 'false' }}"
                                data-sf-menu-close
                            >
                                <span>{{ $savedView->name }}</span>
                                @if($selectedSavedViewId === (string) $savedView->id)
                                    <x-seviaflow-ui::icon name="check-circle" class="sf-action-icon" />
                                @endif
                            </button>
                        @empty
                            <p class="sf-help sf-toolbar-menu-empty">No saved views yet.</p>
                        @endforelse
                        <div class="sf-toolbar-menu-divider"></div>
                        <button type="button" wire:click="openSaveViewForm" class="sf-toolbar-menu-item" data-sf-menu-close>
                            <x-seviaflow-ui::icon name="plus-circle" class="sf-action-icon" />
                            <span>Save current view</span>
                        </button>
                        @if($selectedSavedViewId !== null && $selectedSavedViewId !== '')
                            <button
                                type="button"
                                wire:click="deleteSavedView('{{ $selectedSavedViewId }}')"
                                wire:confirm="Delete this saved view?"
                                class="sf-toolbar-menu-item sf-toolbar-menu-item-danger"
                                data-sf-menu-close
                            >
                                <x-seviaflow-ui::icon name="trash" class="sf-action-icon" />
                                <span>Delete current view</span>
                            </button>
                        @endif
                    </div>
                </details>

                @if($selectedCount > 0)
                    <div class="sf-toolbar-selection" wire:key="resource-selection-tools">
                        <span class="sf-toolbar-selection-count" role="status">
                            {{ $selectedCount }} {{ $selectedCount === 1 ? 'record' : 'records' }} selected
                        </span>
                        @if(count($bulkActions) > 0)
                            <details class="sf-toolbar-dropdown" data-sf-toolbar-menu wire:ignore.self>
                                <summary class="sf-button sf-button-ghost">
                                    <span>Bulk actions</span>
                                    <span class="sf-toolbar-chevron" aria-hidden="true"></span>
                                </summary>
                                <div class="sf-toolbar-menu" aria-label="Bulk actions">
                                    <p class="sf-help sf-toolbar-menu-empty">
                                        {{ $selectedVisibleCount }} selected on this page; bulk actions never select the full result set automatically.
                                    </p>
                                    @foreach($bulkActions as $action)
                                        <button
                                            type="button"
                                            wire:click="executeBulkAction('{{ $action->name() }}')"
                                            @class([
                                                'sf-toolbar-menu-item',
                                                'sf-toolbar-menu-item-danger' => $action->isDanger(),
                                            ])
                                            data-sf-menu-close
                                        >
                                            {{ $action->getLabel() }}
                                        </button>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                        <button type="button" wire:click="clearSelection" class="sf-button sf-button-ghost sf-toolbar-icon-button" aria-label="Clear selection" title="Clear selection">
                            <x-seviaflow-ui::icon name="x-circle" class="sf-action-icon" />
                        </button>
                    </div>
                @else
                    <span class="sr-only" role="status">0 records selected</span>
                @endif

                <div class="sf-toolbar-end">
                    <details class="sf-toolbar-dropdown" data-sf-toolbar-menu wire:ignore.self wire:key="resource-settings-menu">
                        <summary class="sf-button sf-button-ghost sf-toolbar-icon-button" title="Table settings">
                            <x-seviaflow-ui::icon name="adjustments-horizontal" class="sf-action-icon" />
                            <span class="sr-only">Table settings</span>
                        </summary>
                        <div class="sf-toolbar-menu sf-toolbar-menu-end" aria-label="Table settings">
                            <p class="sf-toolbar-menu-label">Table settings</p>
                            @if(count($columnSettings) > 0)
                                <button
                                    type="button"
                                    class="sf-toolbar-menu-item"
                                    data-sf-drawer-target="sf-resource-columns"
                                    aria-controls="sf-resource-columns"
                                    aria-expanded="false"
                                    data-sf-menu-close
                                >
                                    Columns and order
                                </button>
                                <div class="sf-toolbar-menu-divider"></div>
                            @endif
                            <div class="sf-toolbar-menu-field">
                                <p class="sf-label">Table density</p>
                                <div class="sf-density-toggle" role="group" aria-label="Table density">
                                    <button type="button" data-sf-density="comfortable" aria-pressed="true">Comfortable</button>
                                    <button type="button" data-sf-density="compact" aria-pressed="false">Compact</button>
                                </div>
                            </div>
                            <div class="sf-toolbar-menu-field">
                                <label for="resource-per-page" class="sf-label">Records per page</label>
                                <select id="resource-per-page" wire:model.live="perPage" class="sf-select">
                                    @foreach($perPageOptions as $option)
                                        <option value="{{ $option }}">{{ $option }} per page</option>
                                    @endforeach
                                </select>
                            </div>

                            @if($supportsSoftDeletes)
                                <div class="sf-toolbar-menu-field">
                                    <label for="resource-trashed-scope" class="sf-label">Trash scope</label>
                                    <select id="resource-trashed-scope" wire:model.live="trashedScope" class="sf-select">
                                        <option value="active">Active records</option>
                                        <option value="trashed">Trashed records</option>
                                        <option value="with_trashed">All records</option>
                                    </select>
                                </div>
                            @endif
                        </div>
                    </details>

                    @if($canExport || $canImport)
                        <details class="sf-toolbar-dropdown" data-sf-toolbar-menu wire:ignore.self wire:key="resource-more-menu">
                            <summary class="sf-button sf-button-ghost" title="More actions">
                                <span>More</span>
                                <span class="sf-toolbar-chevron" aria-hidden="true"></span>
                            </summary>
                            <div class="sf-toolbar-menu sf-toolbar-menu-end" aria-label="More actions">
                                @if($canExport)
                                    <button type="button" wire:click="openExportModal" data-sf-modal-target="sf-resource-export" aria-controls="sf-resource-export" aria-expanded="false" class="sf-toolbar-menu-item" data-sf-menu-close>Export</button>
                                @endif
                                @if($canImport)
                                    <button type="button" wire:click="openImportModal" data-sf-modal-target="sf-resource-import" aria-controls="sf-resource-import" aria-expanded="false" class="sf-toolbar-menu-item" data-sf-menu-close>Import</button>
                                @endif
                            </div>
                        </details>
                    @endif

                    @if($hasForm && $canCreate)
                        <a href="{{ $createUrl }}" class="sf-button sf-button-primary">
                            <x-seviaflow-ui::icon name="plus-circle" class="sf-action-icon" />
                            <span>Create</span>
                        </a>
                    @endif
                </div>
            </div>

            @if($showSaveViewForm)
                <div class="sf-toolbar-context grid gap-2 sm:grid-cols-[1fr_auto_auto] sm:items-end">
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
            @if($selectedCount > 0 && $pendingBulkActionName !== null)
                <div class="sf-toolbar-context">
                    @foreach($bulkActions as $action)
                        @if($pendingBulkActionName === $action->name())
                            <div class="w-full">
                                @component('seviaflow-ui::livewire.partials.action-confirmation', [
                                    'id' => 'sf-bulk-action-confirmation-'.\Illuminate\Support\Str::slug($action->name()),
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
                        @endif
                    @endforeach
                </div>
            @endif
        </section>

        @if($showExportModal && $canExport)
            <section id="sf-resource-export" class="sf-modal" role="dialog" aria-modal="true" aria-hidden="false" aria-labelledby="sf-resource-export-title" data-sf-modal>
                <div class="sf-modal-panel sf-card">
                    <div class="sf-modal-header">
                        <h2 id="sf-resource-export-title" class="sf-card-title">Export records</h2>
                        <button type="button" class="sf-icon-button" aria-label="Close export dialog" data-sf-modal-close wire:click="closeExportModal">
                            <x-seviaflow-ui::icon name="x-circle" />
                        </button>
                    </div>
                    <div class="sf-modal-body grid gap-4">
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <label class="sf-label mb-0">Fields</label>
                            <button type="button" wire:click="selectAllExportFields" class="sf-button sf-button-secondary !px-3 !py-1.5">
                                Select all
                            </button>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach($exportFields as $field)
                                <label class="sf-checkbox-label">
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

                    <div class="sf-modal-footer">
                        <button type="button" wire:click="exportRecords" class="sf-button sf-button-primary !px-3 !py-1.5">
                            Export
                        </button>
                        <button type="button" wire:click="closeExportModal" class="sf-button sf-button-secondary !px-3 !py-1.5" data-sf-modal-close>
                            Cancel
                        </button>
                    </div>
                    </div>
                </div>
            </section>
        @endif

        @if($showImportModal && $canImport)
            <section id="sf-resource-import" class="sf-modal" role="dialog" aria-modal="true" aria-hidden="false" aria-labelledby="sf-resource-import-title" data-sf-modal>
                <div class="sf-modal-panel sf-card">
                    <div class="sf-modal-header">
                        <div>
                            <h2 id="sf-resource-import-title" class="sf-card-title">Import CSV preview</h2>
                            <p class="sf-card-description">Headers must match allowed fields exactly.</p>
                        </div>
                        <button type="button" class="sf-icon-button" aria-label="Close import dialog" data-sf-modal-close wire:click="closeImportModal">
                            <x-seviaflow-ui::icon name="x-circle" />
                        </button>
                    </div>
                    <div class="sf-modal-body grid gap-4">
                    <div>
                        <label for="import-file" class="sf-label">CSV file</label>
                        <input id="import-file" type="file" wire:model="importFile" accept=".csv,text/csv" class="sf-input">
                        @error('importFile')
                            <p class="sf-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sf-inset-panel text-xs">
                        Allowed fields: {{ implode(', ', $importAllowedFields) }}
                        @if(count($importRequiredFields) > 0)
                            <br>Required fields: {{ implode(', ', $importRequiredFields) }}
                        @endif
                        <button type="button" wire:click="downloadImportTemplate" class="sf-button sf-button-secondary mt-3 !px-3 !py-1.5">
                            Download CSV template
                        </button>
                    </div>

                    <div class="sf-modal-footer">
                        <button type="button" wire:click="previewImport" class="sf-button sf-button-primary !px-3 !py-1.5">
                            Preview
                        </button>
                        <button type="button" wire:click="executeImport" class="sf-button sf-button-primary !px-3 !py-1.5">
                            Import now
                        </button>
                        <button type="button" wire:click="closeImportModal" class="sf-button sf-button-secondary !px-3 !py-1.5" data-sf-modal-close>
                            Cancel
                        </button>
                    </div>

                    @if(count($importPreviewErrors) > 0)
                        <div class="sf-alert sf-alert-warning">
                            <p class="font-semibold">Preview errors</p>
                            <ul class="mt-1 list-disc space-y-1 pl-5 text-xs">
                                @foreach($importPreviewErrors as $previewError)
                                    <li>{{ $previewError }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(count($importPreviewHeaders) > 0)
                        <div>
                            <p class="sf-label">Detected headers</p>
                            <p class="sf-help mt-1">{{ implode(', ', $importPreviewHeaders) }}</p>
                        </div>
                    @endif

                    @if(count($importPreviewRows) > 0)
                        <div class="sf-table-wrapper">
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
                </div>
            </section>
        @endif

        <div class="sf-table-wrapper sf-resource-table">
            <table class="sf-table">
                <thead>
                    <tr>
                        <th scope="col" class="sf-table-header px-4">
                            <label for="select-all-visible" class="sr-only">Select all visible records</label>
                            <input
                                id="select-all-visible"
                                type="checkbox"
                                wire:model.live="selectPage"
                                aria-checked="{{ $hasPartialSelection ? 'mixed' : ($selectPage ? 'true' : 'false') }}"
                                data-sf-partial-selection="{{ $hasPartialSelection ? 'true' : 'false' }}"
                                class="sf-checkbox"
                            >
                        </th>
                        @foreach($columns as $column)
                            <th
                                scope="col"
                                class="sf-table-header"
                                aria-sort="{{ $sortColumn === $column->name() ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"
                            >
                                @if($column->isSortable())
                                    <button
                                        type="button"
                                        wire:click="sortBy('{{ $column->name() }}')"
                                        class="sf-table-sort"
                                        aria-label="Sort by {{ $column->getLabel() }}"
                                    >
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
                                <div class="sf-row-actions">
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
                                                    'id' => 'sf-row-action-confirmation-'.$record->getKey().'-'.\Illuminate\Support\Str::slug($action->name()),
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
                                @if($hasActiveQuery)
                                    <button type="button" wire:click="resetTableState" class="sf-button sf-button-secondary mt-3 !px-3 !py-1.5">
                                        Reset list view
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="sf-pagination">
                {{ $records->onEachSide(1)->links('seviaflow-ui::components.paginator') }}
            </div>
        </div>
    </div>

    @if(count($columnSettings) > 0)
        <aside
            id="sf-resource-columns"
            class="sf-drawer"
            role="dialog"
            aria-modal="true"
            aria-hidden="true"
            aria-labelledby="sf-resource-columns-title"
            hidden
            data-sf-drawer
            wire:ignore.self
        >
            <div class="sf-drawer-header">
                <div>
                    <h2 id="sf-resource-columns-title" class="sf-drawer-title">Table settings</h2>
                    <p class="sf-drawer-description">Choose visible columns and their order. API and export fields are unchanged.</p>
                </div>
                <button type="button" class="sf-drawer-close" aria-label="Close table settings" data-sf-drawer-close>
                    <x-seviaflow-ui::icon name="x-circle" class="sf-drawer-close-icon" />
                </button>
            </div>

            <div class="sf-drawer-body grid gap-2">
                @foreach($columnSettings as $columnSetting)
                    <div class="sf-resource-toolbar">
                        <label class="sf-checkbox-label">
                            <input
                                type="checkbox"
                                class="sf-checkbox"
                                wire:click="toggleColumn('{{ $columnSetting['name'] }}')"
                                @checked($columnSetting['visible'])
                            >
                            <span>{{ $columnSetting['label'] }}</span>
                        </label>
                        @if($columnSetting['visible'])
                            <div class="sf-resource-actions">
                                <button
                                    type="button"
                                    wire:click="moveColumnUp('{{ $columnSetting['name'] }}')"
                                    class="sf-button sf-button-secondary !px-2 !py-1"
                                    @disabled(! $columnSetting['can_move_up'])
                                    aria-label="Move {{ $columnSetting['label'] }} up"
                                >
                                    Up
                                </button>
                                <button
                                    type="button"
                                    wire:click="moveColumnDown('{{ $columnSetting['name'] }}')"
                                    class="sf-button sf-button-secondary !px-2 !py-1"
                                    @disabled(! $columnSetting['can_move_down'])
                                    aria-label="Move {{ $columnSetting['label'] }} down"
                                >
                                    Down
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="sf-drawer-footer">
                <button type="button" class="sf-button sf-button-primary" data-sf-drawer-close>Done</button>
            </div>
        </aside>
    @endif

    @if(count($filters) > 0)
        <aside
            id="sf-resource-filters"
            class="sf-drawer"
            role="dialog"
            aria-modal="true"
            aria-hidden="true"
            aria-labelledby="sf-resource-filters-title"
            hidden
            data-sf-drawer
            wire:ignore.self
        >
            <div class="sf-drawer-header">
                <div>
                    <h2 id="sf-resource-filters-title" class="sf-drawer-title">Filters</h2>
                    <p class="sf-drawer-description">
                        {{ $activeFiltersCount }} {{ $activeFiltersCount === 1 ? 'filter active' : 'filters active' }}. Values apply as they change.
                    </p>
                </div>
                <button type="button" class="sf-drawer-close" aria-label="Close filters" data-sf-drawer-close>
                    <x-seviaflow-ui::icon name="x-circle" class="sf-drawer-close-icon" />
                </button>
            </div>

            <div class="sf-drawer-body">
                @if(count($activeFilterEntries) > 0)
                    <div class="sf-resource-summary mt-3" aria-label="Active filters">
                        <span class="sf-help">Active filters:</span>
                        @foreach($activeFilterEntries as $activeFilter)
                            <button
                                type="button"
                                wire:click="clearFilter('{{ $activeFilter['name'] }}')"
                                class="sf-summary-pill sf-summary-pill-strong"
                                aria-label="Remove {{ $activeFilter['label'] }} filter"
                            >
                                {{ $activeFilter['label'] }}: {{ $activeFilter['value'] }} ×
                            </button>
                        @endforeach
                        <button type="button" wire:click="resetFilters" class="sf-button sf-button-secondary !px-2 !py-1">
                            Reset filters
                        </button>
                    </div>
                @endif
                <div class="sf-filter-grid">
                    @foreach($filters as $filter)
                        <div class="sf-field">
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

            <div class="sf-drawer-footer">
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="sf-button sf-button-secondary"
                >
                    Reset filters
                </button>
                <button type="button" class="sf-button sf-button-primary" data-sf-drawer-close>
                    Close
                </button>
            </div>
        </aside>
    @endif
</div>
