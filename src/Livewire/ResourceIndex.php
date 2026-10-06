<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use SeviaFlow\Core\Exceptions\ResourceSlugNotRegisteredException;
use SeviaFlow\Core\Export\CsvExporter;
use SeviaFlow\Core\Resources\Action;
use SeviaFlow\Core\Resources\BulkAction;
use SeviaFlow\Core\Resources\Columns\Column;
use SeviaFlow\Core\Resources\ExportField;
use SeviaFlow\Core\Resources\Filters\BelongsToFilter;
use SeviaFlow\Core\Resources\Filters\BooleanFilter;
use SeviaFlow\Core\Resources\Filters\Filter;
use SeviaFlow\Core\Resources\Filters\MultiSelectFilter;
use SeviaFlow\Core\Resources\Filters\SelectFilter;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\ResourceAuthorization;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceEventDispatcher;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Resources\ResourceScopedQuery;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Core\Tables\ResourceTableQuery;
use SeviaFlow\Ui\Index\BulkActionRunner;
use SeviaFlow\Ui\Index\BulkSelectionState;
use SeviaFlow\Ui\Index\Export\ExportContext;
use SeviaFlow\Ui\Index\Export\ExportModalState;
use SeviaFlow\Ui\Index\Export\ExportRequestValidator;
use SeviaFlow\Ui\Index\Export\ExportRunner;
use SeviaFlow\Ui\Index\Import\ImportContext;
use SeviaFlow\Ui\Index\Import\ImportExecutionRunner;
use SeviaFlow\Ui\Index\Import\ImportModalState;
use SeviaFlow\Ui\Index\Import\ImportPreviewRunner;
use SeviaFlow\Ui\Index\RowActionRunner;
use SeviaFlow\Ui\Index\SavedViews\SavedViewActionRunner;
use SeviaFlow\Ui\Index\SavedViews\SavedViewStateManager;
use SeviaFlow\Ui\Index\TableAuthorizationState;
use SeviaFlow\Ui\Index\TableUrlFactory;
use SeviaFlow\Ui\Index\TableValueFormatter;
use SeviaFlow\Ui\SavedViews\SavedView;
use SeviaFlow\Ui\SavedViews\SavedViewRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ResourceIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    private const BUILT_IN_BULK_DELETE = '__built_in_bulk_delete';

    private const BUILT_IN_BULK_RESTORE = '__built_in_bulk_restore';

    #[Locked]
    public string $resourceSlug;

    #[Locked]
    public string $resource;

    #[Url(as: 'search', history: true, except: '')]
    public string $search = '';

    #[Url(as: 'sort', history: true, except: '')]
    public string $sortColumn = '';

    #[Url(as: 'direction', history: true, except: 'asc')]
    public string $sortDirection = 'asc';

    #[Url(as: 'per_page', history: true, except: 15)]
    public int $perPage = 15;

    #[Url(as: 'trash', history: true, except: 'active')]
    public string $trashedScope = 'active';

    /**
     * @var array<int, string>
     */
    public array $selectedRecords = [];

    public bool $selectPage = false;

    /**
     * @var array<string, mixed>
     */
    #[Url(as: 'filter', history: true, except: [])]
    public array $filterValues = [];

    /**
     * @var array<int, string>
     */
    public array $visibleColumnNames = [];

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public ?string $pendingActionName = null;

    public int|string|null $pendingActionRecordId = null;

    public ?string $pendingBulkActionName = null;

    public bool $showSaveViewForm = false;

    public string $savedViewName = '';

    public ?string $selectedSavedViewId = null;

    public bool $showExportModal = false;

    /**
     * @var array<int, string>
     */
    public array $selectedExportFields = [];

    public string $selectedExportFormat = 'csv';

    public string $exportScope = 'current_view';

    public bool $showImportModal = false;

    public mixed $importFile = null;

    /**
     * @var array<int, string>
     */
    public array $importPreviewHeaders = [];

    /**
     * @var array<int, array{index:int, values:array<string, string>, errors:array<int, string>}>
     */
    public array $importPreviewRows = [];

    /**
     * @var array<int, string>
     */
    public array $importPreviewErrors = [];

    public function mount(string $resource): void
    {
        try {
            $resourceClass = app(ResourceRegistry::class)->resolve($resource);
        } catch (ResourceSlugNotRegisteredException) {
            abort(404);
        }

        $this->resourceSlug = $resource;
        $this->resource = $resourceClass;

        if (! $this->authorization()->canViewAny()) {
            abort(403);
        }

        $this->initializeFilterValues();
        $this->initializeColumnState();
        $this->normalizeIndexState();
    }

    public function render(): View
    {
        $records = $this->records();
        $selectionSummary = $this->selectionSummary($records);

        return view('seviaflow-ui::livewire.resource-index', [
            'columns' => $this->columns(),
            'columnSettings' => $this->columnSettings(),
            'filters' => $this->filters(),
            'activeFilterEntries' => $this->activeFilterEntries(),
            'activeFiltersCount' => $this->activeFiltersCount(),
            'records' => $records,
            'hasForm' => $this->hasForm(),
            'canCreate' => $this->canCreate(),
            'createUrl' => $this->createUrl(),
            'showUrl' => fn (Model $record): string => $this->showUrl($record),
            'editUrl' => fn (Model $record): string => $this->editUrl($record),
            'rowActions' => $this->actions(),
            'bulkActions' => $this->bulkActions(),
            'canViewRecord' => fn (Model $record): bool => $this->canViewRecord($record),
            'canUpdateRecord' => fn (Model $record): bool => $this->canUpdateRecord($record),
            'canDeleteRecord' => fn (Model $record): bool => $this->canDeleteRecord($record),
            'canRestoreRecord' => fn (Model $record): bool => $this->canRestoreRecord($record),
            'canExecuteAction' => fn (Action $action, Model $record): bool => $this->canExecuteAction($action, $record),
            'supportsSoftDeletes' => $this->supportsSoftDeletes(),
            'selectedCount' => $this->bulkSelectionState()->count($this->selectedRecords),
            'selectedVisibleCount' => $selectionSummary['selectedVisibleCount'],
            'hasPartialSelection' => $selectionSummary['hasPartialSelection'],
            'perPageOptions' => $this->perPageOptions(),
            'emptyMessage' => $this->emptyMessage(),
            'hasActiveQuery' => $this->hasActiveQuery(),
            'savedViewModified' => $this->savedViewModified(),
            'formatValue' => fn (Model $record, Column $column): string => $this->formatValue($record, $column),
            'savedViews' => $this->savedViews(),
            'canExport' => $this->canExport(),
            'exportFields' => $this->exportFields(),
            'exportFormats' => $this->exportFormats(),
            'canImport' => $this->canImport(),
            'importAllowedFields' => $this->importFields(),
            'importRequiredFields' => $this->importRequiredFields(),
        ])->layout('seviaflow-ui::components.app.layout', [
            'contentWidth' => 'wide',
        ]);
    }

    public function updatingSearch(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr(trim($this->search), 0, 200);
    }

    public function updatingPerPage(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->normalizedPerPage($this->perPage);
    }

    public function updatingFilterValues(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedFilterValues(): void
    {
        $this->normalizeFilterValues();
    }

    public function resetFilters(): void
    {
        $this->filterValues = $this->defaultFilterValues();
        $this->clearSelection();
        $this->resetPage();
    }

    public function resetTableState(): void
    {
        $this->search = '';
        $this->sortColumn = '';
        $this->sortDirection = 'asc';
        $this->perPage = $this->normalizedPerPage((int) config('seviaflow.performance.pagination.default_per_page', 15));
        $this->trashedScope = 'active';
        $this->filterValues = $this->defaultFilterValues();
        $this->clearSelection();
        $this->resetPage();
        $this->normalizeIndexState();
    }

    public function updatingTrashedScope(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    public function updatedTrashedScope(): void
    {
        $this->trashedScope = $this->normalizedTrashedScope($this->trashedScope);
    }

    public function updatingPage(): void
    {
        $this->clearSelection();
    }

    public function updatedSelectedRecords(): void
    {
        $this->selectedRecords = $this->bulkSelectionState()->normalizeSelection($this->selectedRecords);
        $this->selectPage = $this->selectedRecords !== [] && $this->selectedRecords === $this->visibleRecordKeys();
        $this->pendingBulkActionName = null;
    }

    public function updatedSelectPage(bool $selected): void
    {
        if ($selected) {
            $this->selectAllVisible();

            return;
        }

        $this->clearSelection();
    }

    public function sortBy(string $column): void
    {
        if (! $this->isSortable($column)) {
            return;
        }

        $this->clearSelection();

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

            $this->resetPage();

            return;
        }

        $this->sortColumn = $column;
        $this->sortDirection = 'asc';
        $this->resetPage();
    }

    public function clearFilter(string $name): void
    {
        foreach ($this->filters() as $filter) {
            if ($filter->name() !== $name) {
                continue;
            }

            $this->filterValues[$name] = $filter->getDefault() ?? ($filter instanceof MultiSelectFilter ? [] : '');
            $this->normalizeFilterValues();
            $this->clearSelection();
            $this->resetPage();

            return;
        }
    }

    public function toggleColumn(string $name): void
    {
        if (! in_array($name, $this->allowedColumnNames(), true)) {
            return;
        }

        if (in_array($name, $this->visibleColumnNames, true)) {
            if (count($this->visibleColumnNames) <= 1) {
                $this->errorMessage = 'Keep at least one table column visible.';

                return;
            }

            $this->visibleColumnNames = array_values(array_filter(
                $this->visibleColumnNames,
                static fn (string $column): bool => $column !== $name
            ));
        } else {
            $this->visibleColumnNames[] = $name;
        }

        $this->normalizeColumnState();
    }

    public function moveColumnUp(string $name): void
    {
        $this->moveColumn($name, -1);
    }

    public function moveColumnDown(string $name): void
    {
        $this->moveColumn($name, 1);
    }

    public function openSaveViewForm(): void
    {
        $this->showSaveViewForm = true;
    }

    public function cancelSaveView(): void
    {
        $this->showSaveViewForm = false;
        $this->savedViewName = '';
    }

    public function saveCurrentView(): void
    {
        $this->clearMessages();
        $result = $this->savedViewActionRunner()->createPrivate(
            user: $this->authenticatedUser(),
            resourceSlug: $this->resourceSlug,
            name: $this->savedViewName,
            state: $this->currentSavedViewState(),
        );
        if (! $result['ok']) {
            $this->errorMessage = $result['message'];

            return;
        }
        /** @var SavedView $savedView */
        $savedView = $result['saved_view'];

        $this->savedViewName = '';
        $this->showSaveViewForm = false;
        $this->selectedSavedViewId = (string) $savedView->id;
        $this->statusMessage = $result['message'];
    }

    public function applySavedView(string $savedViewId): void
    {
        $this->clearMessages();
        $savedView = $this->findOwnedSavedView($savedViewId);

        if (! $savedView instanceof SavedView) {
            $this->errorMessage = 'Saved view not found.';

            return;
        }

        /** @var array<string, mixed> $state */
        $state = $savedView->state;
        $this->initializeFilterValues();
        $applied = $this->savedViewStateManager()->applyState(
            state: $state,
            currentFilterValues: $this->filterValues,
            allowedColumns: $this->allowedColumnNames(),
            defaultColumns: $this->defaultColumnNames(),
            sortableColumns: $this->sortableColumnNames(),
            supportsTrash: $this->supportsSoftDeletes(),
        );
        $this->search = $applied['search'];
        $this->filterValues = $applied['filter_values'];
        $this->sortColumn = $applied['sort_column'];
        $this->sortDirection = $applied['sort_direction'];
        if (is_int($applied['per_page'])) {
            $this->perPage = $this->normalizedPerPage($applied['per_page']);
        }
        $this->visibleColumnNames = $applied['columns'];
        $this->trashedScope = $applied['trashed_scope'];

        $this->normalizeFilterValues();
        $this->normalizeIndexState();
        $this->clearSelection();
        $this->resetPage();
        $this->selectedSavedViewId = (string) $savedView->id;
        $this->statusMessage = 'Saved view applied.';
    }

    public function deleteSavedView(string $savedViewId): void
    {
        $this->clearMessages();
        $savedView = $this->findOwnedSavedView($savedViewId);
        $result = $this->savedViewActionRunner()->deleteOwned($savedView, $this->authenticatedUser());
        if (! $result['ok']) {
            $this->errorMessage = $result['message'];

            return;
        }

        if ($this->selectedSavedViewId === (string) $savedView->id) {
            $this->selectedSavedViewId = null;
        }

        $this->statusMessage = $result['message'];
    }

    public function openExportModal(): void
    {
        if (! $this->canExport()) {
            return;
        }

        $state = $this->exportModalState()->open($this->exportFields(), $this->exportFormats());
        $this->showExportModal = $state['show_export_modal'];
        $this->selectedExportFields = $state['selected_export_fields'];
        $this->selectedExportFormat = $state['selected_export_format'];
        $this->exportScope = $state['export_scope'];
    }

    public function closeExportModal(): void
    {
        $this->showExportModal = $this->exportModalState()->close()['show_export_modal'];
    }

    public function selectAllExportFields(): void
    {
        $this->selectedExportFields = $this->exportModalState()->selectAllFields($this->exportFields());
    }

    public function exportRecords(): ?StreamedResponse
    {
        $this->clearMessages();

        if (! $this->canExport()) {
            $this->errorMessage = 'Export is not available for this resource.';

            return null;
        }

        $validation = $this->exportRequestValidator()->validate(
            selectedExportFields: $this->selectedExportFields,
            allowedFieldNames: array_map(
                static fn (ExportField $field): string => $field->name(),
                $this->exportFields()
            ),
            allowedFormats: $this->exportFormats(),
            selectedExportFormat: $this->selectedExportFormat,
            exportScope: $this->exportScope,
            selectedRecords: $this->selectedRecords
        );
        if (! $validation['ok']) {
            $this->errorMessage = $validation['message'];

            return null;
        }
        $selected = $validation['selected_fields'];

        $rows = $this->exportRecordsRows($selected);
        $run = $this->exportRunner()->run(
            resourceSlug: $this->resourceSlug,
            headings: $this->exportHeadings($selected),
            rows: $rows,
            maxRows: max(1, (int) config('seviaflow.export.max_rows', 5000))
        );
        if ($run['response'] === null) {
            $this->errorMessage = $run['error'];

            return null;
        }

        $this->showExportModal = false;

        return $run['response'];
    }

    public function openImportModal(): void
    {
        if (! $this->canImport()) {
            return;
        }

        $state = $this->importModalState()->open();
        $this->showImportModal = $state['show_import_modal'];
        $this->importFile = $state['import_file'];
        $this->importPreviewHeaders = $state['import_preview_headers'];
        $this->importPreviewRows = $state['import_preview_rows'];
        $this->importPreviewErrors = $state['import_preview_errors'];
    }

    public function closeImportModal(): void
    {
        $state = $this->importModalState()->close();
        $this->showImportModal = $state['show_import_modal'];
        $this->importFile = $state['import_file'];
        $this->importPreviewHeaders = $state['import_preview_headers'];
        $this->importPreviewRows = $state['import_preview_rows'];
        $this->importPreviewErrors = $state['import_preview_errors'];
    }

    public function downloadImportTemplate(): ?StreamedResponse
    {
        $this->clearMessages();

        if (! $this->canImport()) {
            $this->errorMessage = 'Import is not available for this resource.';

            return null;
        }

        $headings = $this->importFields();

        if ($headings === []) {
            $this->errorMessage = 'This resource does not define import fields.';

            return null;
        }

        return (new CsvExporter)->download(
            resourceSlug: $this->resourceSlug,
            headings: $headings,
            rows: [],
            kind: 'import-template'
        );
    }

    public function previewImport(): void
    {
        $this->clearMessages();
        $this->clearImportPreview();

        if (! $this->canImport()) {
            $this->errorMessage = 'Import is not available for this resource.';

            return;
        }

        $this->validate([
            'importFile' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel', 'max:2048'],
        ]);

        $preview = $this->importPreviewRunner()->run(
            importFile: $this->importFile,
            allowedFields: $this->importFields(),
            requiredFields: $this->importRequiredFields(),
            rowLimit: max(1, (int) config('seviaflow.import.preview_rows', 20)),
        );
        if (! $preview['ok'] || $preview['preview'] === null) {
            $this->errorMessage = $preview['message'];

            return;
        }

        $this->importPreviewHeaders = $preview['preview']['headers'];
        $this->importPreviewRows = $preview['preview']['rows'];
        $this->importPreviewErrors = $preview['preview']['errors'];
    }

    public function executeImport(): void
    {
        $this->clearMessages();

        if (! $this->canImport()) {
            $this->errorMessage = 'Import is not available for this resource.';

            return;
        }

        $this->validate([
            'importFile' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel', 'max:2048'],
        ]);

        $run = $this->importExecutionRunner()->run(
            importFile: $this->importFile,
            resourceClass: $this->resource,
            import: $this->importContext()->definition($this->resource),
            maxRows: max(1, (int) config('seviaflow.import.max_rows', 1000)),
            user: auth()->user(),
            request: request(),
        );
        if ($run['result'] === null) {
            $this->errorMessage = $run['message'];

            return;
        }
        $result = $run['result'];

        $this->importPreviewErrors = array_merge($result['global_errors'], $result['row_errors']);

        if (! $run['ok']) {
            $this->errorMessage = $run['message'];

            return;
        }

        $this->statusMessage = $run['message'];
    }

    public function deleteRecord(int|string $recordId): void
    {
        $this->clearMessages();

        $record = $this->tableQuery()->find($recordId);

        if (! $record instanceof Model) {
            $this->statusMessage = 'The selected record could not be found.';

            return;
        }

        if (! $this->canDeleteRecord($record)) {
            abort(403);
        }

        try {
            DB::transaction(function () use ($record): void {
                $this->dispatchResourceEvent(ResourceEventContext::OPERATION_DELETING, $record, ResourceEventContext::SOURCE_INDEX);
                $record->delete();
                $this->dispatchResourceEvent(ResourceEventContext::OPERATION_DELETED, $record, ResourceEventContext::SOURCE_INDEX);
            });

            $this->statusMessage = $this->supportsSoftDeletes()
                ? 'Record moved to trash successfully.'
                : 'Record deleted successfully.';
            $this->removeSelectionForRecord($record);
            $this->resetPage();
        } catch (\InvalidArgumentException|\LogicException $exception) {
            $this->errorMessage = $exception->getMessage();
        } catch (\Throwable $exception) {
            report($exception);
            $this->errorMessage = 'Delete failed. Please try again.';
        }
    }

    public function restoreRecord(int|string $recordId): void
    {
        $this->clearMessages();

        if (! $this->supportsSoftDeletes()) {
            $this->errorMessage = 'Restore is not available for this resource.';

            return;
        }

        $record = $this->tableQuery()->find($recordId);

        if (! $record instanceof Model) {
            $this->errorMessage = 'The selected record could not be found.';

            return;
        }

        if (! method_exists($record, 'trashed') || ! $record->trashed()) {
            $this->errorMessage = 'Only trashed records can be restored.';

            return;
        }

        if (! $this->canRestoreRecord($record)) {
            abort(403);
        }

        try {
            DB::transaction(function () use ($record): void {
                $this->dispatchResourceEvent(ResourceEventContext::OPERATION_RESTORING, $record, ResourceEventContext::SOURCE_INDEX);
                if (! method_exists($record, 'restore')) {
                    throw new \LogicException('Restore is not available for this resource.');
                }

                $record->restore();
                $this->dispatchResourceEvent(ResourceEventContext::OPERATION_RESTORED, $record, ResourceEventContext::SOURCE_INDEX);
            });

            $this->statusMessage = 'Record restored successfully.';
            $this->removeSelectionForRecord($record);
            $this->resetPage();
        } catch (\InvalidArgumentException|\LogicException $exception) {
            $this->errorMessage = $exception->getMessage();
        } catch (\Throwable $exception) {
            report($exception);
            $this->errorMessage = 'Restore failed. Please try again.';
        }
    }

    public function selectAllVisible(): void
    {
        $state = $this->bulkSelectionState()->selectAllVisible($this->visibleRecordKeys());
        $this->selectedRecords = $state['selectedRecords'];
        $this->selectPage = $state['selectPage'];
    }

    public function clearSelection(): void
    {
        $this->selectedRecords = [];
        $this->selectPage = false;
        $this->pendingBulkActionName = null;
    }

    public function executeBulkAction(string $actionName, bool $confirmed = false): void
    {
        $this->clearMessages();
        $internalError = null;
        $result = $this->bulkActionRunner()->run(
            actions: $this->bulkActions(),
            actionName: $actionName,
            records: $this->selectedRecordModels(),
            selectedRecords: $this->selectedRecords,
            confirmed: $confirmed,
            maxByConfig: $this->maxSelectedByConfig(),
            canExecuteBulkAction: fn (BulkAction $action, Collection $records): bool => $this->canExecuteBulkAction($action, $records),
            isBuiltInBulkDelete: fn (BulkAction $action): bool => $this->isBuiltInBulkDelete($action),
            isBuiltInBulkRestore: fn (BulkAction $action): bool => $this->isBuiltInBulkRestore($action),
            runBuiltInBulkDelete: function (Collection $records) use (&$internalError): void {
                $this->runBuiltInBulkDelete($records);
                $internalError = $this->errorMessage;
            },
            runBuiltInBulkRestore: function (Collection $records) use (&$internalError): void {
                $this->runBuiltInBulkRestore($records);
                $internalError = $this->errorMessage;
            },
            internalError: $internalError,
        );

        $this->statusMessage = $result['statusMessage'];
        $this->errorMessage = $result['errorMessage'];
        $this->pendingBulkActionName = $result['pendingBulkActionName'];

        if ($result['shouldClearSelection']) {
            $this->clearSelection();
        }

        if ($result['shouldResetPage']) {
            $this->resetPage();
        }
    }

    public function executeAction(int|string $recordId, string $actionName, bool $confirmed = false): void
    {
        $this->clearMessages();
        $record = $this->tableQuery()->find($recordId);
        $action = $this->resolveAction($actionName);
        $canExecute = $action instanceof Action && $record instanceof Model
            ? $this->canExecuteAction($action, $record)
            : false;

        $result = $this->rowActionRunner()->run(
            actions: $this->actions(),
            record: $record,
            recordId: $recordId,
            actionName: $actionName,
            confirmed: $confirmed,
            canExecute: $canExecute
        );

        if ($result['shouldAbortForbidden']) {
            abort(403);
        }
        $this->statusMessage = $result['statusMessage'];
        $this->errorMessage = $result['errorMessage'];
        $this->pendingActionName = $result['pendingActionName'];
        $this->pendingActionRecordId = $result['pendingActionRecordId'];
    }

    /**
     * @return array<int, Column>
     */
    private function columns(): array
    {
        $columnsByName = [];
        foreach ($this->allColumns() as $column) {
            $columnsByName[$column->name()] = $column;
        }

        $columns = [];
        foreach ($this->visibleColumnNames as $name) {
            if (isset($columnsByName[$name])) {
                $columns[] = $columnsByName[$name];
            }
        }

        return $columns === [] ? $this->allColumns() : $columns;
    }

    /**
     * @return array<int, Column>
     */
    private function allColumns(): array
    {
        return array_values(array_filter(
            $this->table()->getColumns(),
            static fn (Column $column): bool => $column->isVisible(ResourceContext::Index)
        ));
    }

    private function records(): LengthAwarePaginator|Paginator
    {
        $this->perPage = $this->normalizedPerPage($this->perPage);

        return $this->tableQuery()
            ->search($this->search)
            ->sort($this->sortColumn, $this->sortDirection)
            ->filters($this->filterValues)
            ->trashedScope($this->trashedScope)
            ->paginate($this->perPage);
    }

    private function table(): Table
    {
        return $this->resource::table(new Table);
    }

    /**
     * @return array<int, Filter>
     */
    private function filters(): array
    {
        return $this->table()->getFilters();
    }

    private function tableQuery(): ResourceTableQuery
    {
        return ResourceTableQuery::make($this->resource, $this->table())
            ->trashedScope($this->trashedScope);
    }

    private function isSortable(string $column): bool
    {
        if ($column === '') {
            return false;
        }

        foreach ($this->allColumns() as $configuredColumn) {
            if ($configuredColumn->name() === $column && $configuredColumn->isSortable()) {
                return true;
            }
        }

        return false;
    }

    private function initializeFilterValues(): void
    {
        $existing = $this->filterValues;
        $this->filterValues = $this->defaultFilterValues();

        foreach (array_keys($this->filterValues) as $name) {
            if (array_key_exists($name, $existing)) {
                $this->filterValues[$name] = $existing[$name];
            }
        }

        $this->normalizeFilterValues();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFilterValues(): array
    {
        $values = [];

        foreach ($this->filters() as $filter) {
            $values[$filter->name()] = $filter->getDefault()
                ?? ($filter instanceof MultiSelectFilter ? [] : '');
        }

        return $values;
    }

    private function activeFiltersCount(): int
    {
        $count = 0;

        foreach ($this->filters() as $filter) {
            $value = $this->filterValues[$filter->name()] ?? null;

            if ($filter->isActive($value)) {
                $count++;
            }
        }

        return $count;
    }

    private function normalizeFilterValues(): void
    {
        foreach ($this->filters() as $filter) {
            $name = $filter->name();
            $value = $this->filterValues[$name] ?? null;

            if ($filter instanceof SelectFilter) {
                $this->filterValues[$name] = is_scalar($value) && array_key_exists((string) $value, $filter->getOptions())
                    ? (string) $value
                    : '';

                continue;
            }

            if ($filter instanceof MultiSelectFilter) {
                if (! is_array($value)) {
                    $this->filterValues[$name] = [];

                    continue;
                }

                $valid = [];

                foreach ($value as $selected) {
                    if (! is_scalar($selected)) {
                        continue;
                    }

                    $normalized = (string) $selected;

                    if (array_key_exists($normalized, $filter->getOptions())) {
                        $valid[] = $normalized;
                    }
                }

                $this->filterValues[$name] = array_values(array_unique($valid));

                continue;
            }

            if ($filter instanceof BooleanFilter) {
                if ($value === '' || $value === null) {
                    $this->filterValues[$name] = '';

                    continue;
                }

                $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $this->filterValues[$name] = is_bool($parsed) ? ($parsed ? '1' : '0') : '';

                continue;
            }

            if ($filter instanceof BelongsToFilter) {
                $this->filterValues[$name] = is_scalar($value) ? (string) $value : '';
            }
        }
    }

    /**
     * @return array<int, array{name:string, label:string, value:string}>
     */
    private function activeFilterEntries(): array
    {
        $entries = [];

        foreach ($this->filters() as $filter) {
            $value = $this->filterValues[$filter->name()] ?? null;

            if (! $filter->isActive($value)) {
                continue;
            }

            $entries[] = [
                'name' => $filter->name(),
                'label' => $filter->getLabel(),
                'value' => $this->filterDisplayValue($filter, $value),
            ];
        }

        return $entries;
    }

    private function filterDisplayValue(Filter $filter, mixed $value): string
    {
        $values = is_array($value) ? $value : [$value];
        $options = [];

        if ($filter instanceof SelectFilter || $filter instanceof MultiSelectFilter) {
            $options = $filter->getOptions();
        } elseif ($filter instanceof BelongsToFilter) {
            $options = $filter->getOptionsFor($this->resource::model());
        }

        $labels = [];
        foreach ($values as $key => $item) {
            if (is_array($item)) {
                $parts = [];
                foreach ($item as $nestedKey => $nestedValue) {
                    if ($nestedValue !== null && $nestedValue !== '') {
                        $parts[] = $nestedKey.': '.(string) $nestedValue;
                    }
                }
                if ($parts !== []) {
                    $labels[] = implode(', ', $parts);
                }

                continue;
            }

            if (is_scalar($item)) {
                $labels[] = (string) ($options[(string) $item] ?? $item);
            }
        }

        return implode(', ', $labels);
    }

    private function initializeColumnState(): void
    {
        $allowed = $this->allowedColumnNames();
        $this->visibleColumnNames = $this->visibleColumnNames === []
            ? $allowed
            : $this->visibleColumnNames;
        $this->normalizeColumnState();
    }

    private function normalizeColumnState(): void
    {
        $allowed = $this->allowedColumnNames();
        $normalized = [];

        foreach ($this->visibleColumnNames as $name) {
            if (is_string($name) && in_array($name, $allowed, true) && ! in_array($name, $normalized, true)) {
                $normalized[] = $name;
            }
        }

        $this->visibleColumnNames = $normalized === [] ? $this->defaultColumnNames() : $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function allowedColumnNames(): array
    {
        return array_values(array_map(
            static fn (Column $column): string => $column->name(),
            $this->allColumns()
        ));
    }

    /**
     * @return array<int, string>
     */
    private function defaultColumnNames(): array
    {
        return $this->allowedColumnNames();
    }

    /**
     * @return array<int, string>
     */
    private function sortableColumnNames(): array
    {
        return array_values(array_map(
            static fn (Column $column): string => $column->name(),
            array_values(array_filter($this->allColumns(), static fn (Column $column): bool => $column->isSortable()))
        ));
    }

    /**
     * @return array<int, array{name:string, label:string, visible:bool, position:int, can_move_up:bool, can_move_down:bool}>
     */
    private function columnSettings(): array
    {
        $columns = [];
        foreach ($this->allColumns() as $column) {
            $position = array_search($column->name(), $this->visibleColumnNames, true);
            $columns[] = [
                'name' => $column->name(),
                'label' => $column->getLabel(),
                'visible' => $position !== false,
                'position' => $position === false ? count($this->visibleColumnNames) : $position,
                'can_move_up' => $position !== false && $position > 0,
                'can_move_down' => $position !== false && $position < count($this->visibleColumnNames) - 1,
            ];
        }

        usort($columns, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        return $columns;
    }

    private function moveColumn(string $name, int $offset): void
    {
        $position = array_search($name, $this->visibleColumnNames, true);
        if ($position === false) {
            return;
        }

        $target = $position + $offset;
        if ($target < 0 || $target >= count($this->visibleColumnNames)) {
            return;
        }

        [$this->visibleColumnNames[$position], $this->visibleColumnNames[$target]] = [
            $this->visibleColumnNames[$target],
            $this->visibleColumnNames[$position],
        ];
    }

    private function normalizeIndexState(): void
    {
        $this->search = mb_substr(trim($this->search), 0, 200);
        $this->sortDirection = in_array(strtolower($this->sortDirection), ['asc', 'desc'], true)
            ? strtolower($this->sortDirection)
            : 'asc';
        $this->sortColumn = $this->isSortable($this->sortColumn) ? $this->sortColumn : '';
        $this->perPage = $this->normalizedPerPage($this->perPage);
        $this->trashedScope = $this->normalizedTrashedScope($this->trashedScope);
        $this->normalizeColumnState();
        $this->normalizeFilterValues();
    }

    private function normalizedTrashedScope(string $scope): string
    {
        if (! $this->supportsSoftDeletes()) {
            return 'active';
        }

        return in_array($scope, ['active', 'trashed', 'with_trashed'], true) ? $scope : 'active';
    }

    private function emptyMessage(): string
    {
        return $this->hasActiveQuery() ? 'No matching records found.' : 'No records to display.';
    }

    private function hasActiveQuery(): bool
    {
        return trim($this->search) !== ''
            || $this->activeFiltersCount() > 0
            || $this->sortColumn !== ''
            || $this->trashedScope !== 'active';
    }

    private function formatValue(Model $record, Column $column): string
    {
        return $this->tableValueFormatter()->format($record, $column);
    }

    private function hasForm(): bool
    {
        return $this->resource::form(new Form)->getAllFields() !== [];
    }

    private function canCreate(): bool
    {
        return $this->tableAuthorizationState()->canCreate();
    }

    /**
     * @return array<int, Action>
     */
    private function actions(): array
    {
        return $this->resource::actions();
    }

    /**
     * @return array<int, BulkAction>
     */
    private function bulkActions(): array
    {
        $actions = $this->resource::bulkActions();

        if ($this->supportsSoftDeletes()) {
            $actions[] = BulkAction::make(self::BUILT_IN_BULK_DELETE)
                ->label('Move selected to trash')
                ->mutating()
                ->danger()
                ->confirmButton('Move selected to trash')
                ->cancelButton('Cancel')
                ->successMessage('Selected records were moved to trash.');

            if ($this->trashedScope !== 'active') {
                $actions[] = BulkAction::make(self::BUILT_IN_BULK_RESTORE)
                    ->label('Restore selected')
                    ->mutating()
                    ->requiresConfirmation()
                    ->confirmButton('Restore selected records')
                    ->cancelButton('Cancel')
                    ->successMessage('Selected records were restored.');
            }

            return $actions;
        }

        $actions[] = BulkAction::make(self::BUILT_IN_BULK_DELETE)
            ->label('Delete selected')
            ->mutating()
            ->danger()
            ->confirmButton('Delete selected records')
            ->cancelButton('Cancel')
            ->successMessage('Selected records were deleted.');

        return $actions;
    }

    private function createUrl(): string
    {
        return $this->tableUrlFactory()->createUrl($this->resourceSlug);
    }

    private function editUrl(Model $record): string
    {
        return $this->tableUrlFactory()->editUrl($this->resourceSlug, $record, $this->currentListContext());
    }

    private function showUrl(Model $record): string
    {
        return $this->tableUrlFactory()->showUrl($this->resourceSlug, $record, $this->currentListContext());
    }

    /**
     * @return array<int, int>
     */
    private function perPageOptions(): array
    {
        $configured = config('seviaflow.performance.pagination.per_page_options', [15, 25, 50]);
        $max = max(1, (int) config('seviaflow.performance.pagination.max_per_page', 100));
        $options = [];

        foreach ((array) $configured as $value) {
            if (is_numeric($value)) {
                $intValue = (int) $value;

                if ($intValue >= 1 && $intValue <= $max) {
                    $options[] = $intValue;
                }
            }
        }

        $options = array_values(array_unique($options));

        return $options === [] ? [15, 25, 50] : $options;
    }

    private function normalizedPerPage(int $value): int
    {
        $max = max(1, (int) config('seviaflow.performance.pagination.max_per_page', 100));
        $default = (int) config('seviaflow.performance.pagination.default_per_page', 15);
        $options = $this->perPageOptions();

        if ($value < 1) {
            $value = $default;
        }

        if (! in_array($value, $options, true)) {
            $value = in_array($default, $options, true) ? $default : $options[0];
        }

        return min($value, $max);
    }

    private function canViewRecord(Model $record): bool
    {
        return $this->tableAuthorizationState()->canView($record);
    }

    private function canUpdateRecord(Model $record): bool
    {
        return $this->tableAuthorizationState()->canUpdate($record);
    }

    private function canDeleteRecord(Model $record): bool
    {
        return $this->tableAuthorizationState()->canDelete($record);
    }

    private function canExecuteAction(Action $action, Model $record): bool
    {
        return $this->tableAuthorizationState()->canExecuteAction($action, $record);
    }

    private function canRestoreRecord(Model $record): bool
    {
        return $this->tableAuthorizationState()->canRestore($record);
    }

    private function resolveAction(string $name): ?Action
    {
        foreach ($this->actions() as $action) {
            if ($action->name() === $name) {
                return $action;
            }
        }

        return null;
    }

    private function isBuiltInBulkDelete(BulkAction $action): bool
    {
        return $action->name() === self::BUILT_IN_BULK_DELETE;
    }

    private function isBuiltInBulkRestore(BulkAction $action): bool
    {
        return $action->name() === self::BUILT_IN_BULK_RESTORE;
    }

    private function clearMessages(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    private function authorization(): ResourceAuthorization
    {
        return ResourceAuthorization::for($this->resource);
    }

    private function supportsSoftDeletes(): bool
    {
        $modelClass = $this->resource::model();

        return in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
    }

    /**
     * @return array<int, string>
     */
    private function visibleRecordKeys(): array
    {
        /** @var array<int, Model> $records */
        $records = $this->records()->getCollection()->all();

        return $this->bulkSelectionState()->visibleKeys($records);
    }

    /**
     * @return array{selectedVisibleCount:int, hasPartialSelection:bool}
     */
    private function selectionSummary(LengthAwarePaginator|Paginator $records): array
    {
        /** @var array<int, Model> $visibleRecords */
        $visibleRecords = $records->getCollection()->all();
        $visibleKeys = $this->bulkSelectionState()->visibleKeys($visibleRecords);
        $selectedVisibleCount = count(array_intersect($this->selectedRecords, $visibleKeys));

        return [
            'selectedVisibleCount' => $selectedVisibleCount,
            'hasPartialSelection' => $selectedVisibleCount > 0 && $selectedVisibleCount < count($visibleKeys),
        ];
    }

    private function removeSelectionForRecord(Model $record): void
    {
        $state = $this->bulkSelectionState()->removeRecord(
            selectedRecords: $this->selectedRecords,
            recordKey: (string) $record->getKey(),
            visibleKeys: $this->visibleRecordKeys()
        );
        $this->selectedRecords = $state['selectedRecords'];
        $this->selectPage = $state['selectPage'];
    }

    /**
     * @return Collection<int, Model>
     */
    private function selectedRecordModels(): Collection
    {
        if ($this->selectedRecords === []) {
            return collect();
        }

        $records = $this->resourceScopeQuery()
            ->whereKey($this->selectedRecords)
            ->get()
            ->keyBy(static fn (Model $record): string => (string) $record->getKey());

        $ordered = [];
        foreach ($this->selectedRecords as $key) {
            $record = $records->get($key);
            if ($record instanceof Model) {
                $ordered[] = $record;
            }
        }

        return collect($ordered);
    }

    private function resourceScopeQuery(): Builder
    {
        $modelClass = $this->resource::model();
        /** @var Model $model */
        $model = new $modelClass;

        return ResourceScopedQuery::make(
            $this->resource,
            $model->newQuery(),
            $this->trashedScope
        );
    }

    private function maxSelectedByConfig(): ?int
    {
        $value = config('seviaflow-ui.bulk_actions.max_selected');

        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        $max = (int) $value;

        return $max >= 1 ? $max : null;
    }

    /**
     * @param  Collection<int, Model>  $records
     */
    private function runBuiltInBulkDelete(Collection $records): void
    {
        foreach ($records as $record) {
            if (! $this->canDeleteRecord($record)) {
                $this->errorMessage = 'You are not authorized to delete one or more selected records.';

                return;
            }
        }

        try {
            DB::transaction(function () use ($records): void {
                foreach ($records as $record) {
                    $this->dispatchResourceEvent(ResourceEventContext::OPERATION_DELETING, $record, ResourceEventContext::SOURCE_BULK_ACTION);
                    $record->delete();
                    $this->dispatchResourceEvent(ResourceEventContext::OPERATION_DELETED, $record, ResourceEventContext::SOURCE_BULK_ACTION);
                }
            });
        } catch (\InvalidArgumentException|\LogicException $exception) {
            $this->errorMessage = $exception->getMessage();
        } catch (\Throwable $exception) {
            report($exception);
            $this->errorMessage = 'Delete failed. Please try again.';
        }
    }

    /**
     * @param  Collection<int, Model>  $records
     */
    private function runBuiltInBulkRestore(Collection $records): void
    {
        if (! $this->supportsSoftDeletes()) {
            $this->errorMessage = 'Restore is not available for this resource.';

            return;
        }

        foreach ($records as $record) {
            if (! method_exists($record, 'trashed') || ! $record->trashed()) {
                $this->errorMessage = 'Only trashed records can be restored.';

                return;
            }

            if (! $this->canRestoreRecord($record)) {
                $this->errorMessage = 'You are not authorized to restore one or more selected records.';

                return;
            }
        }

        try {
            DB::transaction(function () use ($records): void {
                foreach ($records as $record) {
                    $this->dispatchResourceEvent(ResourceEventContext::OPERATION_RESTORING, $record, ResourceEventContext::SOURCE_BULK_ACTION);
                    if (! method_exists($record, 'restore')) {
                        throw new \LogicException('Restore is not available for this resource.');
                    }

                    $record->restore();
                    $this->dispatchResourceEvent(ResourceEventContext::OPERATION_RESTORED, $record, ResourceEventContext::SOURCE_BULK_ACTION);
                }
            });
        } catch (\InvalidArgumentException|\LogicException $exception) {
            $this->errorMessage = $exception->getMessage();
        } catch (\Throwable $exception) {
            report($exception);
            $this->errorMessage = 'Restore failed. Please try again.';
        }
    }

    private function dispatchResourceEvent(string $event, Model $record, string $source): void
    {
        $original = $record->getOriginal();
        $changes = match ($event) {
            ResourceEventContext::OPERATION_DELETING,
            ResourceEventContext::OPERATION_DELETED => ['deleted' => true],
            ResourceEventContext::OPERATION_RESTORING,
            ResourceEventContext::OPERATION_RESTORED => ['restored' => true],
            default => [],
        };

        $this->resourceEventDispatcher()->dispatch(
            resourceClass: $this->resource,
            event: $event,
            record: $record,
            context: new ResourceEventContext(
                user: Auth::user(),
                resourceClass: $this->resource,
                resourceSlug: $this->resourceSlug,
                operation: $event,
                source: $source,
                request: request(),
                original: is_array($original) ? $original : [],
                changes: $changes,
                metadata: []
            )
        );
    }

    /**
     * @param  Collection<int, Model>  $records
     */
    private function canExecuteBulkAction(BulkAction $action, Collection $records): bool
    {
        if ($this->isBuiltInBulkDelete($action) || $this->isBuiltInBulkRestore($action)) {
            return true;
        }

        $user = Auth::user();
        $authorization = $this->authorization();

        foreach ($records as $record) {
            $allowed = $action->isReadOnly()
                ? $authorization->canView($user, $record)
                : $authorization->canUpdate($user, $record);

            if (! $allowed) {
                return false;
            }
        }

        return $action->canExecute($user, $records);
    }

    private function bulkSelectionState(): BulkSelectionState
    {
        return new BulkSelectionState;
    }

    private function bulkActionRunner(): BulkActionRunner
    {
        return new BulkActionRunner;
    }

    private function rowActionRunner(): RowActionRunner
    {
        return new RowActionRunner;
    }

    private function resourceEventDispatcher(): ResourceEventDispatcher
    {
        return new ResourceEventDispatcher;
    }

    private function tableUrlFactory(): TableUrlFactory
    {
        return new TableUrlFactory;
    }

    private function tableValueFormatter(): TableValueFormatter
    {
        return new TableValueFormatter;
    }

    private function tableAuthorizationState(): TableAuthorizationState
    {
        return new TableAuthorizationState($this->authorization(), $this->supportsSoftDeletes());
    }

    /**
     * @return Collection<int, SavedView>
     */
    private function savedViews(): Collection
    {
        $user = $this->authenticatedUser();
        if (! $user instanceof Model) {
            return collect();
        }

        return $this->savedViewRepository()->forResourceAndUser($this->resourceSlug, $user);
    }

    private function findOwnedSavedView(string $savedViewId): ?SavedView
    {
        $id = trim($savedViewId);
        if ($id === '') {
            return null;
        }

        return $this->savedViews()
            ->first(static fn (SavedView $savedView): bool => (string) $savedView->id === $id);
    }

    private function currentSavedViewState(): array
    {
        return $this->savedViewStateManager()->collectCurrentState(
            search: $this->search,
            filterValues: $this->filterValues,
            sortColumn: $this->sortColumn,
            sortDirection: $this->sortDirection,
            perPage: $this->normalizedPerPage($this->perPage),
            columns: $this->visibleColumnNames,
            trashedScope: $this->trashedScope,
        );
    }

    private function savedViewModified(): bool
    {
        if ($this->selectedSavedViewId === null || $this->selectedSavedViewId === '') {
            return false;
        }

        $savedView = $this->findOwnedSavedView($this->selectedSavedViewId);
        if (! $savedView instanceof SavedView) {
            return false;
        }

        return $this->savedViewStateManager()->isModified(
            currentState: $this->currentSavedViewState(),
            savedState: $savedView->state,
            defaultFilterValues: $this->defaultFilterValues(),
            allowedColumns: $this->allowedColumnNames(),
            defaultColumns: $this->defaultColumnNames(),
            sortableColumns: $this->sortableColumnNames(),
            supportsTrash: $this->supportsSoftDeletes(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function currentListContext(): array
    {
        $context = [
            'search' => $this->search,
            'filter' => $this->filterValues,
            'sort' => $this->sortColumn,
            'direction' => $this->sortColumn === '' ? null : $this->sortDirection,
            'per_page' => $this->normalizedPerPage($this->perPage),
            'trash' => $this->trashedScope,
            'page' => $this->getPage(),
        ];

        return (new TableUrlFactory)->contextFromQuery($context);
    }

    private function authenticatedUser(): ?Model
    {
        $user = Auth::user();

        return $user instanceof Model ? $user : null;
    }

    private function savedViewRepository(): SavedViewRepository
    {
        return new SavedViewRepository;
    }

    private function canExport(): bool
    {
        return $this->exportContext()->canExport($this->resource, $this->authorization()->canViewAny());
    }

    /**
     * @return array<int, ExportField>
     */
    private function exportFields(): array
    {
        return $this->exportContext()->fields($this->resource);
    }

    /**
     * @return array<int, string>
     */
    private function exportFormats(): array
    {
        return $this->exportContext()->formats($this->resource);
    }

    private function canImport(): bool
    {
        return $this->importContext()->canImport($this->resource, $this->authorization()->canCreate());
    }

    /**
     * @return array<int, string>
     */
    private function importFields(): array
    {
        return $this->importContext()->fields($this->resource);
    }

    /**
     * @return array<int, string>
     */
    private function importRequiredFields(): array
    {
        return $this->importContext()->requiredFields($this->resource);
    }

    /**
     * @param  array<int, string>  $selectedFieldNames
     * @return array<int, string>
     */
    private function exportHeadings(array $selectedFieldNames): array
    {
        return $this->exportContext()->headings($this->resource, $selectedFieldNames);
    }

    /**
     * @param  array<int, string>  $selectedFieldNames
     * @return array<int, array<int, mixed>>
     */
    private function exportRecordsRows(array $selectedFieldNames): array
    {
        $maxRows = max(1, (int) config('seviaflow.export.max_rows', 5000));
        $currentViewRecords = $this->exportContext()->currentViewRecords(
            tableQuery: $this->tableQuery(),
            search: $this->search,
            sortColumn: $this->sortColumn,
            sortDirection: $this->sortDirection,
            filterValues: $this->filterValues,
            trashedScope: $this->trashedScope,
            maxRows: $maxRows
        );

        return $this->exportContext()->rows(
            selectedFieldNames: $selectedFieldNames,
            exportScope: $this->exportScope,
            selectedRecordModels: $this->selectedRecordModels()->values(),
            currentViewRecords: $currentViewRecords
        );
    }

    private function clearImportPreview(): void
    {
        $state = $this->importModalState()->clearPreview();
        $this->importPreviewHeaders = $state['import_preview_headers'];
        $this->importPreviewRows = $state['import_preview_rows'];
        $this->importPreviewErrors = $state['import_preview_errors'];
    }

    private function savedViewStateManager(): SavedViewStateManager
    {
        return new SavedViewStateManager;
    }

    private function savedViewActionRunner(): SavedViewActionRunner
    {
        return new SavedViewActionRunner($this->savedViewRepository());
    }

    private function exportModalState(): ExportModalState
    {
        return new ExportModalState;
    }

    private function exportContext(): ExportContext
    {
        return new ExportContext;
    }

    private function exportRequestValidator(): ExportRequestValidator
    {
        return new ExportRequestValidator;
    }

    private function exportRunner(): ExportRunner
    {
        return new ExportRunner;
    }

    private function importModalState(): ImportModalState
    {
        return new ImportModalState;
    }

    private function importContext(): ImportContext
    {
        return new ImportContext;
    }

    private function importPreviewRunner(): ImportPreviewRunner
    {
        return new ImportPreviewRunner;
    }

    private function importExecutionRunner(): ImportExecutionRunner
    {
        return new ImportExecutionRunner;
    }
}
