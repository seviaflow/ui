<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\WithPagination;
use SeviaFlow\Core\Exceptions\ResourceSlugNotRegisteredException;
use SeviaFlow\Core\Forms\BelongsToFieldOptions;
use SeviaFlow\Core\Forms\FormFields;
use SeviaFlow\Core\Forms\FormState;
use SeviaFlow\Core\Forms\FormStateInitializer;
use SeviaFlow\Core\Forms\FormValidationRules;
use SeviaFlow\Core\Operations\ResourceOperationContext;
use SeviaFlow\Core\Operations\ResourceOperationService;
use SeviaFlow\Core\Resources\Action;
use SeviaFlow\Core\Resources\Columns\BadgeColumn;
use SeviaFlow\Core\Resources\Columns\BelongsToColumn;
use SeviaFlow\Core\Resources\Columns\BooleanColumn;
use SeviaFlow\Core\Resources\Columns\Column;
use SeviaFlow\Core\Resources\Columns\MoneyColumn;
use SeviaFlow\Core\Resources\Columns\Support\BadgeRenderer;
use SeviaFlow\Core\Resources\Fields\BelongsToField;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Fields\ImageField;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Relations\BelongsToManyRelation;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Relations\HasOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphManyRelation;
use SeviaFlow\Core\Resources\Relations\MorphOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphToManyRelation;
use SeviaFlow\Core\Resources\ResourceAuthorization;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Resources\ResourceScopedQuery;
use SeviaFlow\Core\Resources\Section;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Core\Support\SensitiveDataPolicy;
use SeviaFlow\Ui\Fields\FieldRendererRegistry;
use SeviaFlow\Ui\Index\TableUrlFactory;
use SeviaFlow\Ui\Show\Actions\ShowActionResolver;
use SeviaFlow\Ui\Show\Actions\ShowActionRunner;
use SeviaFlow\Ui\Show\Relations\BelongsToManyRelationManager;
use SeviaFlow\Ui\Show\Relations\HasManyRelationManager;
use SeviaFlow\Ui\Show\Relations\HasOneRelationManager;
use SeviaFlow\Ui\Show\Relations\MorphManyRelationManager;
use SeviaFlow\Ui\Show\Relations\MorphOneRelationManager;
use SeviaFlow\Ui\Show\Relations\MorphToManyRelationManager;
use SeviaFlow\Ui\Show\Relations\PivotFormHandler;
use SeviaFlow\Ui\Show\Relations\RelationBoundaryGuard;
use SeviaFlow\Ui\Show\Relations\RelationManagerResolver;
use SeviaFlow\Ui\Show\Relations\RelationModalFormHandler;
use SeviaFlow\Ui\Show\Relations\RelationQueryPaginator;
use SeviaFlow\Ui\Show\Workflow\WorkflowPanelState;
use SeviaFlow\Ui\Show\Workflow\WorkflowTransitionRunner;
use SeviaFlow\Ui\Support\MoneyFormatter;

