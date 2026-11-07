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

        <section class="sf-panel sf-resource-toolbar-panel" aria-label="{{ __('seviaflow-ui::panel.record_tools') }}">
            <div class="sf-resource-toolbar sf-resource-toolbar-unified">
                <div class="sf-toolbar-search">
                    <label for="resource-search" class="sr-only">{{ __('seviaflow-ui::panel.search_records') }}</label>
                    <x-seviaflow-ui::icon name="magnifying-glass" class="sf-toolbar-search-icon" />
                    <input
                        id="resource-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('seviaflow-ui::panel.search_records') }}"
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
                        title="{{ __('seviaflow-ui::panel.filters') }}"
                    >
                        <x-seviaflow-ui::icon name="funnel" class="sf-action-icon" />
                        <span class="sf-toolbar-control-label">{{ __('seviaflow-ui::panel.filters') }}</span>
                        @if($activeFiltersCount > 0)
                            <span class="sf-summary-pill sf-summary-pill-strong">{{ $activeFiltersCount }}</span>
                            <span class="sr-only">{{ $activeFiltersCount === 1 ? __('seviaflow-ui::panel.filter_active') : __('seviaflow-ui::panel.filters_active') }}</span>
                        @endif
                    </button>
                @endif

                <details class="sf-toolbar-dropdown" data-sf-toolbar-menu wire:ignore.self wire:key="resource-views-menu">
                    <summary class="sf-button sf-button-ghost" title="{{ __('seviaflow-ui::panel.views') }}">
                        <x-seviaflow-ui::icon name="bookmark" class="sf-action-icon" />
                        <span class="sf-toolbar-control-label">{{ __('seviaflow-ui::panel.views') }}</span>
                        <span class="sf-toolbar-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="sf-toolbar-menu" aria-label="{{ __('seviaflow-ui::panel.saved_views') }}">
                        <p class="sf-toolbar-menu-label">{{ __('seviaflow-ui::panel.saved_views') }}</p>
                        @if($savedViewModified)
                            <p class="sf-help sf-toolbar-menu-empty" role="status">{{ __('seviaflow-ui::panel.current_view_modified') }}</p>
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
                            <p class="sf-help sf-toolbar-menu-empty">{{ __('seviaflow-ui::panel.no_saved_views_yet') }}</p>
                        @endforelse
                        <div class="sf-toolbar-menu-divider"></div>
                        <button type="button" wire:click="openSaveViewForm" class="sf-toolbar-menu-item" data-sf-menu-close>
                            <x-seviaflow-ui::icon name="plus-circle" class="sf-action-icon" />
                            <span>{{ __('seviaflow-ui::panel.save_current_view') }}</span>
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
                                <span>{{ __('seviaflow-ui::panel.delete_current_view') }}</span>
                            </button>
                        @endif
                    </div>
                </details>

                @if($selectedCount > 0)
                    <div class="sf-toolbar-selection" wire:key="resource-selection-tools">
                        <span class="sf-toolbar-selection-count" role="status">
                            {{ trans_choice('seviaflow-ui::panel.selected_records', $selectedCount, ['count' => $selectedCount]) }}
                        </span>
                        @if(count($bulkActions) > 0)
                            <details class="sf-toolbar-dropdown" data-sf-toolbar-menu wire:ignore.self>
                                <summary class="sf-button sf-button-ghost">
                                    <span>{{ __('seviaflow-ui::panel.bulk_actions') }}</span>
                                    <span class="sf-toolbar-chevron" aria-hidden="true"></span>
                                </summary>
                                <div class="sf-toolbar-menu" aria-label="{{ __('seviaflow-ui::panel.bulk_actions') }}">
                                    <p class="sf-help sf-toolbar-menu-empty">
                                        {{ __('seviaflow-ui::panel.selected_on_page', ['count' => $selectedVisibleCount]) }}
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
                        <button type="button" wire:click="clearSelection" class="sf-button sf-button-ghost sf-toolbar-icon-button" aria-label="{{ __('seviaflow-ui::panel.clear_selection') }}" title="{{ __('seviaflow-ui::panel.clear_selection') }}">
                            <x-seviaflow-ui::icon name="x-circle" class="sf-action-icon" />
                        </button>
                    </div>
                @else
                    <span class="sr-only" role="status">{{ __('seviaflow-ui::panel.0_records_selected') }}</span>
                @endif

                <div class="sf-toolbar-end">
                    <details class="sf-toolbar-dropdown" data-sf-toolbar-menu wire:ignore.self wire:key="resource-settings-menu">
                        <summary class="sf-button sf-button-ghost sf-toolbar-icon-button" title="{{ __('seviaflow-ui::panel.table_settings') }}">
                            <x-seviaflow-ui::icon name="adjustments-horizontal" class="sf-action-icon" />
                            <span class="sr-only">{{ __('seviaflow-ui::panel.table_settings') }}</span>
                        </summary>
                        <div class="sf-toolbar-menu sf-toolbar-menu-end" aria-label="{{ __('seviaflow-ui::panel.table_settings') }}">
                            <p class="sf-toolbar-menu-label">{{ __('seviaflow-ui::panel.table_settings') }}</p>
                            @if(count($columnSettings) > 0)
                                <button
                                    type="button"
                                    class="sf-toolbar-menu-item"
                                    data-sf-drawer-target="sf-resource-columns"
                                    aria-controls="sf-resource-columns"
                                    aria-expanded="false"
                                    data-sf-menu-close
                                >
                                    {{ __('seviaflow-ui::panel.columns_and_order') }}
                                </button>
                                <div class="sf-toolbar-menu-divider"></div>
                            @endif
                            <div class="sf-toolbar-menu-field">
                                <p class="sf-label">{{ __('seviaflow-ui::panel.table_density') }}</p>
                                <div class="sf-density-toggle" role="group" aria-label="{{ __('seviaflow-ui::panel.table_density') }}">
                                    <button type="button" data-sf-density="comfortable" aria-pressed="true">{{ __('seviaflow-ui::panel.comfortable') }}</button>
                                    <button type="button" data-sf-density="compact" aria-pressed="false">{{ __('seviaflow-ui::panel.compact') }}</button>
                                </div>
                            </div>
                            <div class="sf-toolbar-menu-field">
                                <label for="resource-per-page" class="sf-label">{{ __('seviaflow-ui::panel.records_per_page') }}</label>
                                <select id="resource-per-page" wire:model.live="perPage" class="sf-select">
                                    @foreach($perPageOptions as $option)
                                        <option value="{{ $option }}">{{ __('seviaflow-ui::panel.per_page', ['count' => $option]) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if($supportsSoftDeletes)
                                <div class="sf-toolbar-menu-field">
                                    <label for="resource-trashed-scope" class="sf-label">{{ __('seviaflow-ui::panel.trash_scope') }}</label>
                                    <select id="resource-trashed-scope" wire:model.live="trashedScope" class="sf-select">
                                        <option value="active">{{ __('seviaflow-ui::panel.active_records') }}</option>
                                        <option value="trashed">{{ __('seviaflow-ui::panel.trashed_records') }}</option>
                                        <option value="with_trashed">{{ __('seviaflow-ui::panel.all_records') }}</option>
                                    </select>
                                </div>
                            @endif
                        </div>
                    </details>

                    @if($canExport || $canImport)
                        <details class="sf-toolbar-dropdown" data-sf-toolbar-menu wire:ignore.self wire:key="resource-more-menu">
                            <summary class="sf-button sf-button-ghost" title="{{ __('seviaflow-ui::panel.more_actions') }}">
                                <span>{{ __('seviaflow-ui::panel.more') }}</span>
                                <span class="sf-toolbar-chevron" aria-hidden="true"></span>
                            </summary>
                            <div class="sf-toolbar-menu sf-toolbar-menu-end" aria-label="{{ __('seviaflow-ui::panel.more_actions') }}">
                                @if($canExport)
                                    <button type="button" wire:click="openExportModal" data-sf-modal-target="sf-resource-export" aria-controls="sf-resource-export" aria-expanded="false" class="sf-toolbar-menu-item" data-sf-menu-close>{{ __('seviaflow-ui::panel.export') }}</button>
                                @endif
                                @if($canImport)
                                    <button type="button" wire:click="openImportModal" data-sf-modal-target="sf-resource-import" aria-controls="sf-resource-import" aria-expanded="false" class="sf-toolbar-menu-item" data-sf-menu-close>{{ __('seviaflow-ui::panel.import') }}</button>
                                @endif
                            </div>
                        </details>
                    @endif

                    @if($hasForm && $canCreate)
                        <a href="{{ $createUrl }}" class="sf-button sf-button-primary">
                            <x-seviaflow-ui::icon name="plus-circle" class="sf-action-icon" />
                            <span>{{ __('seviaflow-ui::panel.create') }}</span>
                        </a>
                    @endif
                </div>
            </div>

            @if($showSaveViewForm)
                <div class="sf-toolbar-context grid gap-2 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                    <div>
                        <label for="saved-view-name" class="sf-label">{{ __('seviaflow-ui::panel.view_name') }}</label>
                        <input
                            id="saved-view-name"
                            type="text"
                            wire:model.live="savedViewName"
                            placeholder="{{ __('seviaflow-ui::panel.e_g_published_this_month') }}"
                            class="sf-input"
                        >
                    </div>
                    <button type="button" wire:click="saveCurrentView" class="sf-button sf-button-primary !px-3 !py-1.5">
                        {{ __('seviaflow-ui::panel.save_view') }}
                    </button>
                    <button type="button" wire:click="cancelSaveView" class="sf-button sf-button-secondary !px-3 !py-1.5">
                        {{ __('seviaflow-ui::ui.actions.cancel') }}
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
                                    'title' => $action->isDanger() ? __('seviaflow-ui::panel.confirm_dangerous_bulk_action') : __('seviaflow-ui::panel.confirm_bulk_action'),
                                    'message' => trans_choice('seviaflow-ui::panel.bulk_confirmation', $selectedCount, ['count' => $selectedCount]),
                                    'consequences' => $action->isDanger()
                                        ? [__('seviaflow-ui::panel.selected_records_may_be_changed_immediately'), __('seviaflow-ui::panel.this_action_may_be_hard_to_undo')]
                                        : [],
                                ])
                                    <button
                                        type="button"
                                        wire:click="executeBulkAction('{{ $action->name() }}', true)"
                                        class="sf-button {{ $action->isDanger() ? 'sf-button-danger' : 'sf-button-primary' }} !px-3 !py-1.5"
                                    >
                                        {{ $action->getConfirmButton() ?? __('seviaflow-ui::ui.actions.confirm') }}
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="$set('pendingBulkActionName', null)"
                                        class="sf-button sf-button-secondary !px-3 !py-1.5"
                                    >
                                        {{ $action->getCancelButton() ?? __('seviaflow-ui::ui.actions.cancel') }}
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
                        <h2 id="sf-resource-export-title" class="sf-card-title">{{ __('seviaflow-ui::panel.export_records') }}</h2>
                        <button type="button" class="sf-icon-button" aria-label="{{ __('seviaflow-ui::panel.close_export_dialog') }}" data-sf-modal-close wire:click="closeExportModal">
                            <x-seviaflow-ui::icon name="x-circle" />
                        </button>
                    </div>
                    <div class="sf-modal-body grid gap-4">
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <label class="sf-label mb-0">{{ __('seviaflow-ui::panel.fields') }}</label>
                            <button type="button" wire:click="selectAllExportFields" class="sf-button sf-button-secondary !px-3 !py-1.5">
                                {{ __('seviaflow-ui::panel.select_all') }}
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
                            <label for="export-format" class="sf-label">{{ __('seviaflow-ui::panel.format') }}</label>
                            <select id="export-format" wire:model.live="selectedExportFormat" class="sf-select">
                                @foreach($exportFormats as $format)
                                    <option value="{{ $format }}">{{ strtoupper($format) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="export-scope" class="sf-label">{{ __('seviaflow-ui::panel.scope') }}</label>
                            <select id="export-scope" wire:model.live="exportScope" class="sf-select">
                                <option value="current_view">{{ __('seviaflow-ui::panel.current_view') }}</option>
                                <option value="selected_rows">{{ __('seviaflow-ui::panel.selected_rows') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="sf-modal-footer">
                        <button type="button" wire:click="exportRecords" class="sf-button sf-button-primary !px-3 !py-1.5">
                            {{ __('seviaflow-ui::panel.export') }}
                        </button>
                        <button type="button" wire:click="closeExportModal" class="sf-button sf-button-secondary !px-3 !py-1.5" data-sf-modal-close>
                            {{ __('seviaflow-ui::ui.actions.cancel') }}
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
                            <h2 id="sf-resource-import-title" class="sf-card-title">{{ __('seviaflow-ui::panel.import_csv_preview') }}</h2>
                            <p class="sf-card-description">{{ __('seviaflow-ui::panel.headers_must_match_allowed_fields_exactly') }}</p>
                        </div>
                        <button type="button" class="sf-icon-button" aria-label="{{ __('seviaflow-ui::panel.close_import_dialog') }}" data-sf-modal-close wire:click="closeImportModal">
                            <x-seviaflow-ui::icon name="x-circle" />
                        </button>
                    </div>
                    <div class="sf-modal-body grid gap-4">
                    <div>
                        <label for="import-file" class="sf-label">{{ __('seviaflow-ui::panel.csv_file') }}</label>
                        <input id="import-file" type="file" wire:model="importFile" accept=".csv,text/csv" class="sf-input">
                        @error('importFile')
                            <p class="sf-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sf-inset-panel text-xs">
                        {{ __('seviaflow-ui::panel.allowed_fields', ['fields' => implode(', ', $importAllowedFields)]) }}
                        @if(count($importRequiredFields) > 0)
                            <br>{{ __('seviaflow-ui::panel.required_fields', ['fields' => implode(', ', $importRequiredFields)]) }}
                        @endif
                        <button type="button" wire:click="downloadImportTemplate" class="sf-button sf-button-secondary mt-3 !px-3 !py-1.5">
                            {{ __('seviaflow-ui::panel.download_csv_template') }}
                        </button>
                    </div>

                    <div class="sf-modal-footer">
                        <button type="button" wire:click="previewImport" class="sf-button sf-button-primary !px-3 !py-1.5">
                            {{ __('seviaflow-ui::panel.preview') }}
                        </button>
                        <button type="button" wire:click="executeImport" class="sf-button sf-button-primary !px-3 !py-1.5">
                            {{ __('seviaflow-ui::panel.import_now') }}
                        </button>
                        <button type="button" wire:click="closeImportModal" class="sf-button sf-button-secondary !px-3 !py-1.5" data-sf-modal-close>
                            {{ __('seviaflow-ui::ui.actions.cancel') }}
                        </button>
                    </div>

                    @if(count($importPreviewErrors) > 0)
                        <div class="sf-alert sf-alert-warning">
                            <p class="font-semibold">{{ __('seviaflow-ui::panel.preview_errors') }}</p>
                            <ul class="mt-1 list-disc space-y-1 pl-5 text-xs">
                                @foreach($importPreviewErrors as $previewError)
                                    <li>{{ $previewError }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(count($importPreviewHeaders) > 0)
                        <div>
                            <p class="sf-label">{{ __('seviaflow-ui::panel.detected_headers') }}</p>
                            <p class="sf-help mt-1">{{ implode(', ', $importPreviewHeaders) }}</p>
                        </div>
                    @endif

                    @if(count($importPreviewRows) > 0)
                        <div class="sf-table-wrapper">
                            <table class="sf-table">
                                <thead>
                                    <tr>
                                        <th class="sf-table-header">{{ __('seviaflow-ui::panel.row') }}</th>
                                        @foreach($importPreviewHeaders as $header)
                                            <th class="sf-table-header">{{ $header }}</th>
                                        @endforeach
                                        <th class="sf-table-header">{{ __('seviaflow-ui::panel.errors') }}</th>
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
                            <label for="select-all-visible" class="sr-only">{{ __('seviaflow-ui::panel.select_all_visible_records') }}</label>
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
                                        aria-label="{{ __('seviaflow-ui::panel.sort_by', ['column' => $column->getLabel()]) }}"
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
                        <th scope="col" class="sf-table-header">{{ __('seviaflow-ui::panel.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td class="sf-table-cell px-4">
                                <label for="record-select-{{ $record->getKey() }}" class="sr-only">{{ __('seviaflow-ui::panel.select_record', ['id' => $record->getKey()]) }}</label>
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
                                            {{ __('seviaflow-ui::panel.view') }}
                                        </a>
                                    @endif
                                    @if($hasForm && $canUpdateRecord($record))
                                        <a href="{{ $editUrl($record) }}" class="sf-button sf-button-secondary !px-3 !py-1.5">
                                            {{ __('seviaflow-ui::panel.edit') }}
                                        </a>
                                    @endif
                                    @if($canDeleteRecord($record))
                                        <button
                                            type="button"
                                            wire:click="deleteRecord({{ $record->getKey() }})"
                                            wire:confirm="{{ $supportsSoftDeletes ? __('seviaflow-ui::panel.are_you_sure_you_want_to_move_this_record_to_trash') : __('seviaflow-ui::panel.are_you_sure_you_want_to_delete_this_record') }}"
                                            class="sf-button sf-button-danger !px-3 !py-1.5"
                                        >
                                            {{ $supportsSoftDeletes ? __('seviaflow-ui::panel.move_to_trash') : __('seviaflow-ui::panel.delete') }}
                                        </button>
                                    @endif
                                    @if($canRestoreRecord($record))
                                        <button
                                            type="button"
                                            wire:click="restoreRecord({{ $record->getKey() }})"
                                            wire:confirm="Are you sure you want to restore this record?"
                                            class="sf-button sf-button-secondary !px-3 !py-1.5"
                                        >
                                            {{ __('seviaflow-ui::panel.restore') }}
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
                                                    'title' => $action->isDanger() ? __('seviaflow-ui::panel.confirm_dangerous_action') : __('seviaflow-ui::panel.confirm_action'),
                                                    'message' => __('seviaflow-ui::panel.please_confirm_before_continuing'),
                                                    'consequences' => $action->isDanger()
                                                        ? [__('seviaflow-ui::panel.this_action_affects_the_selected_record_immediately')]
                                                        : [],
                                                ])
                                                    <button
                                                        type="button"
                                                        wire:click="executeAction('{{ $record->getKey() }}', '{{ $action->name() }}', true)"
                                                        class="sf-button {{ $action->isDanger() ? 'sf-button-danger' : 'sf-button-primary' }} !px-3 !py-1.5"
                                                    >
                                                        {{ $action->getConfirmButton() ?? __('seviaflow-ui::ui.actions.confirm') }}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click="$set('pendingActionName', null); $set('pendingActionRecordId', null);"
                                                        class="sf-button sf-button-secondary !px-3 !py-1.5"
                                                    >
                                                        {{ $action->getCancelButton() ?? __('seviaflow-ui::ui.actions.cancel') }}
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
                                        {{ __('seviaflow-ui::panel.reset_list_view') }}
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
                    <h2 id="sf-resource-columns-title" class="sf-drawer-title">{{ __('seviaflow-ui::panel.table_settings') }}</h2>
                    <p class="sf-drawer-description">{{ __('seviaflow-ui::panel.choose_visible_columns_and_their_order_api_and_export_fields_are_unchanged') }}</p>
                </div>
                <button type="button" class="sf-drawer-close" aria-label="{{ __('seviaflow-ui::panel.close_table_settings') }}" data-sf-drawer-close>
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
                                    aria-label="{{ __('seviaflow-ui::panel.move_up', ['column' => $columnSetting['label']]) }}"
                                >
                                    {{ __('seviaflow-ui::panel.up') }}
                                </button>
                                <button
                                    type="button"
                                    wire:click="moveColumnDown('{{ $columnSetting['name'] }}')"
                                    class="sf-button sf-button-secondary !px-2 !py-1"
                                    @disabled(! $columnSetting['can_move_down'])
                                    aria-label="{{ __('seviaflow-ui::panel.move_down', ['column' => $columnSetting['label']]) }}"
                                >
                                    {{ __('seviaflow-ui::panel.down') }}
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="sf-drawer-footer">
                <button type="button" class="sf-button sf-button-primary" data-sf-drawer-close>{{ __('seviaflow-ui::panel.done') }}</button>
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
                    <h2 id="sf-resource-filters-title" class="sf-drawer-title">{{ __('seviaflow-ui::panel.filters') }}</h2>
                    <p class="sf-drawer-description">
                        {{ trans_choice('seviaflow-ui::panel.active_filters_count', $activeFiltersCount, ['count' => $activeFiltersCount]) }}. {{ __('seviaflow-ui::panel.filters_apply_live') }}
                    </p>
                </div>
                <button type="button" class="sf-drawer-close" aria-label="{{ __('seviaflow-ui::panel.close_filters') }}" data-sf-drawer-close>
                    <x-seviaflow-ui::icon name="x-circle" class="sf-drawer-close-icon" />
                </button>
            </div>

            <div class="sf-drawer-body">
                @if(count($activeFilterEntries) > 0)
                    <div class="sf-resource-summary mt-3" aria-label="{{ __('seviaflow-ui::panel.active_filters') }}">
                        <span class="sf-help">{{ __('seviaflow-ui::panel.active_filters_label') }}</span>
                        @foreach($activeFilterEntries as $activeFilter)
                            <button
                                type="button"
                                wire:click="clearFilter('{{ $activeFilter['name'] }}')"
                                class="sf-summary-pill sf-summary-pill-strong"
                                aria-label="{{ __('seviaflow-ui::panel.remove_filter', ['filter' => $activeFilter['label']]) }}"
                            >
                                {{ $activeFilter['label'] }}: {{ $activeFilter['value'] }} ×
                            </button>
                        @endforeach
                        <button type="button" wire:click="resetFilters" class="sf-button sf-button-secondary !px-2 !py-1">
                            {{ __('seviaflow-ui::panel.reset_filters') }}
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
                                    <option value="">{{ $filter->getPlaceholder() ?? __('seviaflow-ui::panel.any') }}</option>
                                    <option value="1">{{ __('seviaflow-ui::panel.yes') }}</option>
                                    <option value="0">{{ __('seviaflow-ui::panel.no') }}</option>
                                </select>
                            @elseif($filter instanceof \SeviaFlow\Core\Resources\Filters\BelongsToFilter)
                                <select
                                    id="filter-{{ $filter->name() }}"
                                    wire:model.live="filterValues.{{ $filter->name() }}"
                                    class="sf-select"
                                >
                                    <option value="">{{ $filter->getPlaceholder() ?? __('seviaflow-ui::panel.any') }}</option>
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
                                    <option value="">{{ $filter->getPlaceholder() ?? __('seviaflow-ui::panel.any') }}</option>
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
                                    placeholder="{{ $filter->getPlaceholder() ?? __('seviaflow-ui::panel.type_to_filter') }}"
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
                                        placeholder="{{ __('seviaflow-ui::panel.from') }}"
                                        class="sf-input"
                                    >
                                    <input
                                        id="filter-{{ $filter->name() }}-to"
                                        type="datetime-local"
                                        wire:model.live="filterValues.{{ $filter->name() }}.to"
                                        placeholder="{{ __('seviaflow-ui::panel.to') }}"
                                        class="sf-input"
                                    >
                                </div>
                            @elseif($filter instanceof \SeviaFlow\Core\Resources\Filters\DateRangeFilter)
                                <div class="grid grid-cols-2 gap-2">
                                    <input
                                        id="filter-{{ $filter->name() }}-from"
                                        type="date"
                                        wire:model.live="filterValues.{{ $filter->name() }}.from"
                                        placeholder="{{ __('seviaflow-ui::panel.from') }}"
                                        class="sf-input"
                                    >
                                    <input
                                        id="filter-{{ $filter->name() }}-to"
                                        type="date"
                                        wire:model.live="filterValues.{{ $filter->name() }}.to"
                                        placeholder="{{ __('seviaflow-ui::panel.to') }}"
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
                                    placeholder="{{ $filter->getPlaceholder() ?? __('seviaflow-ui::panel.any_value') }}"
                                    class="sf-input"
                                >
                            @elseif($filter instanceof \SeviaFlow\Core\Resources\Filters\NumberRangeFilter)
                                <div class="grid grid-cols-2 gap-2">
                                    <input
                                        id="filter-{{ $filter->name() }}-min"
                                        type="number"
                                        step="any"
                                        wire:model.live="filterValues.{{ $filter->name() }}.min"
                                        placeholder="{{ __('seviaflow-ui::panel.min') }}"
                                        class="sf-input"
                                    >
                                    <input
                                        id="filter-{{ $filter->name() }}-max"
                                        type="number"
                                        step="any"
                                        wire:model.live="filterValues.{{ $filter->name() }}.max"
                                        placeholder="{{ __('seviaflow-ui::panel.max') }}"
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
                    {{ __('seviaflow-ui::panel.reset_filters') }}
                </button>
                <button type="button" class="sf-button sf-button-primary" data-sf-drawer-close>
                    {{ __('seviaflow-ui::ui.actions.close') }}
                </button>
            </div>
        </aside>
    @endif
</div>