final class ResourceShow extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Locked]
    public string $resourceSlug;

    /**
     * @var class-string
     */
    #[Locked]
    public string $resource;

    /**
     * @var class-string<Model>
     */
    #[Locked]
    public string $modelClass;

    /**
     * @var Model|null
     */
    #[Locked]
    public $record = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public ?string $pendingActionName = null;

    public ?string $pendingWorkflowTransitionName = null;

    public ?string $workflowTransitionComment = null;

    /**
     * @var array<string, string>
     */
    public array $attachSelection = [];

    public ?string $pendingDetachRelation = null;

    public int|string|null $pendingDetachRecordId = null;

    public ?string $pendingHasManyDeleteRelation = null;

    public int|string|null $pendingHasManyDeleteRecordId = null;

    public ?string $pendingHasOneDeleteRelation = null;

    public ?string $pivotModalRelationship = null;

    public ?string $pivotModalMode = null;

    public int|string|null $pivotModalRelatedRecordId = null;

    /**
     * @var array<string, mixed>
     */
    public array $pivotModalValues = [];

    public ?string $relationModalRelationship = null;

    public ?string $relationModalMode = null;

    public int|string|null $relationModalRecordId = null;

    /**
     * @var array<string, mixed>
     */
    public array $relationModalValues = [];

    /**
     * @var array<string, bool>
     */
    public array $relationModalPresence = [];

    /**
     * @var array<string, string>
     */
    public array $relationModalBelongsToSearch = [];

    /**
     * @var array<string, string>
     */
    public array $relationModalMultiSelectSearch = [];

    /**
     * @var array<string, string>
     */
    public array $relationModalFileActions = [];

    public ?string $relationInlineCreateFieldName = null;

    /**
     * @var array<int, string>
     */
    public array $expandedRelations = [];

    /**
     * @var array<string, mixed>
     */
    public array $relationInlineCreateValues = [];

    /**
     * @var array<string, bool>
     */
    public array $relationInlineCreatePresence = [];

    /**
     * @var array<string, string>
     */
    public array $relationInlineCreateBelongsToSearch = [];

    /**
     * @var array<string, string>
     */
    public array $relationInlineCreateMultiSelectSearch = [];

    public function mount(string $resource, int|string $record): void
    {
        try {
            $resourceClass = app(ResourceRegistry::class)->resolve($resource);
        } catch (ResourceSlugNotRegisteredException) {
            abort(404);
        }

        $this->resourceSlug = $resource;
        $this->resource = $resourceClass;
        $this->modelClass = $resourceClass::model();
        $this->record = $this->query()->find($record);

        if (! $this->record instanceof Model) {
            abort(404);
        }

        if (! $this->authorization()->canView(null, $this->record)) {
            abort(403);
        }
    }

    public function render(): View
    {
        $tableUrlFactory = new TableUrlFactory;
        $listContext = $tableUrlFactory->contextFromQuery(request()->query());

        return view('seviaflow-ui::livewire.resource-show', [
            'fields' => $this->visibleFields(),
            'sections' => $this->visibleSections(),
            'title' => $this->resourceLabel(),
            'metadata' => $this->recordMetadata(),
            'actions' => $this->authorizedActions(),
            'canEdit' => $this->canEdit(),
            'backUrl' => $tableUrlFactory->indexUrl($this->resourceSlug, $listContext),
            'editUrl' => $tableUrlFactory->editUrl($this->resourceSlug, $this->record, $listContext),
            'formatValue' => fn (Field $field): string => $this->formatFieldValue($field),
            'relationManagers' => $this->relationManagers(),
            'formatColumnValue' => fn (Model $record, Column $column): string => $this->formatColumnValue($record, $column),
            'formatPivotColumnValue' => fn (Model $record, Column $column): string => $this->formatPivotColumnValue($record, $column),
            'relationModalState' => $this->relationModalState(),
            'pivotModalState' => $this->pivotModalState(),
            'workflowPanel' => $this->workflowPanelState(),
            'workflowStateColorClasses' => fn (?string $color): string => $this->workflowStateColorClasses($color),
        ])->layout('seviaflow-ui::components.app.layout', [
            'contentWidth' => 'standard',
        ]);
    }

    public function toggleRelation(string $relationshipName): void
    {
        if (! $this->record instanceof Model) {
            return;
        }

        $relation = collect($this->relationManagerResolver()->relations($this->resource))
            ->first(static fn ($candidate): bool => $candidate->getRelationshipName() === $relationshipName);

        if ($relation === null || ! ResourceAuthorization::for($relation->getResourceClass())->canViewAny()) {
            return;
        }

        if (in_array($relationshipName, $this->expandedRelations, true)) {
            $this->expandedRelations = array_values(array_diff($this->expandedRelations, [$relationshipName]));
        } else {
            $this->expandedRelations[] = $relationshipName;
        }

        $this->resetPage($this->relationPageName($relationshipName));
    }

    public function executeWorkflowTransition(string $transitionName, bool $confirmed = false): void
    {
        $this->clearMessages();

        if (! $this->record instanceof Model) {
            $this->errorMessage = 'The selected record could not be found.';

            return;
        }

        if (! $this->authorization()->canUpdate(null, $this->record)) {
            abort(403);
        }

        $result = $this->workflowTransitionRunner()->run(
            resourceClass: $this->resource,
            record: $this->record,
            transitionName: $transitionName,
            confirmed: $confirmed,
            comment: $this->workflowTransitionComment,
            user: auth()->user()
        );

        if ($result['refreshRecord']) {
            $this->record->refresh();
        }

        if ($result['clearComment']) {
            $this->workflowTransitionComment = null;
        }

        $this->pendingWorkflowTransitionName = $result['pendingTransitionName'];
        $this->statusMessage = $result['statusMessage'];
        $this->errorMessage = $result['errorMessage'];
    }

    public function executeAction(string $actionName, bool $confirmed = false): void
    {
        $this->clearMessages();

        $result = $this->showActionRunner()->run(
            resourceClass: $this->resource,
            record: $this->record,
            actionName: $actionName,
            confirmed: $confirmed,
            user: auth()->user()
        );

        if ($result['errorMessage'] === 'forbidden') {
            abort(403);
        }

        if ($result['refreshRecord'] && $this->record instanceof Model) {
            $this->record->refresh();
        }

        $this->pendingActionName = $result['pendingActionName'];
        $this->statusMessage = $result['statusMessage'];
        $this->errorMessage = $result['errorMessage'];
    }

    public function attachBelongsToMany(string $relationshipName): void
    {
        $this->clearMessages();

        if (! $this->record instanceof Model) {
            $this->errorMessage = 'The selected record could not be found.';

            return;
        }

        if (! $this->authorization()->canUpdate(null, $this->record)) {
            abort(403);
        }

        $relation = $this->findBelongsToManyRelation($relationshipName);
        if (! $relation instanceof BelongsToManyRelation && ! $relation instanceof MorphToManyRelation) {
            $this->errorMessage = 'The selected relation is not available.';

            return;
        }

        if (! $relation->canAttach()) {
            $this->errorMessage = 'Attaching related records is not allowed for this relation.';

            return;
        }

        if ($relation->getPivotFields() !== []) {
            $this->openBelongsToManyAttachModal($relationshipName);

            return;
        }

        $selectedId = (string) ($this->attachSelection[$relationshipName] ?? '');
        if ($selectedId === '') {
            $this->errorMessage = 'Select a record to attach.';

            return;
        }

        $relatedResource = $relation->getResourceClass();
        $relatedAuthorization = ResourceAuthorization::for($relatedResource);
        $relatedRecord = $relation instanceof MorphToManyRelation
            ? $this->morphToManyRelationManager()->resolveRelatedRecord($this->record, $relation, $selectedId)
            : $this->belongsToManyRelationManager()->resolveRelatedRecord($this->record, $relation, $selectedId);

        if (! $relatedRecord instanceof Model) {
            $this->errorMessage = 'The selected record is not available for attachment.';

            return;
        }

        if (! $relatedAuthorization->canUpdate(null, $relatedRecord)) {
            abort(403);
        }

        $isAttached = $relation instanceof MorphToManyRelation
            ? $this->morphToManyRelationManager()->isAttached($this->record, $relation, $selectedId)
            : $this->belongsToManyRelationManager()->isAttached($this->record, $relation, $selectedId);

        if ($isAttached) {
            $this->errorMessage = 'The selected record is already attached.';

            return;
        }

        $availableIds = array_map(
            static fn (mixed $value): string => (string) $value,
            array_keys($this->belongsToManyAttachOptions($relation))
        );
        if (! in_array((string) $selectedId, $availableIds, true)) {
            $this->errorMessage = 'The selected record is not available for attachment.';

            return;
        }

        if ($relation instanceof MorphToManyRelation) {
            $this->morphToManyRelationManager()->attach($this->record, $relation, $selectedId);
        } else {
            $this->belongsToManyRelationManager()->attach($this->record, $relation, $selectedId);
        }
        $this->attachSelection[$relationshipName] = '';
        $this->statusMessage = 'Related record attached successfully.';
    }

    public function openBelongsToManyAttachModal(string $relationshipName): void
    {
        $relation = $this->findBelongsToManyRelation($relationshipName);
        if ((! $relation instanceof BelongsToManyRelation && ! $relation instanceof MorphToManyRelation) || ! $relation->canAttach()) {
            return;
        }

        if (! $this->authorization()->canUpdate(null, $this->record)) {
            abort(403);
        }

        $this->pivotModalRelationship = $relationshipName;
        $this->pivotModalMode = 'attach';
        $this->pivotModalRelatedRecordId = null;
        $this->pivotModalValues = $this->relationModalFormHandler()->initializeValuesFromFields($relation->getPivotFields(), null);
        $this->resetErrorBag();
    }

    public function openBelongsToManyEditPivotModal(string $relationshipName, int|string $relatedRecordId): void
    {
        $relation = $this->findBelongsToManyRelation($relationshipName);
        if (! $relation instanceof BelongsToManyRelation && ! $relation instanceof MorphToManyRelation) {
            return;
        }

        if ($relation->getPivotFields() === []) {
            return;
        }

        if (! $this->authorization()->canUpdate(null, $this->record)) {
            abort(403);
        }

        $relatedRecord = $this->resolveBelongsToManyAttachedRecord($relation, $relatedRecordId);
        if (! $relatedRecord instanceof Model) {
            $this->errorMessage = 'The selected related record is not attached.';

            return;
        }

        if (! ResourceAuthorization::for($relation->getResourceClass())->canUpdate(null, $relatedRecord)) {
            abort(403);
        }

        $this->pivotModalRelationship = $relationshipName;
        $this->pivotModalMode = 'edit';
        $this->pivotModalRelatedRecordId = (string) $relatedRecordId;
        $this->pivotModalValues = $this->pivotFormHandler()->initializeFromPivotRecord($relatedRecord, $relation->getPivotFields());
        $this->resetErrorBag();
    }

    public function cancelPivotModal(): void
    {
        $this->pivotModalRelationship = null;
        $this->pivotModalMode = null;
        $this->pivotModalRelatedRecordId = null;
        $this->pivotModalValues = [];
        $this->resetErrorBag();
    }

    public function savePivotModal(): void
    {
        $state = $this->pivotModalState();
        $relation = $state['relation'];
        $pivotFields = $state['pivotFields'];
        $relationship = $state['relationship'];

        if (! $relation instanceof BelongsToManyRelation && ! $relation instanceof MorphToManyRelation) {
            return;
        }

        if ($relationship === null) {
            return;
        }

        if (! $this->record instanceof Model) {
            $this->errorMessage = 'The selected record could not be found.';

            return;
        }

        if (! $this->authorization()->canUpdate(null, $this->record)) {
            abort(403);
        }

        $validated = $this->validate(
            $this->formValidationRules()->build($pivotFields, ResourceContext::Create, null, 'pivotModalValues', $this->pivotModalValues),
            messages: [],
            attributes: $this->formValidationRules()->attributes($pivotFields, ResourceContext::Create, 'pivotModalValues')
        );
        $values = is_array($validated['pivotModalValues'] ?? null) ? $validated['pivotModalValues'] : [];
        $safePivotValues = $this->pivotFormHandler()->valuesForPersistence($pivotFields, $values, ResourceContext::Create);

        if ($this->pivotModalMode === 'attach') {
            if (! $relation->canAttach()) {
                $this->errorMessage = 'Attaching related records is not allowed for this relation.';

                return;
            }

            $selectedId = (string) ($this->attachSelection[$relation->getRelationshipName()] ?? '');
            if ($selectedId === '') {
                $this->errorMessage = 'Select a record to attach.';

                return;
            }

            $relatedRecord = $this->resolveBelongsToManyAttachableRecord($relation, $selectedId);
            if (! $relatedRecord instanceof Model) {
                $this->errorMessage = 'The selected record is not available for attachment.';

                return;
            }

            if (! ResourceAuthorization::for($relation->getResourceClass())->canUpdate(null, $relatedRecord)) {
                abort(403);
            }

            $relationship->attach($selectedId, $safePivotValues);
            $this->attachSelection[$relation->getRelationshipName()] = '';
            $this->statusMessage = 'Related record attached successfully.';
            $this->cancelPivotModal();

            return;
        }

        $relatedRecord = $this->resolveBelongsToManyAttachedRecord($relation, (string) $this->pivotModalRelatedRecordId);
        if (! $relatedRecord instanceof Model) {
            $this->errorMessage = 'The selected related record is not attached.';

            return;
        }

        if (! ResourceAuthorization::for($relation->getResourceClass())->canUpdate(null, $relatedRecord)) {
            abort(403);
        }

        $relationship->updateExistingPivot($relatedRecord->getKey(), $safePivotValues);
        $this->statusMessage = 'Pivot values updated successfully.';
        $this->cancelPivotModal();
    }

    public function detachBelongsToMany(string $relationshipName, int|string $relatedRecordId, bool $confirmed = false): void
    {
        $this->clearMessages();

        if (! $this->record instanceof Model) {
            $this->errorMessage = 'The selected record could not be found.';

            return;
        }

        if (! $this->authorization()->canUpdate(null, $this->record)) {
            abort(403);
        }

        $relation = $this->findBelongsToManyRelation($relationshipName);
        if (! $relation instanceof BelongsToManyRelation && ! $relation instanceof MorphToManyRelation) {
            $this->errorMessage = 'The selected relation is not available.';

            return;
        }

        if (! $relation->canDetach()) {
            $this->errorMessage = 'Detaching related records is not allowed for this relation.';

            return;
        }

        if (! $confirmed) {
            $this->pendingDetachRelation = $relationshipName;
            $this->pendingDetachRecordId = (string) $relatedRecordId;
            $this->errorMessage = 'Please confirm detaching this related record before continuing.';

            return;
        }

        $relatedResource = $relation->getResourceClass();
        $relatedAuthorization = ResourceAuthorization::for($relatedResource);
        $relatedRecord = $relation instanceof MorphToManyRelation
            ? $this->morphToManyRelationManager()->resolveRelatedRecord($this->record, $relation, $relatedRecordId)
            : $this->belongsToManyRelationManager()->resolveRelatedRecord($this->record, $relation, $relatedRecordId);

        if (! $relatedRecord instanceof Model) {
            $this->errorMessage = 'The selected related record could not be found.';

            return;
        }

        if (! $relatedAuthorization->canUpdate(null, $relatedRecord)) {
            abort(403);
        }

        $isAttached = $relation instanceof MorphToManyRelation
            ? $this->morphToManyRelationManager()->isAttached($this->record, $relation, (string) $relatedRecord->getKey())
            : $this->belongsToManyRelationManager()->isAttached($this->record, $relation, (string) $relatedRecord->getKey());

        if (! $isAttached) {
            $this->errorMessage = 'The selected related record is not attached.';

            return;
        }

        if ($relation instanceof MorphToManyRelation) {
            $this->morphToManyRelationManager()->detach($this->record, $relation, (string) $relatedRecord->getKey());
        } else {
            $this->belongsToManyRelationManager()->detach($this->record, $relation, (string) $relatedRecord->getKey());
        }
        $this->pendingDetachRelation = null;
        $this->pendingDetachRecordId = null;
        $this->statusMessage = 'Related record detached successfully.';
    }

    public function openHasManyCreateModal(string $relationshipName): void
    {
        $relation = $this->findHasManyRelation($relationshipName);
        if ((! $relation instanceof HasManyRelation && ! $relation instanceof MorphManyRelation) || ! $relation->canCreate()) {
            return;
        }

        if (! ResourceAuthorization::for($relation->getResourceClass())->canCreate()) {
            abort(403);
        }

        $fields = $this->relationModalFields($relation, ResourceContext::Create);
        $this->relationModalRelationship = $relationshipName;
        $this->relationModalMode = 'create';
        $this->relationModalRecordId = null;
        $this->relationModalValues = $this->relationModalFormHandler()->initializeValuesFromFields($fields, null);
        $this->initializeRelationModalState($fields);
        $this->resetErrorBag();
    }

    public function openHasOneCreateModal(string $relationshipName): void
    {
        $relation = $this->findHasOneRelation($relationshipName);
        if ((! $relation instanceof HasOneRelation && ! $relation instanceof MorphOneRelation) || ! $relation->canCreate()) {
            return;
        }

        if (! ResourceAuthorization::for($relation->getResourceClass())->canCreate()) {
            abort(403);
        }

        if ($this->resolveHasOneRecord($relation) instanceof Model) {
            $this->errorMessage = 'A related record already exists for this relation.';

            return;
        }

        $fields = $this->relationModalFields($relation, ResourceContext::Create);
        $this->relationModalRelationship = $relationshipName;
        $this->relationModalMode = 'create';
        $this->relationModalRecordId = null;
        $this->relationModalValues = $this->relationModalFormHandler()->initializeValuesFromFields($fields, null);
        $this->initializeRelationModalState($fields);
        $this->resetErrorBag();
    }

    public function openHasManyEditModal(string $relationshipName, int|string $recordId): void
    {
        $relation = $this->findHasManyRelation($relationshipName);
        if ((! $relation instanceof HasManyRelation && ! $relation instanceof MorphManyRelation) || ! $relation->canEdit()) {
            return;
        }

        $record = $this->resolveHasManyRecord($relation, $recordId);
        if (! $record instanceof Model) {
            $this->errorMessage = 'The selected related record is not available.';

            return;
        }

        if (! ResourceAuthorization::for($relation->getResourceClass())->canUpdate(null, $record)) {
            abort(403);
        }

        $fields = $this->relationModalFields($relation, ResourceContext::Edit);
        $this->relationModalRelationship = $relationshipName;
        $this->relationModalMode = 'edit';
        $this->relationModalRecordId = (string) $recordId;
        $this->relationModalValues = $this->relationModalFormHandler()->initializeValuesFromFields($fields, $record);
        $this->initializeRelationModalState($fields, $record);
        $this->resetErrorBag();
    }

    public function openHasOneEditModal(string $relationshipName, int|string $recordId): void
    {
        $relation = $this->findHasOneRelation($relationshipName);
        if ((! $relation instanceof HasOneRelation && ! $relation instanceof MorphOneRelation) || ! $relation->canEdit()) {
            return;
        }

        $record = $this->resolveHasOneRecordById($relation, $recordId);
        if (! $record instanceof Model) {
            $this->errorMessage = 'The selected related record is not available.';

            return;
        }

        if (! ResourceAuthorization::for($relation->getResourceClass())->canUpdate(null, $record)) {
            abort(403);
        }

        $fields = $this->relationModalFields($relation, ResourceContext::Edit);
        $this->relationModalRelationship = $relationshipName;
        $this->relationModalMode = 'edit';
        $this->relationModalRecordId = (string) $recordId;
        $this->relationModalValues = $this->relationModalFormHandler()->initializeValuesFromFields($fields, $record);
        $this->initializeRelationModalState($fields, $record);
        $this->resetErrorBag();
    }

    public function cancelRelationModal(): void
    {
        $this->relationModalRelationship = null;
        $this->relationModalMode = null;
        $this->relationModalRecordId = null;
        $this->relationModalValues = [];
        $this->relationModalPresence = [];
        $this->relationModalBelongsToSearch = [];
        $this->relationModalMultiSelectSearch = [];
        $this->relationModalFileActions = [];
        $this->cancelRelationInlineCreate();
        $this->resetErrorBag();
    }

    public function updatedRelationModalValues(mixed $value, string $fieldPath): void
    {
        $changedField = str_starts_with($fieldPath, 'relationModalValues.')
            ? substr($fieldPath, strlen('relationModalValues.'))
            : $fieldPath;

        if ($changedField === '') {
            return;
        }

        $this->markRelationModalFieldPresent($changedField);

        foreach ($this->relationModalFieldsForCurrent() as $field) {
            if (! $field instanceof BelongsToField || $field->getDependsOnField() !== $changedField) {
                continue;
            }

            $currentValue = (string) ($this->relationModalValues[$field->name()] ?? '');
            if ($currentValue === '') {
                continue;
            }

            $candidateValues = $this->relationModalValues;
            $options = (new BelongsToFieldOptions)->resolve(
                [$field],
                $this->relationModalModelClass(),
                [$field->name() => (string) ($this->relationModalBelongsToSearch[$field->name()] ?? '')],
                $candidateValues
            );
            if (! array_key_exists($currentValue, $options[$field->name()] ?? [])) {
                $this->relationModalValues[$field->name()] = '';
                $this->markRelationModalFieldPresent($field->name());
            }
        }
    }

    public function updatedRelationInlineCreateValues(mixed $value, string $fieldPath): void
    {
        $this->markRelationInlineCreateFieldPresent($fieldPath);
    }

    public function selectRelationModalBelongsToOption(string $fieldName, int|string $value): void
    {
        $field = $this->findRelationModalField($fieldName);
        if (! $field instanceof BelongsToField) {
            return;
        }

        $candidateValues = $this->relationModalValues;
        $candidateValues[$fieldName] = (string) $value;
        $options = (new BelongsToFieldOptions)->resolve(
            [$field],
            $this->relationModalModelClass(),
            [$fieldName => (string) ($this->relationModalBelongsToSearch[$fieldName] ?? '')],
            $candidateValues
        );
        if (! array_key_exists((string) $value, $options[$fieldName] ?? [])) {
            return;
        }

        $this->relationModalValues[$fieldName] = (string) $value;
        $this->markRelationModalFieldPresent($fieldName);
        $this->relationModalBelongsToSearch[$fieldName] = '';
    }

    public function clearRelationModalBelongsToOption(string $fieldName): void
    {
        if (! $this->findRelationModalField($fieldName) instanceof BelongsToField) {
            return;
        }

        $this->relationModalValues[$fieldName] = '';
        $this->markRelationModalFieldPresent($fieldName);
        $this->relationModalBelongsToSearch[$fieldName] = '';
    }

    public function toggleRelationModalMultiSelectOption(string $fieldName, int|string $value): void
    {
        $field = $this->findRelationModalField($fieldName);
        if (! $field instanceof MultiSelectField) {
            return;
        }

        $value = (string) $value;
        $allowed = array_map(static fn (int|string $option): string => (string) $option, array_keys($field->getOptions()));
        if (! in_array($value, $allowed, true)) {
            return;
        }

        $selected = is_array($this->relationModalValues[$fieldName] ?? null)
            ? array_values(array_map(static fn (mixed $item): string => (string) $item, $this->relationModalValues[$fieldName]))
            : [];
        if (in_array($value, $selected, true)) {
            $selected = array_values(array_filter($selected, static fn (string $item): bool => $item !== $value));
        } elseif ($field->getMaxSelected() === null || count($selected) < $field->getMaxSelected()) {
            $selected[] = $value;
        }

        $this->relationModalValues[$fieldName] = $selected;
        $this->markRelationModalFieldPresent($fieldName);
    }

    public function removeRelationModalMultiSelectOption(string $fieldName, int|string $value): void
    {
        $field = $this->findRelationModalField($fieldName);
        if (! $field instanceof MultiSelectField) {
            return;
        }

        $value = (string) $value;
        $selected = is_array($this->relationModalValues[$fieldName] ?? null) ? $this->relationModalValues[$fieldName] : [];
        $this->relationModalValues[$fieldName] = array_values(array_filter(
            array_map(static fn (mixed $item): string => (string) $item, $selected),
            static fn (string $item): bool => $item !== $value
        ));
        $this->markRelationModalFieldPresent($fieldName);
    }

    public function beginRelationInlineCreate(string $fieldName): void
    {
        $field = $this->findRelationModalField($fieldName);
        if (! $field instanceof BelongsToField) {
            return;
        }

        $resourceClass = $field->getInlineCreateResourceClass();
        if ($resourceClass === null) {
            return;
        }

        if (! ResourceAuthorization::for($resourceClass)->canCreate()) {
            abort(403);
        }

        $this->relationInlineCreateFieldName = $fieldName;
        $this->relationInlineCreateBelongsToSearch = [];
        $this->relationInlineCreateMultiSelectSearch = [];
        $this->relationInlineCreateValues = (new FormStateInitializer)->initialize(
            $this->relationInlineCreateState()['fields'],
            null,
            ResourceContext::Create
        );
        $this->relationInlineCreatePresence = (new FormStateInitializer)->initialPresence(
            $this->relationInlineCreateState()['fields'],
            null
        );
        $this->resetErrorBag();
    }

    public function cancelRelationInlineCreate(): void
    {
        $this->relationInlineCreateFieldName = null;
        $this->relationInlineCreateValues = [];
        $this->relationInlineCreatePresence = [];
        $this->relationInlineCreateBelongsToSearch = [];
        $this->relationInlineCreateMultiSelectSearch = [];
    }

    public function selectRelationInlineCreateBelongsToOption(string $fieldName, int|string $value): void
    {
        $state = $this->relationInlineCreateState();
        $field = $this->findFieldInList($state['fields'], $fieldName);
        if (! $field instanceof BelongsToField || ! is_string($state['modelClass'])) {
            return;
        }

        $candidateValues = $this->relationInlineCreateValues;
        $candidateValues[$fieldName] = (string) $value;
        $options = (new BelongsToFieldOptions)->resolve(
            [$field],
            $state['modelClass'],
            [$fieldName => (string) ($this->relationInlineCreateBelongsToSearch[$fieldName] ?? '')],
            $candidateValues
        );
        if (! array_key_exists((string) $value, $options[$fieldName] ?? [])) {
            return;
        }

        $this->relationInlineCreateValues[$fieldName] = (string) $value;
        $this->markRelationInlineCreateFieldPresent($fieldName);
        $this->relationInlineCreateBelongsToSearch[$fieldName] = '';
    }

    public function clearRelationInlineCreateBelongsToOption(string $fieldName): void
    {
        if (! $this->findFieldInList($this->relationInlineCreateState()['fields'], $fieldName) instanceof BelongsToField) {
            return;
        }

        $this->relationInlineCreateValues[$fieldName] = '';
        $this->markRelationInlineCreateFieldPresent($fieldName);
        $this->relationInlineCreateBelongsToSearch[$fieldName] = '';
    }

    public function toggleRelationInlineCreateMultiSelectOption(string $fieldName, int|string $value): void
    {
        $field = $this->findFieldInList($this->relationInlineCreateState()['fields'], $fieldName);
        if (! $field instanceof MultiSelectField) {
            return;
        }

        $value = (string) $value;
        $allowed = array_map(static fn (int|string $option): string => (string) $option, array_keys($field->getOptions()));
        if (! in_array($value, $allowed, true)) {
            return;
        }

        $selected = is_array($this->relationInlineCreateValues[$fieldName] ?? null)
            ? array_values(array_map(static fn (mixed $item): string => (string) $item, $this->relationInlineCreateValues[$fieldName]))
            : [];
        if (in_array($value, $selected, true)) {
            $selected = array_values(array_filter($selected, static fn (string $item): bool => $item !== $value));
        } elseif ($field->getMaxSelected() === null || count($selected) < $field->getMaxSelected()) {
            $selected[] = $value;
        }

        $this->relationInlineCreateValues[$fieldName] = $selected;
        $this->markRelationInlineCreateFieldPresent($fieldName);
    }

    public function removeRelationInlineCreateMultiSelectOption(string $fieldName, int|string $value): void
    {
        $field = $this->findFieldInList($this->relationInlineCreateState()['fields'], $fieldName);
        if (! $field instanceof MultiSelectField) {
            return;
        }

        $value = (string) $value;
        $selected = is_array($this->relationInlineCreateValues[$fieldName] ?? null) ? $this->relationInlineCreateValues[$fieldName] : [];
        $this->relationInlineCreateValues[$fieldName] = array_values(array_filter(
            array_map(static fn (mixed $item): string => (string) $item, $selected),
            static fn (string $item): bool => $item !== $value
        ));
        $this->markRelationInlineCreateFieldPresent($fieldName);
    }

    public function saveRelationInlineCreate(): void
    {
        $state = $this->relationInlineCreateState();
        $field = $state['field'];
        $resourceClass = $state['resourceClass'];
        $modelClass = $state['modelClass'];
        $fields = $state['fields'];

        if (! $field instanceof BelongsToField || ! is_string($resourceClass) || ! is_string($modelClass)) {
            return;
        }

        if (! ResourceAuthorization::for($resourceClass)->canCreate()) {
            abort(403);
        }

        $formState = new FormState($this->relationInlineCreateValues, ResourceContext::Create);
        $validated = $this->validate(
            (new FormValidationRules)->build(
                $fields,
                ResourceContext::Create,
                $modelClass,
                'relationInlineCreateValues',
                $this->relationInlineCreateValues,
                null,
                $formState
            ),
            messages: [],
            attributes: (new FormValidationRules)->attributes(
                $fields,
                ResourceContext::Create,
                'relationInlineCreateValues',
                $formState
            )
        );

        $values = is_array($validated['relationInlineCreateValues'] ?? null) ? $validated['relationInlineCreateValues'] : [];
        $values = $this->presentValues($values, $this->relationInlineCreatePresence);

        $created = $this->operationService()->execute(
            resourceClass: $resourceClass,
            resourceSlug: $this->resourceSlugForClass($resourceClass),
            model: new $modelClass,
            fields: $fields,
            values: $values,
            context: ResourceContext::Create,
            operationContext: new ResourceOperationContext(
                actor: auth()->user(),
                source: ResourceEventContext::SOURCE_INLINE_CREATE,
                request: request(),
            )
        );

        $this->relationModalValues[$field->name()] = (string) $created->getKey();
        $this->cancelRelationInlineCreate();
        $this->statusMessage = 'Related record created successfully.';
        $this->dispatch('seviaflow:focus-field', fieldId: $this->fieldDomId('relationModalValues', $field->name()));
    }

    public function saveRelationModal(): void
    {
        $state = $this->relationModalState();
        $relation = $state['relation'];
        $resourceClass = $state['resourceClass'];
        $fields = $state['fields'];
        $modelClass = $state['modelClass'];

        if ((! $relation instanceof HasManyRelation && ! $relation instanceof HasOneRelation && ! $relation instanceof MorphManyRelation && ! $relation instanceof MorphOneRelation) || ! is_string($resourceClass) || ! is_string($modelClass)) {
            return;
        }

        if (! $this->record instanceof Model) {
            $this->errorMessage = 'The selected record could not be found.';

            return;
        }

        if (! $this->authorization()->canUpdate(null, $this->record)) {
            abort(403);
        }

        $context = $this->relationModalMode === 'edit' ? ResourceContext::Edit : ResourceContext::Create;
        $existingRecord = null;
        if ($this->relationModalMode === 'edit') {
            $existingRecord = $relation instanceof HasManyRelation || $relation instanceof MorphManyRelation
                ? $this->resolveHasManyRecord($relation, (string) $this->relationModalRecordId)
                : $this->resolveHasOneRecordById($relation, (string) $this->relationModalRecordId);

            if (! $existingRecord instanceof Model) {
                $this->errorMessage = 'The selected related record is not available.';

                return;
            }

            if (! ResourceAuthorization::for($resourceClass)->canUpdate(null, $existingRecord)) {
                abort(403);
            }
        }

        $rules = $this->formValidationRules()->build(
            $fields,
            $context,
            $modelClass,
            'relationModalValues',
            $this->relationModalValues,
            $existingRecord,
        );
        $values = $this->relationModalValues;

        if ($rules !== []) {
            $validated = $this->validate(
                $rules,
                messages: [],
                attributes: $this->formValidationRules()->attributes($fields, $context, 'relationModalValues')
            );
            $values = is_array($validated['relationModalValues'] ?? null) ? $validated['relationModalValues'] : [];
        }
        $values = $this->presentValues($values, $this->relationModalPresence);
        $values = $this->applyRelationModalFileActions($values, $fields);

        if ($this->relationModalMode === 'edit') {
            if (! $relation->canEdit()) {
                $this->errorMessage = 'Editing related records is not allowed for this relation.';

                return;
            }

            if (! $existingRecord instanceof Model) {
                $this->errorMessage = 'The selected related record is not available.';

                return;
            }

            $this->relationModalFormHandler()->updateRelatedRecord(
                resourceClass: $resourceClass,
                resourceSlug: $this->resourceSlugForClass($resourceClass) ?? $resourceClass,
                model: $existingRecord,
                fields: $fields,
                values: $values,
                operationContext: new ResourceOperationContext(
                    actor: auth()->user(),
                    source: ResourceEventContext::SOURCE_RELATION,
                    request: request(),
                ),
            );
            $this->statusMessage = 'Related record updated successfully.';
            $this->cancelRelationModal();

            return;
        }

        if (! $relation->canCreate()) {
            $this->errorMessage = 'Creating related records is not allowed for this relation.';

            return;
        }

        if (! ResourceAuthorization::for($resourceClass)->canCreate()) {
            abort(403);
        }

        if ($relation instanceof HasOneRelation || $relation instanceof MorphOneRelation) {
            if ($this->resolveHasOneRecord($relation) instanceof Model) {
                $this->errorMessage = 'A related record already exists for this relation.';

                return;
            }

            $relationship = $this->hasOneRelationship($relation);
        } else {
            $relationship = $this->hasManyRelationship($relation);
        }

        $this->relationModalFormHandler()->createRelatedRecord(
            relationship: $relationship,
            modelClass: $modelClass,
            fields: $fields,
            values: $values,
            resourceClass: $resourceClass,
            resourceSlug: $this->resourceSlugForClass($resourceClass) ?? $resourceClass,
            operationContext: new ResourceOperationContext(
                actor: auth()->user(),
                source: ResourceEventContext::SOURCE_RELATION,
                request: request(),
            ),
        );
        $this->statusMessage = 'Related record created successfully.';
        $this->cancelRelationModal();
    }

    public function deleteHasManyRecord(string $relationshipName, int|string $recordId, bool $confirmed = false): void
    {
        $this->clearMessages();

        $relation = $this->findHasManyRelation($relationshipName);
        if ((! $relation instanceof HasManyRelation && ! $relation instanceof MorphManyRelation) || ! $relation->canDelete()) {
            return;
        }

        $record = $this->resolveHasManyRecordWithTrashed($relation, $recordId);
        if (! $record instanceof Model) {
            $this->errorMessage = 'The selected related record is not available.';

            return;
        }

        if (! ResourceAuthorization::for($relation->getResourceClass())->canDelete(null, $record)) {
            abort(403);
        }

        if (! $confirmed) {
            $this->pendingHasManyDeleteRelation = $relationshipName;
            $this->pendingHasManyDeleteRecordId = (string) $recordId;
            $this->errorMessage = 'You are about to move this related record to trash.';

            return;
        }

        $record->delete();
        $this->pendingHasManyDeleteRelation = null;
        $this->pendingHasManyDeleteRecordId = null;
        $this->statusMessage = $relation->supportsSoftDeletes()
            ? 'Related record moved to trash successfully.'
            : 'Related record deleted successfully.';
    }

    public function restoreHasManyRecord(string $relationshipName, int|string $recordId, bool $confirmed = false): void
    {
        $this->clearMessages();

        $relation = $this->findHasManyRelation($relationshipName);
        if ((! $relation instanceof HasManyRelation && ! $relation instanceof MorphManyRelation) || ! $relation->canRestore() || ! $relation->supportsSoftDeletes()) {
            return;
        }

        $record = $this->resolveHasManyRecordWithTrashed($relation, $recordId);
        if (! $record instanceof Model) {
            $this->errorMessage = 'The selected related record is not available.';

            return;
        }

        if (! method_exists($record, 'trashed') || ! $record->trashed()) {
            $this->errorMessage = 'Only trashed related records can be restored.';

            return;
        }

        if (! method_exists($record, 'restore')) {
            $this->errorMessage = 'Restore is not available for this related record.';

            return;
        }

        if (! ResourceAuthorization::for($relation->getResourceClass())->canRestore(null, $record)) {
            abort(403);
        }

        if (! $confirmed) {
            $this->pendingHasManyDeleteRelation = $relationshipName;
            $this->pendingHasManyDeleteRecordId = (string) $recordId;
            $this->errorMessage = 'Please confirm restoring this related record before continuing.';

            return;
        }

        $record->restore();
        $this->pendingHasManyDeleteRelation = null;
        $this->pendingHasManyDeleteRecordId = null;
        $this->statusMessage = 'Related record restored successfully.';
    }

    public function deleteHasOneRecord(string $relationshipName, int|string $recordId, bool $confirmed = false): void
    {
        $this->clearMessages();

        $relation = $this->findHasOneRelation($relationshipName);
        if ((! $relation instanceof HasOneRelation && ! $relation instanceof MorphOneRelation) || ! $relation->canDelete()) {
            return;
        }

        $record = $this->resolveHasOneRecordById($relation, $recordId);
        if (! $record instanceof Model) {
            $this->errorMessage = 'The selected related record is not available.';

            return;
        }

        if (! ResourceAuthorization::for($relation->getResourceClass())->canDelete(null, $record)) {
            abort(403);
        }

        if (! $confirmed) {
            $this->pendingHasOneDeleteRelation = $relationshipName;
            $this->errorMessage = 'You are about to delete this related record.';

            return;
        }

        $record->delete();
        $this->pendingHasOneDeleteRelation = null;
        $this->statusMessage = 'Related record deleted successfully.';
    }

    /**
     * @return array<int, Field>
     */
    private function fields(): array
    {
        return $this->formDefinition()->getAllFields();
    }

    /**
     * @return array<int, Section>
     */
    private function sections(): array
    {
        return $this->formDefinition()->getSections();
    }

    private function query(): Builder
    {
        $model = new $this->modelClass;

        return ResourceScopedQuery::make($this->resource, $model->newQuery());
    }

    private function formDefinition(): Form
    {
        return $this->resource::show(new Form);
    }

    /**
     * @return array<int, Field>
     */
    private function visibleFields(): array
    {
        return $this->formFields()->visible($this->fields(), ResourceContext::Show);
    }

    /**
     * @return array<int, Section>
     */
    private function visibleSections(): array
    {
        return $this->formFields()->visibleSections($this->sections(), ResourceContext::Show);
    }

    private function formatFieldValue(Field $field): string
    {
        if (! $this->record instanceof Model || SensitiveDataPolicy::isSensitive($field->name())) {
            return '—';
        }

        return app(FieldRendererRegistry::class)->render(
            field: $field,
            mode: 'show',
            data: ['value' => $this->record->getAttribute($field->name()), 'record' => $this->record],
        );
    }

    private function resourceLabel(): string
    {
        if (! $this->record instanceof Model) {
            return 'Record details';
        }

        $title = $this->resource::recordTitle($this->record);

        if ($title !== null) {
            return $title;
        }

        return sprintf(
            '%s #%s',
            class_basename($this->modelClass),
            (string) $this->record->getKey()
        );
    }

    /**
     * @return array<string, string>
     */
    private function recordMetadata(): array
    {
        if (! $this->record instanceof Model) {
            return [];
        }

        return [
            'ID' => (string) $this->record->getKey(),
            ...$this->resource::recordMetadata($this->record),
        ];
    }

    private function formFields(): FormFields
    {
        return new FormFields;
    }

    /**
     * @return array<int, Action>
     */
    private function authorizedActions(): array
    {
        return $this->showActionResolver()->authorizedActions(
            resourceClass: $this->resource,
            record: $this->record,
            user: auth()->user()
        );
    }

    private function clearMessages(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    private function workflowPanelState(): array
    {
        return $this->workflowPanelStateResolver()->resolve(
            resourceClass: $this->resource,
            record: $this->record,
            user: auth()->user()
        );
    }

    private function workflowStateColorClasses(?string $color): string
    {
        return match ($color) {
            'red' => 'bg-red-100 text-red-800',
            'yellow' => 'bg-yellow-100 text-yellow-800',
            'green' => 'bg-green-100 text-green-800',
            'blue' => 'bg-blue-100 text-blue-800',
            'indigo' => 'bg-indigo-100 text-indigo-800',
            'purple' => 'bg-purple-100 text-purple-800',
            'pink' => 'bg-pink-100 text-pink-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    private function workflowPanelStateResolver(): WorkflowPanelState
    {
        return new WorkflowPanelState;
    }

    private function workflowTransitionRunner(): WorkflowTransitionRunner
    {
        return new WorkflowTransitionRunner;
    }

    private function showActionResolver(): ShowActionResolver
    {
        return new ShowActionResolver;
    }

    private function showActionRunner(): ShowActionRunner
    {
        return new ShowActionRunner($this->showActionResolver());
    }

    private function relationManagerResolver(): RelationManagerResolver
    {
        return new RelationManagerResolver;
    }

    private function hasManyRelationManager(): HasManyRelationManager
    {
        return new HasManyRelationManager;
    }

    private function hasOneRelationManager(): HasOneRelationManager
    {
        return new HasOneRelationManager;
    }

    private function morphOneRelationManager(): MorphOneRelationManager
    {
        return new MorphOneRelationManager;
    }

    private function belongsToManyRelationManager(): BelongsToManyRelationManager
    {
        return new BelongsToManyRelationManager;
    }

    private function morphToManyRelationManager(): MorphToManyRelationManager
    {
        return new MorphToManyRelationManager;
    }

    private function morphManyRelationManager(): MorphManyRelationManager
    {
        return new MorphManyRelationManager;
    }

    private function relationQueryPaginator(): RelationQueryPaginator
    {
        return new RelationQueryPaginator;
    }

    private function relationBoundaryGuard(): RelationBoundaryGuard
    {
        return new RelationBoundaryGuard(
            $this->hasOneRelationManager(),
            $this->hasManyRelationManager(),
            $this->morphOneRelationManager(),
            $this->morphManyRelationManager(),
            $this->belongsToManyRelationManager(),
            $this->morphToManyRelationManager()
        );
    }

    private function pivotFormHandler(): PivotFormHandler
    {
        return new PivotFormHandler;
    }

    private function relationModalFormHandler(): RelationModalFormHandler
    {
        return new RelationModalFormHandler;
    }

    private function operationService(): ResourceOperationService
    {
        return new ResourceOperationService(new FormFields);
    }

    private function canEdit(): bool
    {
        if (! $this->record instanceof Model) {
            return false;
        }

        return $this->authorization()->canUpdate(null, $this->record);
    }

    private function authorization(): ResourceAuthorization
    {
        return ResourceAuthorization::for($this->resource);
    }

    /**
     * @return array<int, array{
     *   type: string,
     *   relationshipName: string,
     *   label: string,
     *   canCreate: bool,
     *   canEdit: bool,
     *   canDelete: bool,
     *   canRestore: bool,
     *   canAttach: bool,
     *   canDetach: bool,
     *   createLabel: string,
     *   editLabel: string,
     *   canEditRecord: (\Closure(Model): bool),
     *   canDeleteRecord: (\Closure(Model): bool),
     *   canRestoreRecord: (\Closure(Model): bool),
     *   columns: array<int, Column>,
     *   pivotColumns: array<int, Column>,
     *   pivotFields: array<int, Field>,
     *   canEditPivot: bool,
     *   canEditPivotRecord: (\Closure(Model): bool),
     *   records: Collection<int, Model>|LengthAwarePaginator|Paginator,
     *   expanded: bool,
     *   lazy: bool,
     *   recordCount: int,
     *   loadLabel: string,
     *   attachOptions: array<string, string>,
     *   showUrl: (\Closure(Model): ?string),
     *   editUrl: (\Closure(Model): ?string)
     * }>
     */
    private function relationManagers(): array
    {
        if (! $this->record instanceof Model) {
            return [];
        }

        $managers = [];

        foreach ($this->relationManagerResolver()->relations($this->resource) as $relation) {
            $relatedResource = $relation->getResourceClass();
            $relatedAuthorization = ResourceAuthorization::for($relatedResource);

            if (! $relatedAuthorization->canViewAny()) {
                continue;
            }

            $columns = array_values(array_filter(
                $relatedResource::table(new Table)->getColumns(),
                static fn (Column $column): bool => $column->isVisible(ResourceContext::Index)
            ));

            $relationshipName = $relation->getRelationshipName();
            $isLazy = $this->relationIsLazy($relationshipName);
            $expanded = ! $isLazy || in_array($relationshipName, $this->expandedRelations, true);
            $records = $expanded ? $this->paginateRelationRecords($relation) : Collection::make();
            $recordCount = $expanded
                ? (method_exists($records, 'total') ? (int) $records->total() : $records->count())
                : $this->relationQueryPaginator()->count($this->record, $relation, $relatedResource);
            $relatedResourceSlug = $this->resourceSlugForClass($relatedResource);
            $allowsRelatedResourceEdit = (
                $relation instanceof HasManyRelation
                || $relation instanceof HasOneRelation
                || $relation instanceof MorphManyRelation
                || $relation instanceof MorphOneRelation
            ) ? $relation->canEdit() : false;

            $managers[] = [
                'type' => $relation instanceof BelongsToManyRelation || $relation instanceof MorphToManyRelation
                    ? 'belongsToMany'
                    : ($relation instanceof HasOneRelation || $relation instanceof MorphOneRelation ? 'hasOne' : 'hasMany'),
                'relationshipName' => $relationshipName,
                'label' => $relation->getLabel(),
                'canCreate' => $relation instanceof HasManyRelation || $relation instanceof MorphManyRelation
                    ? $relation->canCreate() && ResourceAuthorization::for($relatedResource)->canCreate()
                    : ($relation instanceof HasOneRelation || $relation instanceof MorphOneRelation
                        ? $relation->canCreate()
                            && ResourceAuthorization::for($relatedResource)->canCreate()
                            && ! ($this->resolveHasOneRecord($relation) instanceof Model)
                        : false),
                'canEdit' => $relation instanceof HasManyRelation || $relation instanceof HasOneRelation || $relation instanceof MorphManyRelation || $relation instanceof MorphOneRelation ? $relation->canEdit() : false,
                'canDelete' => $relation instanceof HasManyRelation || $relation instanceof HasOneRelation || $relation instanceof MorphManyRelation || $relation instanceof MorphOneRelation ? $relation->canDelete() : false,
                'canRestore' => $relation instanceof HasManyRelation || $relation instanceof MorphManyRelation ? $relation->canRestore() && $relation->supportsSoftDeletes() : false,
                'canAttach' => $relation instanceof BelongsToManyRelation || $relation instanceof MorphToManyRelation ? $relation->canAttach() : false,
                'canDetach' => $relation instanceof BelongsToManyRelation || $relation instanceof MorphToManyRelation ? $relation->canDetach() : false,
                'createLabel' => $relation instanceof HasManyRelation || $relation instanceof HasOneRelation || $relation instanceof MorphManyRelation || $relation instanceof MorphOneRelation ? $relation->getCreateLabel() : 'Add',
                'editLabel' => $relation instanceof HasManyRelation || $relation instanceof HasOneRelation || $relation instanceof MorphManyRelation || $relation instanceof MorphOneRelation ? $relation->getEditLabel() : 'Edit',
                'canEditRecord' => fn (Model $relatedRecord): bool => $relation instanceof HasManyRelation || $relation instanceof HasOneRelation || $relation instanceof MorphManyRelation || $relation instanceof MorphOneRelation
                    ? $relation->canEdit() && $relatedAuthorization->canUpdate(null, $relatedRecord)
                    : false,
                'canDeleteRecord' => fn (Model $relatedRecord): bool => $relation instanceof HasManyRelation || $relation instanceof HasOneRelation || $relation instanceof MorphManyRelation || $relation instanceof MorphOneRelation
                    ? $relation->canDelete() && $relatedAuthorization->canDelete(null, $relatedRecord)
                    : false,
                'canRestoreRecord' => fn (Model $relatedRecord): bool => $relation instanceof HasManyRelation || $relation instanceof MorphManyRelation
                    ? $relation->canRestore()
                        && $relation->supportsSoftDeletes()
                        && method_exists($relatedRecord, 'trashed')
                        && $relatedRecord->trashed()
                        && ResourceAuthorization::for($relatedResource)->canRestore(null, $relatedRecord)
                    : false,
                'columns' => $columns,
                'pivotColumns' => $relation instanceof BelongsToManyRelation || $relation instanceof MorphToManyRelation ? $relation->getPivotColumns() : [],
                'pivotFields' => $relation instanceof BelongsToManyRelation || $relation instanceof MorphToManyRelation ? $relation->getPivotFields() : [],
                'canEditPivot' => $relation instanceof BelongsToManyRelation || $relation instanceof MorphToManyRelation ? $relation->getPivotFields() !== [] : false,
                'canEditPivotRecord' => fn (Model $relatedRecord): bool => $relation instanceof BelongsToManyRelation || $relation instanceof MorphToManyRelation
                    ? $relation->getPivotFields() !== []
                        && ResourceAuthorization::for($relatedResource)->canUpdate(null, $relatedRecord)
                    : false,
                'records' => $records,
                'expanded' => $expanded,
                'lazy' => $isLazy,
                'recordCount' => $recordCount,
                'loadLabel' => 'Open to load related records',
                'attachOptions' => ($relation instanceof BelongsToManyRelation || $relation instanceof MorphToManyRelation) && $relation->canAttach()
                    ? $this->belongsToManyAttachOptions($relation)
                    : [],
                'showUrl' => function (Model $relatedRecord) use ($relatedResourceSlug, $relatedAuthorization): ?string {
                    if ($relatedResourceSlug === null || ! $relatedAuthorization->canView(null, $relatedRecord)) {
                        return null;
                    }

                    return route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'resources.show', [
                        'resource' => $relatedResourceSlug,
                        'record' => $relatedRecord->getKey(),
                    ]);
                },
                'editUrl' => function (Model $relatedRecord) use ($allowsRelatedResourceEdit, $relatedResourceSlug, $relatedAuthorization): ?string {
                    if (! $allowsRelatedResourceEdit || $relatedResourceSlug === null || ! $relatedAuthorization->canUpdate(null, $relatedRecord)) {
                        return null;
                    }

                    return route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'resources.edit', [
                        'resource' => $relatedResourceSlug,
                        'record' => $relatedRecord->getKey(),
                    ]);
                },
            ];
        }

        return $managers;
    }

    private function relationIsLazy(string $relationshipName): bool
    {
        return in_array($relationshipName, $this->resource::lazyRelations(), true);
    }

    private function relationPageName(string $relationshipName): string
    {
        return 'rel_'.preg_replace('/[^A-Za-z0-9_]/', '_', $relationshipName);
    }

    private function paginateRelationRecords(HasOneRelation|HasManyRelation|BelongsToManyRelation|MorphOneRelation|MorphManyRelation|MorphToManyRelation $relation): Collection|LengthAwarePaginator
    {
        if (! $this->record instanceof Model) {
            throw new InvalidArgumentException('Cannot resolve relation records without a parent record.');
        }
        $relatedResource = $relation->getResourceClass();

        if ($relation instanceof HasOneRelation || $relation instanceof MorphOneRelation) {
            $record = $this->resolveHasOneRecord($relation);

            return $record instanceof Model ? Collection::make([$record]) : Collection::make();
        }

        if ($relation instanceof HasManyRelation) {
            return $this->relationQueryPaginator()->paginateHasMany($this->record, $relation, $relatedResource);
        }

        if ($relation instanceof MorphManyRelation) {
            return $this->relationQueryPaginator()->paginateMorphMany($this->record, $relation, $relatedResource);
        }

        if ($relation instanceof MorphToManyRelation) {
            return $this->relationQueryPaginator()->paginateMorphToMany($this->record, $relation, $relatedResource);
        }

        return $this->relationQueryPaginator()->paginateBelongsToMany($this->record, $relation, $relatedResource);
    }

    /**
     * @return array<string, string>
     */
    private function belongsToManyAttachOptions(BelongsToManyRelation|MorphToManyRelation $relation): array
    {
        if (! $this->record instanceof Model) {
            throw new InvalidArgumentException('Cannot resolve relation records without a parent record.');
        }

        if ($relation instanceof MorphToManyRelation) {
            return $this->morphToManyRelationManager()->attachOptions($this->record, $relation, $relation->getResourceClass());
        }

        return $this->belongsToManyRelationManager()->attachOptions($this->record, $relation, $relation->getResourceClass());
    }

    private function hasManyRelationship(HasManyRelation|MorphManyRelation $relation): HasMany|MorphMany
    {
        if (! $this->record instanceof Model) {
            throw new InvalidArgumentException('Cannot resolve relation records without a parent record.');
        }

        if ($relation instanceof MorphManyRelation) {
            return $this->morphManyRelationManager()->relationship($this->record, $relation);
        }

        return $this->hasManyRelationManager()->relationship($this->record, $relation);
    }

    private function hasOneRelationship(HasOneRelation|MorphOneRelation $relation): HasOne|MorphOne
    {
        if (! $this->record instanceof Model) {
            throw new InvalidArgumentException('Cannot resolve relation records without a parent record.');
        }

        if ($relation instanceof MorphOneRelation) {
            return $this->morphOneRelationManager()->relationship($this->record, $relation);
        }

        return $this->hasOneRelationManager()->relationship($this->record, $relation);
    }

    private function belongsToManyRelationship(BelongsToManyRelation|MorphToManyRelation $relation): BelongsToMany|MorphToMany
    {
        if (! $this->record instanceof Model) {
            throw new InvalidArgumentException('Cannot resolve relation records without a parent record.');
        }

        if ($relation instanceof MorphToManyRelation) {
            return $this->morphToManyRelationManager()->relationship($this->record, $relation);
        }

        return $this->belongsToManyRelationManager()->relationship($this->record, $relation);
    }

    private function findBelongsToManyRelation(string $relationshipName): BelongsToManyRelation|MorphToManyRelation|null
    {
        $belongsToMany = $this->relationManagerResolver()->findBelongsToMany($this->resource, $relationshipName);
        if ($belongsToMany !== null) {
            return $belongsToMany;
        }

        return $this->relationManagerResolver()->findMorphToMany($this->resource, $relationshipName);
    }

    private function findHasOneRelation(string $relationshipName): HasOneRelation|MorphOneRelation|null
    {
        $hasOne = $this->relationManagerResolver()->findHasOne($this->resource, $relationshipName);
        if ($hasOne !== null) {
            return $hasOne;
        }

        return $this->relationManagerResolver()->findMorphOne($this->resource, $relationshipName);
    }

    private function resolveBelongsToManyAttachedRecord(BelongsToManyRelation|MorphToManyRelation $relation, int|string|null $relatedRecordId): ?Model
    {
        if (! $this->record instanceof Model) {
            return null;
        }

        if ($relation instanceof MorphToManyRelation) {
            return $this->relationBoundaryGuard()->resolveAttachedMorphToManyRecord($this->record, $relation, $relatedRecordId);
        }

        return $this->relationBoundaryGuard()->resolveAttachedBelongsToManyRecord($this->record, $relation, $relatedRecordId);
    }

    private function resolveBelongsToManyAttachableRecord(BelongsToManyRelation|MorphToManyRelation $relation, int|string $relatedRecordId): ?Model
    {
        if (! $this->record instanceof Model) {
            return null;
        }

        if ($relation instanceof MorphToManyRelation) {
            return $this->morphToManyRelationManager()->resolveAttachableRecord(
                $this->record,
                $relation,
                $relatedRecordId,
                $relation->getResourceClass()
            );
        }

        return $this->belongsToManyRelationManager()->resolveAttachableRecord(
            $this->record,
            $relation,
            $relatedRecordId,
            $relation->getResourceClass()
        );
    }

    private function findHasManyRelation(string $relationshipName): HasManyRelation|MorphManyRelation|null
    {
        $hasMany = $this->relationManagerResolver()->findHasMany($this->resource, $relationshipName);
        if ($hasMany !== null) {
            return $hasMany;
        }

        return $this->relationManagerResolver()->findMorphMany($this->resource, $relationshipName);
    }

    private function resolveHasOneRecord(HasOneRelation|MorphOneRelation $relation): ?Model
    {
        if (! $this->record instanceof Model) {
            return null;
        }

        if ($relation instanceof MorphOneRelation) {
            return $this->relationBoundaryGuard()->resolveMorphOneRecord($this->record, $relation);
        }

        return $this->relationBoundaryGuard()->resolveHasOneRecord($this->record, $relation);
    }

    private function resolveHasOneRecordById(HasOneRelation|MorphOneRelation $relation, int|string|null $recordId): ?Model
    {
        if (! $this->record instanceof Model) {
            return null;
        }

        if ($relation instanceof MorphOneRelation) {
            return $this->relationBoundaryGuard()->resolveMorphOneRecordById($this->record, $relation, $recordId);
        }

        return $this->relationBoundaryGuard()->resolveHasOneRecordById($this->record, $relation, $recordId);
    }

    private function resolveHasManyRecord(HasManyRelation|MorphManyRelation $relation, int|string|null $recordId): ?Model
    {
        if (! $this->record instanceof Model) {
            return null;
        }

        if ($relation instanceof MorphManyRelation) {
            return $this->relationBoundaryGuard()->resolveMorphManyRecord($this->record, $relation, $recordId);
        }

        return $this->relationBoundaryGuard()->resolveHasManyRecord($this->record, $relation, $recordId);
    }

    private function resolveHasManyRecordWithTrashed(HasManyRelation|MorphManyRelation $relation, int|string|null $recordId): ?Model
    {
        if (! $this->record instanceof Model) {
            return null;
        }

        if ($relation instanceof MorphManyRelation) {
            return $this->relationBoundaryGuard()->resolveMorphManyRecordWithTrashed($this->record, $relation, $recordId);
        }

        return $this->relationBoundaryGuard()->resolveHasManyRecordWithTrashed($this->record, $relation, $recordId);
    }

    /**
     * @return array<int, Field>
     */
    private function relationModalFields(HasOneRelation|HasManyRelation|MorphOneRelation|MorphManyRelation $relation, ResourceContext $context): array
    {
        return $this->relationModalFormHandler()->relationModalFields($relation, $context);
    }

    /**
     * @return array{
     *   relation: HasOneRelation|HasManyRelation|MorphOneRelation|MorphManyRelation|null,
     *   resourceClass: class-string<\SeviaFlow\Core\Resources\Resource>|null,
     *   modelClass: class-string<Model>|null,
     *   fields: array<int, Field>,
     *   belongsToOptions: array<string, array<string, string>>,
     *   inlineCreateAllowed: array<string, bool>,
     *   inlineCreateState: array<string, mixed>,
     *   record: Model|null,
     *   fileDownloadUrls: array<string, string>,
     *   fileHasValues: array<string, bool>,
     *   fileMetadata: array<string, array{name:string, size:int|null, mime:string|null, image:bool}>,
     *   filePending: array<string, bool>,
     *   fileActions: array<string, string>,
     *   title: string|null,
     *   visible: bool
     * }
     */
    private function relationModalState(): array
    {
        $relation = $this->relationModalRelation($this->relationModalRelationship ?? '');

        if (($relation === null) || $this->relationModalMode === null) {
            return [
                'relation' => null,
                'resourceClass' => null,
                'modelClass' => null,
                'fields' => [],
                'belongsToOptions' => [],
                'inlineCreateAllowed' => [],
                'inlineCreateState' => [],
                'record' => null,
                'fileDownloadUrls' => [],
                'fileHasValues' => [],
                'fileMetadata' => [],
                'filePending' => [],
                'fileActions' => [],
                'title' => null,
                'visible' => false,
            ];
        }

        $resourceClass = $relation->getResourceClass();
        /** @var class-string<Model> $modelClass */
        $modelClass = $resourceClass::model();
        $context = $this->relationModalMode === 'edit' ? ResourceContext::Edit : ResourceContext::Create;
        $fields = $this->relationModalFields($relation, $context);
        $modalRecord = $this->relationModalRecord($relation);
        $belongsToOptions = $this->relationModalFormHandler()->belongsToOptions(
            $fields,
            $modelClass,
            $this->relationModalValues,
            $this->relationModalBelongsToSearch
        );
        $title = $this->relationModalMode === 'edit'
            ? $relation->getEditLabel().' '.$relation->getLabel()
            : $relation->getCreateLabel();

        return [
            'relation' => $relation,
            'resourceClass' => $resourceClass,
            'modelClass' => $modelClass,
            'fields' => $fields,
            'belongsToOptions' => $belongsToOptions,
            'inlineCreateAllowed' => $this->relationInlineCreateAllowedMap($fields),
            'inlineCreateState' => $this->relationInlineCreateState(),
            'record' => $modalRecord,
            'fileDownloadUrls' => $this->relationFileDownloadUrls($resourceClass, $modalRecord, $fields),
            'fileHasValues' => $this->relationFileHasValues($modalRecord, $fields),
            'fileMetadata' => $this->relationFileMetadata($modalRecord, $fields),
            'filePending' => $this->relationFilePending($fields),
            'fileActions' => $this->relationModalFileActions,
            'title' => $title,
            'visible' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, bool>  $presence
     * @return array<string, mixed>
     */
    private function presentValues(array $values, array $presence): array
    {
        $present = [];

        foreach ($values as $name => $value) {
            if (is_string($name) && ($presence[$name] ?? false)) {
                $present[$name] = $value;
            }
        }

        return $present;
    }

    private function markRelationModalFieldPresent(string $fieldPath): void
    {
        $fieldName = $this->topLevelRelationFieldName($fieldPath, 'relationModalValues');
        if ($fieldName !== '') {
            $this->relationModalPresence[$fieldName] = true;
        }
    }

    private function markRelationInlineCreateFieldPresent(string $fieldPath): void
    {
        $fieldName = $this->topLevelRelationFieldName($fieldPath, 'relationInlineCreateValues');
        if ($fieldName !== '') {
            $this->relationInlineCreatePresence[$fieldName] = true;
        }
    }

    private function topLevelRelationFieldName(string $fieldPath, string $modelPath): string
    {
        $prefix = $modelPath.'.';
        if (str_starts_with($fieldPath, $prefix)) {
            $fieldPath = substr($fieldPath, strlen($prefix));
        }

        return explode('.', $fieldPath, 2)[0];
    }

    /**
     * @param  array<int, Field>  $fields
     */
    private function initializeRelationModalState(array $fields, ?Model $record = null): void
    {
        $this->relationModalPresence = (new FormStateInitializer)->initialPresence($fields, $record);
        $this->relationModalBelongsToSearch = [];
        $this->relationModalMultiSelectSearch = [];
        $this->relationModalFileActions = [];

        foreach ($fields as $field) {
            if ($field instanceof FileField) {
                $this->relationModalFileActions[$field->name()] = 'retain';
            }
        }
    }

    /**
     * @return array<int, Field>
     */
    private function relationModalFieldsForCurrent(): array
    {
        $relation = $this->relationModalRelation($this->relationModalRelationship ?? '');
        if ($relation === null || $this->relationModalMode === null) {
            return [];
        }

        return $this->relationModalFields(
            $relation,
            $this->relationModalMode === 'edit' ? ResourceContext::Edit : ResourceContext::Create
        );
    }

    private function relationModalModelClass(): string
    {
        $relation = $this->relationModalRelation($this->relationModalRelationship ?? '');
        if ($relation !== null) {
            return $relation->getResourceClass()::model();
        }

        return $this->modelClass;
    }

    private function findRelationModalField(string $fieldName): ?Field
    {
        foreach ($this->relationModalFieldsForCurrent() as $field) {
            if ($field->name() === $fieldName) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  array<int, Field>  $fields
     */
    private function findFieldInList(array $fields, string $fieldName): ?Field
    {
        foreach ($fields as $field) {
            if ($field->name() === $fieldName) {
                return $field;
            }
        }

        return null;
    }

    private function relationModalRecord(HasOneRelation|HasManyRelation|MorphOneRelation|MorphManyRelation $relation): ?Model
    {
        if ($this->relationModalMode !== 'edit') {
            return null;
        }

        return $relation instanceof HasManyRelation || $relation instanceof MorphManyRelation
            ? $this->resolveHasManyRecord($relation, $this->relationModalRecordId)
            : $this->resolveHasOneRecordById($relation, $this->relationModalRecordId);
    }

    /**
     * @param  array<int, Field>  $fields
     * @return array<string, bool>
     */
    private function relationInlineCreateAllowedMap(array $fields): array
    {
        $allowed = [];
        foreach ($fields as $field) {
            if (! $field instanceof BelongsToField) {
                continue;
            }

            $resourceClass = $field->getInlineCreateResourceClass();
            $allowed[$field->name()] = $resourceClass !== null
                && ResourceAuthorization::for($resourceClass)->canCreate();
        }

        return $allowed;
    }

    /**
     * @return array{
     *   field: Field|null,
     *   resourceClass: class-string<\SeviaFlow\Core\Resources\Resource>|null,
     *   modelClass: class-string<Model>|null,
     *   fields: array<int, Field>,
     *   belongsToOptions: array<string, array<string, string>>,
     *   visible: bool,
     *   title: string|null
     * }
     */
    private function relationInlineCreateState(): array
    {
        $field = $this->findRelationModalField($this->relationInlineCreateFieldName ?? '');
        $resourceClass = $field instanceof BelongsToField
            ? $field->getInlineCreateResourceClass()
            : null;
        $modelClass = null;
        $fields = [];
        $belongsToOptions = [];
        $visible = false;

        if ($field instanceof BelongsToField && $resourceClass !== null) {
            $visible = ResourceAuthorization::for($resourceClass)->canCreate();
            if ($visible) {
                /** @var class-string<Model> $resolvedModelClass */
                $resolvedModelClass = $resourceClass::model();
                $modelClass = $resolvedModelClass;
                $fields = (new FormFields)->visible(
                    $resourceClass::form(new Form)->getAllFields(),
                    ResourceContext::Create
                );
                $belongsToOptions = (new BelongsToFieldOptions)->resolve(
                    $fields,
                    $resolvedModelClass,
                    [],
                    $this->relationInlineCreateValues
                );
            }
        }

        return [
            'field' => $field,
            'resourceClass' => $resourceClass,
            'modelClass' => $modelClass,
            'fields' => $fields,
            'belongsToOptions' => $belongsToOptions,
            'visible' => $visible,
            'title' => $field instanceof BelongsToField ? ($field->getInlineCreateLabel() ?? ('Create '.$field->getLabel())) : null,
        ];
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function applyRelationModalFileActions(array $values, array $fields): array
    {
        foreach ($this->relationModalFileActions as $fieldName => $action) {
            if ($action !== 'remove' || ! array_key_exists($fieldName, $values) || $values[$fieldName] !== null) {
                continue;
            }

            foreach ($fields as $field) {
                if ($field instanceof FileField && $field->name() === $fieldName) {
                    $values[$fieldName] = ['action' => 'remove'];
                    break;
                }
            }
        }

        return $values;
    }

    public function removeRelationModalFile(string $fieldName): void
    {
        $field = $this->findRelationModalField($fieldName);
        if (! $field instanceof FileField) {
            return;
        }

        $this->relationModalFileActions[$fieldName] = 'remove';
        $this->relationModalValues[$fieldName] = null;
        $this->markRelationModalFieldPresent($fieldName);
    }

    public function retainRelationModalFile(string $fieldName): void
    {
        $field = $this->findRelationModalField($fieldName);
        if (! $field instanceof FileField) {
            return;
        }

        $this->relationModalFileActions[$fieldName] = 'retain';
        $this->relationModalValues[$fieldName] = null;
        $this->markRelationModalFieldPresent($fieldName);
    }

    /**
     * @param  class-string<\SeviaFlow\Core\Resources\Resource>  $resourceClass
     * @param  array<int, Field>  $fields
     * @return array<string, string>
     */
    private function relationFileDownloadUrls(string $resourceClass, ?Model $record, array $fields): array
    {
        if (! $record instanceof Model) {
            return [];
        }

        $urls = [];
        foreach ($fields as $field) {
            if (! $field instanceof FileField || ! $field->canRead(auth()->user(), $record)) {
                continue;
            }

            $value = $record->getAttribute($field->name());
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $urls[$field->name()] = route(
                config('seviaflow-ui.route_name_prefix', 'seviaflow.').'resources.files.download',
                ['resource' => $this->resourceSlugForClass($resourceClass), 'record' => $record->getKey(), 'field' => $field->name()]
            );
        }

        return $urls;
    }

    /**
     * @param  array<int, Field>  $fields
     * @return array<string, bool>
     */
    private function relationFileHasValues(?Model $record, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            if ($field instanceof FileField) {
                $value = $record?->getAttribute($field->name());
                $values[$field->name()] = is_string($value) && trim($value) !== '';
            }
        }

        return $values;
    }

    /**
     * @param  array<int, Field>  $fields
     * @return array<string, array{name:string, size:int|null, mime:string|null, image:bool}>
     */
    private function relationFileMetadata(?Model $record, array $fields): array
    {
        $metadata = [];
        foreach ($fields as $field) {
            if (! $field instanceof FileField) {
                continue;
            }

            $path = $record?->getAttribute($field->name());
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $size = null;
            $mime = null;
            try {
                $disk = Storage::disk($field->getDisk());
                if ($disk->exists($path)) {
                    $size = $disk->size($path);
                    $mime = $disk->mimeType($path);
                }
            } catch (\Throwable) {
            }

            $metadata[$field->name()] = [
                'name' => basename($path),
                'size' => is_int($size) ? $size : null,
                'mime' => is_string($mime) ? $mime : null,
                'image' => $field instanceof ImageField,
            ];
        }

        return $metadata;
    }

    /**
     * @param  array<int, Field>  $fields
     * @return array<string, bool>
     */
    private function relationFilePending(array $fields): array
    {
        $pending = [];
        foreach ($fields as $field) {
            if ($field instanceof FileField) {
                $pending[$field->name()] = ($this->relationModalValues[$field->name()] ?? null) instanceof UploadedFile;
            }
        }

        return $pending;
    }

    private function fieldDomId(string $modelPath, string $fieldName): string
    {
        return 'field-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $modelPath.'.'.$fieldName);
    }

    private function relationModalRelation(string $relationshipName): HasOneRelation|HasManyRelation|MorphOneRelation|MorphManyRelation|null
    {
        $hasOne = $this->findHasOneRelation($relationshipName);
        if ($hasOne instanceof HasOneRelation || $hasOne instanceof MorphOneRelation) {
            return $hasOne;
        }

        $hasMany = $this->findHasManyRelation($relationshipName);
        if ($hasMany instanceof HasManyRelation || $hasMany instanceof MorphManyRelation) {
            return $hasMany;
        }

        return null;
    }

    /**
     * @return array{
     *   relation: BelongsToManyRelation|MorphToManyRelation|null,
     *   relationship: BelongsToMany|MorphToMany|null,
     *   pivotFields: array<int, Field>,
     *   visible: bool
     * }
     */
    private function pivotModalState(): array
    {
        $relation = $this->findBelongsToManyRelation($this->pivotModalRelationship ?? '');

        if ((! $relation instanceof BelongsToManyRelation && ! $relation instanceof MorphToManyRelation) || $relation->getPivotFields() === [] || $this->pivotModalMode === null) {
            return [
                'relation' => null,
                'relationship' => null,
                'pivotFields' => [],
                'visible' => false,
            ];
        }

        return [
            'relation' => $relation,
            'relationship' => $this->belongsToManyRelationship($relation),
            'pivotFields' => $relation->getPivotFields(),
            'visible' => true,
        ];
    }

    private function formValidationRules(): FormValidationRules
    {
        return new FormValidationRules($this->formFields());
    }

    private function formatColumnValue(Model $record, Column $column): string
    {
        if ($column instanceof BelongsToColumn) {
            $related = $record->getRelationValue($column->getRelationshipName());

            if (! $related instanceof Model) {
                return '—';
            }

            $value = $related->getAttribute($column->getTitleAttribute());

            return ($value === null || $value === '') ? '—' : e((string) $value);
        }

        $value = $record->getAttribute($column->name());

        if ($value === null || $value === '') {
            return '—';
        }

        if ($column instanceof BadgeColumn) {
            return BadgeRenderer::render($column, $value);
        }

        if ($column instanceof BooleanColumn) {
            return (bool) $value ? 'Yes' : 'No';
        }

        if ($column instanceof MoneyColumn) {
            return (new MoneyFormatter)->formatColumn($column, $value);
        }

        return e((string) $value);
    }

    private function formatPivotColumnValue(Model $record, Column $column): string
    {
        $pivot = $record->getRelationValue('pivot');

        if (! $pivot instanceof Model) {
            return '—';
        }

        $value = $pivot->getAttribute($column->name());

        if ($value === null || $value === '') {
            return '—';
        }

        if ($column instanceof BadgeColumn) {
            return BadgeRenderer::render($column, $value);
        }

        if ($column instanceof BooleanColumn) {
            return (bool) $value ? 'Yes' : 'No';
        }

        if ($column instanceof MoneyColumn) {
            return (new MoneyFormatter)->formatColumn($column, $value);
        }

        return e((string) $value);
    }

    /**
     * @param  class-string  $resourceClass
     */
    private function resourceSlugForClass(string $resourceClass): ?string
    {
        $resources = (array) config('seviaflow.resources', []);

        foreach ($resources as $slug => $class) {
            if (is_string($class) && $class === $resourceClass) {
                return $slug;
            }
        }

        return null;
    }
}
