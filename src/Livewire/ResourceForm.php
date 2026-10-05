<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use ReflectionMethod;
use SeviaFlow\Core\Exceptions\ResourceSlugNotRegisteredException;
use SeviaFlow\Core\Forms\BelongsToFieldOptions;
use SeviaFlow\Core\Forms\FormFields;
use SeviaFlow\Core\Forms\FormPersistence;
use SeviaFlow\Core\Forms\FormState;
use SeviaFlow\Core\Forms\FormStateInitializer;
use SeviaFlow\Core\Forms\FormValidationRules;
use SeviaFlow\Core\Operations\ResourceOperationContext;
use SeviaFlow\Core\Operations\ResourceOperationService;
use SeviaFlow\Core\Resources\Fields\BelongsToField;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Fields\ImageField;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Fields\RepeaterField;
use SeviaFlow\Core\Resources\Fields\SlugField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\ResourceAuthorization;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Resources\ResourceScopedQuery;
use SeviaFlow\Core\Resources\Section;
use SeviaFlow\Core\Resources\Tab;
use SeviaFlow\Ui\Index\TableUrlFactory;

final class ResourceForm extends Component
{
    use WithFileUploads;

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

    #[Locked]
    public bool $isEditMode = false;

    /**
     * @var array<string, mixed>
     */
    public array $listContext = [];

    /**
     * @var array<string, mixed>
     */
    public array $originalFieldValues = [];

    /**
     * @var array<string, string>
     */
    public array $originalFileActions = [];

    /**
     * @var array<string, bool>
     */
    public array $slugManualOverrides = [];

    /**
     * @var array<string, string>
     */
    public array $slugGeneratedValues = [];

    public bool $isDirty = false;

    #[Locked]
    public ?string $recordVersion = null;

    public ?string $activeTab = null;

    public bool $slugAutoUpdating = false;

    /**
     * @var array<string, mixed>
     */
    public array $fieldValues = [];

    /**
     * UI-only identity for repeater rows, keyed by model path and field name.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    public array $repeaterUiKeys = [];

    /**
     * @var array<string, string>
     */
    public array $fileActions = [];

    /**
     * @var array<string, string>
     */
    public array $belongsToSearch = [];

    /**
     * @var array<string, string>
     */
    public array $multiSelectSearch = [];

    /**
     * @var array<string, string>
     */
    public array $inlineCreateBelongsToSearch = [];

    /**
     * @var array<string, string>
     */
    public array $inlineCreateMultiSelectSearch = [];

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public ?string $inlineCreateFieldName = null;

    /**
     * @var array<string, mixed>
     */
    public array $inlineCreateValues = [];

    public function mount(string $resource, int|string|null $record = null): void
    {
        try {
            $resourceClass = app(ResourceRegistry::class)->resolve($resource);
        } catch (ResourceSlugNotRegisteredException) {
            abort(404);
        }

        $this->resourceSlug = $resource;
        $this->resource = $resourceClass;
        $this->modelClass = $resourceClass::model();
        $this->isEditMode = $record !== null;

        if ($this->isEditMode) {
            $this->record = $this->query()->find($record);

            if (! $this->record instanceof Model) {
                abort(404);
            }

            if (! $this->authorization()->canUpdate(null, $this->record)) {
                abort(403);
            }
        } elseif (! $this->authorization()->canCreate()) {
            abort(403);
        }

        $this->listContext = (new TableUrlFactory)->contextFromQuery(request()->query());
        $this->initializeFieldValues();
    }

    public function render(): View
    {
        $tableUrlFactory = new TableUrlFactory;
        $tabs = $this->visibleTabs();
        $activeTab = $this->resolvedActiveTab($tabs);

        return view('seviaflow-ui::livewire.resource-form', [
            'fields' => $this->visibleFields(),
            'sections' => $this->visibleSections(),
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'formColumns' => $this->formDefinition()->getColumns(),
            'belongsToOptions' => $this->belongsToFieldOptions()->resolve(
                $this->visibleFields(),
                $this->modelClass,
                $this->belongsToSearch,
                $this->fieldValues
            ),
            'belongsToPlaceholder' => $this->belongsToPlaceholderMap(),
            'inlineCreateAllowed' => $this->inlineCreateAllowedMap(),
            'inlineCreateState' => $this->inlineCreateState(),
            'fileDownloadUrls' => $this->fileDownloadUrls(),
            'fileHasValues' => $this->fileHasValues(),
            'fileMetadata' => $this->fileMetadata(),
            'filePending' => $this->filePending(),
            'repeaterUiKeys' => $this->repeaterUiKeys,
            'context' => $this->context(),
            'cancelUrl' => $tableUrlFactory->indexUrl($this->resourceSlug, $this->listContext),
            'showUrl' => $this->record instanceof Model
                ? $this->canonicalRecordUrl($this->record, $tableUrlFactory)
                : null,
        ])->layout('seviaflow-ui::components.app.layout', [
            'contentWidth' => 'standard',
        ]);
    }

    public function save(): void
    {
        $this->persistForm();
    }

    public function saveAndView(): void
    {
        if ($this->persistForm() && $this->record instanceof Model) {
            $this->redirect($this->canonicalRecordUrl($this->record, new TableUrlFactory));
        }
    }

    public function saveAndReturn(): void
    {
        if ($this->persistForm()) {
            $this->redirect((new TableUrlFactory)->indexUrl($this->resourceSlug, $this->listContext));
        }
    }

    public function saveAndCreateAnother(): void
    {
        if (! $this->persistForm()) {
            return;
        }

        $this->record = null;
        $this->isEditMode = false;
        $this->initializeFieldValues();
        $this->statusMessage = 'Record saved. Ready for another record.';
    }

    public function setActiveTab(string $tab): void
    {
        foreach ($this->visibleTabs() as $candidate) {
            if ($candidate->getKey() === $tab) {
                $this->activeTab = $tab;

                return;
            }
        }
    }

    private function persistForm(): bool
    {
        $this->errorMessage = null;
        $this->statusMessage = null;

        $this->fieldValues = $this->operationService()->prepareForValidation(
            $this->fields(),
            $this->fieldValues,
        );

        if ($this->record instanceof Model) {
            if (! $this->authorization()->canUpdate(null, $this->record)) {
                abort(403);
            }
        } elseif (! $this->authorization()->canCreate()) {
            abort(403);
        }

        $validated = $this->validate($this->rules(), messages: [], attributes: $this->validationAttributes());

        try {
            $this->assertRecordIsCurrent();
            $model = $this->record ?? new $this->modelClass;
            $fieldValues = is_array($validated['fieldValues'] ?? null) ? $validated['fieldValues'] : [];
            $fieldValues = $this->applyFileActions($fieldValues);
            $state = $this->formState();

            $this->record = $this->formPersistence()->persistWithResourceEvents(
                resourceClass: $this->resource,
                resourceSlug: $this->resourceSlug,
                model: $model,
                fields: $this->fields(),
                validatedFieldValues: $fieldValues,
                context: $this->context(),
                user: auth()->user(),
                request: request(),
                state: $state,
            );
            $this->isEditMode = true;
            $this->initializeFieldValues();
            $this->statusMessage = 'Record saved successfully.';
            $this->refreshDirtyState();

            return true;
        } catch (\InvalidArgumentException|\LogicException $exception) {
            $this->errorMessage = $exception->getMessage();
        } catch (\Throwable $exception) {
            report($exception);
            $this->errorMessage = 'Saving failed. Please try again.';
        }

        $this->refreshDirtyState();

        return false;
    }

    public function updatedFieldValues(mixed $value, string $fieldPath): void
    {
        $changedField = str_starts_with($fieldPath, 'fieldValues.')
            ? substr($fieldPath, strlen('fieldValues.'))
            : $fieldPath;

        if ($changedField === '') {
            return;
        }

        if ($changedField !== '' && $this->slugAutoUpdating) {
            $this->slugAutoUpdating = false;
        } else {
            $this->updateSlugState($changedField);
        }

        foreach ($this->visibleFields() as $field) {
            if (! $field instanceof BelongsToField || $field->getDependsOnField() !== $changedField) {
                continue;
            }

            $currentValue = (string) ($this->fieldValues[$field->name()] ?? '');
            if ($currentValue === '') {
                continue;
            }

            $options = $this->belongsToFieldOptions()->resolve(
                [$field],
                $this->modelClass,
                [$field->name() => (string) ($this->belongsToSearch[$field->name()] ?? '')],
                $this->fieldValues
            );

            $allowedIds = array_keys($options[$field->name()] ?? []);
            if (! in_array($currentValue, $allowedIds, true)) {
                $this->fieldValues[$field->name()] = '';
            }
        }

        $this->refreshDirtyState();
    }

    public function selectBelongsToOption(string $fieldName, int|string $value): void
    {
        $field = $this->findVisibleField($fieldName);
        if (! $field instanceof BelongsToField) {
            return;
        }

        $candidateValues = $this->fieldValues;
        $candidateValues[$fieldName] = (string) $value;
        $options = $this->belongsToFieldOptions()->resolve(
            [$field],
            $this->modelClass,
            [$fieldName => (string) ($this->belongsToSearch[$fieldName] ?? '')],
            $candidateValues
        );

        if (! array_key_exists((string) $value, $options[$fieldName] ?? [])) {
            return;
        }

        $this->fieldValues[$fieldName] = (string) $value;
        $this->belongsToSearch[$fieldName] = '';
        $this->refreshDirtyState();
    }

    public function clearBelongsToOption(string $fieldName): void
    {
        if (! $this->findVisibleField($fieldName) instanceof BelongsToField) {
            return;
        }

        $this->fieldValues[$fieldName] = '';
        $this->belongsToSearch[$fieldName] = '';
        $this->refreshDirtyState();
    }

    public function toggleMultiSelectOption(string $fieldName, int|string $value): void
    {
        $field = $this->findVisibleField($fieldName);
        if (! $field instanceof MultiSelectField) {
            return;
        }

        $value = (string) $value;
        $allowed = array_map(static fn (int|string $option): string => (string) $option, array_keys($field->getOptions()));
        if (! in_array($value, $allowed, true)) {
            return;
        }

        $selected = array_values(array_map(
            static fn (mixed $selectedValue): string => (string) $selectedValue,
            is_array($this->fieldValues[$fieldName] ?? null) ? $this->fieldValues[$fieldName] : []
        ));
        if (in_array($value, $selected, true)) {
            $selected = array_values(array_filter($selected, static fn (string $item): bool => $item !== $value));
        } elseif ($field->getMaxSelected() === null || count($selected) < $field->getMaxSelected()) {
            $selected[] = $value;
        }

        $this->fieldValues[$fieldName] = $selected;
        $this->refreshDirtyState();
    }

    public function removeMultiSelectOption(string $fieldName, int|string $value): void
    {
        $field = $this->findVisibleField($fieldName);
        if (! $field instanceof MultiSelectField) {
            return;
        }

        $value = (string) $value;
        $selected = is_array($this->fieldValues[$fieldName] ?? null) ? $this->fieldValues[$fieldName] : [];
        $this->fieldValues[$fieldName] = array_values(array_filter(
            array_map(static fn (mixed $item): string => (string) $item, $selected),
            static fn (string $item): bool => $item !== $value
        ));
        $this->refreshDirtyState();
    }

    public function addRepeaterItem(string $fieldName, string $modelPath = 'fieldValues'): void
    {
        $field = $this->repeaterField($fieldName, $modelPath);
        if (! $field instanceof RepeaterField || $this->repeaterIsLocked($field, $modelPath)) {
            return;
        }

        $values = &$this->repeaterValues($modelPath);
        $keys = &$this->repeaterKeys($modelPath);
        $items = is_array($values[$fieldName] ?? null) ? array_values($values[$fieldName]) : [];
        if ($field->getMaxItems() !== null && count($items) >= $field->getMaxItems()) {
            return;
        }

        $uiKey = $this->newRepeaterUiKey();
        $items[] = $this->newRepeaterItem($field, $modelPath);
        $values[$fieldName] = $items;
        $keys[$fieldName] ??= [];
        $keys[$fieldName][] = $uiKey;

        $this->resetRepeaterValidation($fieldName, $modelPath);
        $this->refreshDirtyState();
        $this->dispatch('seviaflow:focus-field', fieldId: $this->repeaterItemFocusId($field, $fieldName, $uiKey));
    }

    public function removeRepeaterItem(string $fieldName, string $uiKey, string $modelPath = 'fieldValues'): void
    {
        $field = $this->repeaterField($fieldName, $modelPath);
        if (! $field instanceof RepeaterField || ! $field->isDeletable() || $this->repeaterIsLocked($field, $modelPath)) {
            return;
        }

        $values = &$this->repeaterValues($modelPath);
        $keys = &$this->repeaterKeys($modelPath);
        $items = is_array($values[$fieldName] ?? null) ? array_values($values[$fieldName]) : [];
        $itemKeys = array_values($keys[$fieldName] ?? []);
        $index = array_search($uiKey, $itemKeys, true);
        if ($index === false || ($field->getMinItems() !== null && count($items) <= $field->getMinItems())) {
            return;
        }

        array_splice($items, $index, 1);
        array_splice($itemKeys, $index, 1);
        $values[$fieldName] = $items;
        $keys[$fieldName] = $itemKeys;
        $this->resetRepeaterValidation($fieldName, $modelPath);
        $this->refreshDirtyState();
    }

    public function moveRepeaterItemUp(string $fieldName, string $uiKey, string $modelPath = 'fieldValues'): void
    {
        $this->moveRepeaterItem($fieldName, $uiKey, -1, $modelPath);
    }

    public function moveRepeaterItemDown(string $fieldName, string $uiKey, string $modelPath = 'fieldValues'): void
    {
        $this->moveRepeaterItem($fieldName, $uiKey, 1, $modelPath);
    }

    private function moveRepeaterItem(string $fieldName, string $uiKey, int $direction, string $modelPath): void
    {
        $field = $this->repeaterField($fieldName, $modelPath);
        if (! $field instanceof RepeaterField || ! $field->isReorderable() || $this->repeaterIsLocked($field, $modelPath)) {
            return;
        }

        $values = &$this->repeaterValues($modelPath);
        $keys = &$this->repeaterKeys($modelPath);
        $items = is_array($values[$fieldName] ?? null) ? array_values($values[$fieldName]) : [];
        $itemKeys = array_values($keys[$fieldName] ?? []);
        $index = array_search($uiKey, $itemKeys, true);
        $target = is_int($index) ? $index + $direction : -1;
        if ($index === false || ! isset($itemKeys[$target])) {
            return;
        }

        [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
        [$itemKeys[$index], $itemKeys[$target]] = [$itemKeys[$target], $itemKeys[$index]];
        $values[$fieldName] = $items;
        $keys[$fieldName] = $itemKeys;
        $this->resetRepeaterValidation($fieldName, $modelPath);
        $this->refreshDirtyState();
    }

    public function selectInlineCreateBelongsToOption(string $fieldName, int|string $value): void
    {
        $state = $this->inlineCreateState();
        $field = $this->findFieldInList($state['fields'], $fieldName);
        if (! $field instanceof BelongsToField || ! is_string($state['modelClass'])) {
            return;
        }

        $candidateValues = $this->inlineCreateValues;
        $candidateValues[$fieldName] = (string) $value;
        $options = $this->belongsToFieldOptions()->resolve(
            [$field],
            $state['modelClass'],
            [$fieldName => (string) ($this->inlineCreateBelongsToSearch[$fieldName] ?? '')],
            $candidateValues
        );
        if (! array_key_exists((string) $value, $options[$fieldName] ?? [])) {
            return;
        }

        $this->inlineCreateValues[$fieldName] = (string) $value;
        $this->inlineCreateBelongsToSearch[$fieldName] = '';
        $this->refreshDirtyState();
    }

    public function clearInlineCreateBelongsToOption(string $fieldName): void
    {
        if (! $this->findFieldInList($this->inlineCreateState()['fields'], $fieldName) instanceof BelongsToField) {
            return;
        }

        $this->inlineCreateValues[$fieldName] = '';
        $this->inlineCreateBelongsToSearch[$fieldName] = '';
        $this->refreshDirtyState();
    }

    public function toggleInlineCreateMultiSelectOption(string $fieldName, int|string $value): void
    {
        $field = $this->findFieldInList($this->inlineCreateState()['fields'], $fieldName);
        if (! $field instanceof MultiSelectField) {
            return;
        }

        $value = (string) $value;
        $allowed = array_map(static fn (int|string $option): string => (string) $option, array_keys($field->getOptions()));
        if (! in_array($value, $allowed, true)) {
            return;
        }

        $selected = is_array($this->inlineCreateValues[$fieldName] ?? null)
            ? array_values(array_map(static fn (mixed $item): string => (string) $item, $this->inlineCreateValues[$fieldName]))
            : [];
        if (in_array($value, $selected, true)) {
            $selected = array_values(array_filter($selected, static fn (string $item): bool => $item !== $value));
        } elseif ($field->getMaxSelected() === null || count($selected) < $field->getMaxSelected()) {
            $selected[] = $value;
        }

        $this->inlineCreateValues[$fieldName] = $selected;
        $this->refreshDirtyState();
    }

    public function removeInlineCreateMultiSelectOption(string $fieldName, int|string $value): void
    {
        $field = $this->findFieldInList($this->inlineCreateState()['fields'], $fieldName);
        if (! $field instanceof MultiSelectField) {
            return;
        }

        $value = (string) $value;
        $selected = is_array($this->inlineCreateValues[$fieldName] ?? null) ? $this->inlineCreateValues[$fieldName] : [];
        $this->inlineCreateValues[$fieldName] = array_values(array_filter(
            array_map(static fn (mixed $item): string => (string) $item, $selected),
            static fn (string $item): bool => $item !== $value
        ));
        $this->refreshDirtyState();
    }

    public function removeFile(string $fieldName): void
    {
        if (! $this->record instanceof Model || ! $this->authorization()->canUpdate(null, $this->record)) {
            abort(403);
        }

        if (! $this->isVisibleFileField($fieldName)) {
            return;
        }

        $this->fileActions[$fieldName] = 'remove';
        $this->fieldValues[$fieldName] = null;
        $this->refreshDirtyState();
    }

    public function retainFile(string $fieldName): void
    {
        if (! $this->record instanceof Model || ! $this->authorization()->canUpdate(null, $this->record)) {
            abort(403);
        }

        if (! $this->isVisibleFileField($fieldName)) {
            return;
        }

        $this->fileActions[$fieldName] = 'retain';
        $this->fieldValues[$fieldName] = null;
        $this->refreshDirtyState();
    }

    public function beginInlineCreate(string $fieldName): void
    {
        $field = $this->findInlineCreateField($fieldName);
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

        $this->inlineCreateFieldName = $fieldName;
        $this->inlineCreateBelongsToSearch = [];
        $this->inlineCreateMultiSelectSearch = [];
        $fields = $this->inlineCreateState()['fields'];
        $this->inlineCreateValues = $this->formStateInitializer()->initialize($fields, null, ResourceContext::Create);
        $this->initializeRepeaterUiKeysFor('inlineCreateValues', $fields, $this->inlineCreateValues);
        $this->resetErrorBag();
    }

    public function cancelInlineCreate(): void
    {
        $this->inlineCreateFieldName = null;
        $this->inlineCreateValues = [];
        unset($this->repeaterUiKeys['inlineCreateValues']);
        $this->inlineCreateBelongsToSearch = [];
        $this->inlineCreateMultiSelectSearch = [];
        $this->resetErrorBag();
    }

    public function saveInlineCreate(): void
    {
        $state = $this->inlineCreateState();
        $field = $state['field'];
        $resourceClass = $state['resourceClass'];
        $modelClass = $state['modelClass'];
        $fields = $state['fields'];

        if (! $field instanceof BelongsToField || $resourceClass === null || $modelClass === null) {
            return;
        }

        if (! ResourceAuthorization::for($resourceClass)->canCreate()) {
            abort(403);
        }

        $this->inlineCreateValues = $this->operationService()->prepareForValidation(
            $fields,
            $this->inlineCreateValues,
        );

        $validated = $this->validate(
            $this->formValidationRules()->build(
                $fields,
                ResourceContext::Create,
                $modelClass,
                'inlineCreateValues',
                $this->inlineCreateValues,
                null,
                new FormState($this->inlineCreateValues, ResourceContext::Create)
            ),
            messages: [],
            attributes: $this->formValidationRules()->attributes(
                $fields,
                ResourceContext::Create,
                'inlineCreateValues',
                new FormState($this->inlineCreateValues, ResourceContext::Create)
            )
        );

        $values = is_array($validated['inlineCreateValues'] ?? null) ? $validated['inlineCreateValues'] : [];
        $model = new $modelClass;

        $created = $this->operationService()->execute(
            resourceClass: $resourceClass,
            resourceSlug: $this->resourceSlugForClass($resourceClass),
            model: $model,
            fields: $fields,
            values: $values,
            context: ResourceContext::Create,
            operationContext: new ResourceOperationContext(
                actor: auth()->user(),
                source: ResourceEventContext::SOURCE_INLINE_CREATE,
                request: request(),
            ),
        );

        $this->fieldValues[$field->name()] = (string) $created->getKey();
        $this->statusMessage = 'Related record created successfully.';
        $this->cancelInlineCreate();
        $this->refreshDirtyState();
        $this->dispatch('seviaflow:focus-field', fieldId: 'field-'.$field->name());
    }

    /**
     * @return array<int, Field>
     */
    public function fields(): array
    {
        return $this->formDefinition()->getAllFields();
    }

    /**
     * @return array<int, Section>
     */
    public function sections(): array
    {
        return $this->formDefinition()->getSections();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return $this->formValidationRules()->build(
            $this->fields(),
            $this->context(),
            $this->modelClass,
            'fieldValues',
            $this->fieldValues,
            $this->record,
            $this->formState()
        );
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return $this->formValidationRules()->attributes($this->fields(), $this->context(), 'fieldValues', $this->formState());
    }

    private function initializeFieldValues(): void
    {
        $this->fieldValues = $this->formStateInitializer()->initialize($this->fields(), $this->record, $this->context());
        $this->initializeRepeaterUiKeys();
        $this->originalFieldValues = $this->fieldValues;
        $this->fileActions = [];
        foreach ($this->fields() as $field) {
            if ($field instanceof FileField) {
                $this->fileActions[$field->name()] = 'retain';
            }
        }
        $this->originalFileActions = $this->fileActions;
        $this->belongsToSearch = [];
        $this->multiSelectSearch = [];
        $this->inlineCreateBelongsToSearch = [];
        $this->inlineCreateMultiSelectSearch = [];
        $this->inlineCreateFieldName = null;
        $this->inlineCreateValues = [];
        $this->initializeSlugState();
        $this->recordVersion = $this->record instanceof Model ? $this->recordVersion($this->record) : null;
        $this->isDirty = false;
    }

    private function query(): Builder
    {
        $model = new $this->modelClass;

        return ResourceScopedQuery::make($this->resource, $model->newQuery());
    }

    private function formDefinition(): Form
    {
        return $this->resource::form(new Form);
    }

    /**
     * @return array<int, Field>
     */
    private function visibleFields(): array
    {
        return $this->formFields()->visible($this->fields(), $this->context(), $this->formState());
    }

    /**
     * @return array<int, Section>
     */
    private function visibleSections(): array
    {
        return $this->formFields()->visibleSections($this->sections(), $this->context(), $this->formState());
    }

    /**
     * @return array<int, Tab>
     */
    private function visibleTabs(): array
    {
        return $this->formFields()->visibleTabs($this->formDefinition()->getTabs(), $this->context(), $this->formState());
    }

    private function context(): ResourceContext
    {
        return $this->isEditMode ? ResourceContext::Edit : ResourceContext::Create;
    }

    private function formFields(): FormFields
    {
        return new FormFields;
    }

    private function formStateInitializer(): FormStateInitializer
    {
        return new FormStateInitializer;
    }

    private function formState(): FormState
    {
        return new FormState($this->fieldValues, $this->context(), $this->record);
    }

    private function initializeRepeaterUiKeys(): void
    {
        $this->repeaterUiKeys = [];
        $this->initializeRepeaterUiKeysFor('fieldValues', $this->fields(), $this->fieldValues);
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     */
    private function initializeRepeaterUiKeysFor(string $modelPath, array $fields, array $values): void
    {
        $this->repeaterUiKeys[$modelPath] = [];

        foreach ($fields as $field) {
            if (! $field instanceof RepeaterField) {
                continue;
            }

            $items = is_array($values[$field->name()] ?? null)
                ? $values[$field->name()]
                : [];
            $this->repeaterUiKeys[$modelPath][$field->name()] = array_map(
                fn (): string => $this->newRepeaterUiKey(),
                $items
            );
        }
    }

    private function newRepeaterUiKey(): string
    {
        return 'repeater-'.str_replace('-', '', (string) Str::uuid());
    }

    private function repeaterItemFocusId(RepeaterField $field, string $fieldName, string $uiKey): string
    {
        $itemField = $field->getItemFields()[0] ?? null;

        return 'field-'.$fieldName.'-'.$uiKey.($itemField instanceof Field ? '-'.$itemField->name() : '');
    }

    private function resetRepeaterValidation(string $fieldName, string $modelPath): void
    {
        $prefix = $modelPath.'.'.$fieldName;

        foreach ($this->getErrorBag()->keys() as $errorKey) {
            if ($errorKey === $prefix || str_starts_with($errorKey, $prefix.'.')) {
                $this->resetValidation($errorKey);
            }
        }
    }

    private function repeaterField(string $fieldName, string $modelPath): ?RepeaterField
    {
        $fields = $modelPath === 'fieldValues'
            ? $this->fields()
            : ($modelPath === 'inlineCreateValues' ? $this->inlineCreateState()['fields'] : []);

        $field = $this->findFieldInList($fields, $fieldName);

        return $field instanceof RepeaterField ? $field : null;
    }

    private function repeaterIsLocked(RepeaterField $field, string $modelPath): bool
    {
        $context = $modelPath === 'fieldValues' ? $this->context() : ResourceContext::Create;
        $disabled = (bool) (new ReflectionMethod($field, 'isDisabledForState'))
            ->invoke($field, $context, $this->formState());

        return $field->isReadonly($context) || $disabled;
    }

    /**
     * @return array<string, mixed>
     */
    private function &repeaterValues(string $modelPath): array
    {
        if ($modelPath === 'fieldValues') {
            return $this->fieldValues;
        }

        if ($modelPath === 'inlineCreateValues') {
            return $this->inlineCreateValues;
        }

        throw new \InvalidArgumentException('Unsupported repeater model path.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function &repeaterKeys(string $modelPath): array
    {
        $this->repeaterUiKeys[$modelPath] ??= [];

        return $this->repeaterUiKeys[$modelPath];
    }

    /**
     * @return array<string, mixed>
     */
    private function newRepeaterItem(RepeaterField $field, string $modelPath): array
    {
        $state = new FormState([], $modelPath === 'fieldValues' ? $this->context() : ResourceContext::Create);
        $item = [];

        foreach ($field->getItemFields() as $itemField) {
            $value = method_exists($itemField, 'hasDefault') && (bool) call_user_func([$itemField, 'hasDefault'])
                ? call_user_func([$itemField, 'getDefaultValue'], $state, null)
                : null;
            $item[$itemField->name()] = $value ?? ($itemField instanceof ToggleField ? false : '');
            $state = $state->withValues($item);
        }

        return $item;
    }

    /**
     * @param  array<int, Tab>  $tabs
     */
    private function resolvedActiveTab(array $tabs): ?string
    {
        if ($tabs === []) {
            return null;
        }

        foreach ($this->getErrorBag()->keys() as $errorKey) {
            if (! str_starts_with($errorKey, 'fieldValues.')) {
                continue;
            }

            $fieldName = explode('.', substr($errorKey, strlen('fieldValues.')), 2)[0];
            foreach ($tabs as $tab) {
                foreach ($tab->getAllFields() as $field) {
                    if ($field->name() === $fieldName) {
                        return $tab->getKey();
                    }
                }
            }
        }

        foreach ($tabs as $tab) {
            if ($tab->getKey() === $this->activeTab) {
                return $this->activeTab;
            }
        }

        return $tabs[0]->getKey();
    }

    private function canonicalRecordUrl(Model $record, TableUrlFactory $tableUrlFactory): string
    {
        if ($this->authorization()->canView(null, $record)) {
            return $tableUrlFactory->showUrl($this->resourceSlug, $record, $this->listContext);
        }

        return $tableUrlFactory->editUrl($this->resourceSlug, $record, $this->listContext);
    }

    private function assertRecordIsCurrent(): void
    {
        if (! $this->record instanceof Model || $this->recordVersion === null) {
            return;
        }

        $fresh = $this->query()->find($this->record->getKey());
        if (! $fresh instanceof Model) {
            throw new \LogicException('This record is no longer available.');
        }

        if ($this->recordVersion($fresh) !== $this->recordVersion) {
            throw new \LogicException('This record changed elsewhere. Your values were kept; reload before saving.');
        }

        $this->record = $fresh;
    }

    private function recordVersion(Model $record): string
    {
        $attributes = $record->getRawOriginal();
        ksort($attributes);

        return hash('sha256', serialize($attributes));
    }

    private function initializeSlugState(): void
    {
        $this->slugManualOverrides = [];
        $this->slugGeneratedValues = [];

        foreach ($this->fields() as $field) {
            if (! $field instanceof SlugField || $field->getSourceField() === null) {
                continue;
            }

            $slug = (string) ($this->fieldValues[$field->name()] ?? '');
            $this->slugManualOverrides[$field->name()] = $this->isEditMode && $slug !== '';
            $source = (string) ($this->fieldValues[$field->getSourceField()] ?? '');
            $this->slugGeneratedValues[$field->name()] = Str::slug($source);
        }
    }

    private function updateSlugState(string $changedField): void
    {
        foreach ($this->fields() as $field) {
            if (! $field instanceof SlugField || $field->getSourceField() === null) {
                continue;
            }

            if ($field->name() === $changedField) {
                $this->slugManualOverrides[$field->name()] = true;

                continue;
            }

            if ($field->getSourceField() !== $changedField || ($this->slugManualOverrides[$field->name()] ?? false)) {
                continue;
            }

            $currentSlug = (string) ($this->fieldValues[$field->name()] ?? '');
            $previousGenerated = $this->slugGeneratedValues[$field->name()] ?? '';
            if ($currentSlug !== '' && $currentSlug !== $previousGenerated) {
                $this->slugManualOverrides[$field->name()] = true;

                continue;
            }

            $generated = Str::slug((string) ($this->fieldValues[$changedField] ?? ''));
            $this->fieldValues[$field->name()] = $generated;
            $this->slugGeneratedValues[$field->name()] = $generated;
        }
    }

    private function refreshDirtyState(): void
    {
        $this->isDirty = $this->signature($this->fieldValues) !== $this->signature($this->originalFieldValues)
            || $this->fileActions !== $this->originalFileActions
            || $this->inlineCreateFieldName !== null
            || $this->inlineCreateValues !== [];
    }

    private function signature(mixed $value): string
    {
        try {
            return serialize($value);
        } catch (\Throwable) {
            return (string) json_encode($value, JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
    }

    private function formValidationRules(): FormValidationRules
    {
        return new FormValidationRules($this->formFields());
    }

    /**
     * @param  array<string, mixed>  $fieldValues
     * @return array<string, mixed>
     */
    private function applyFileActions(array $fieldValues): array
    {
        foreach ($this->fileActions as $fieldName => $action) {
            if ($action !== 'remove' || ! array_key_exists($fieldName, $fieldValues) || $fieldValues[$fieldName] !== null) {
                continue;
            }

            foreach ($this->fields() as $field) {
                if ($field instanceof FileField && $field->name() === $fieldName) {
                    $fieldValues[$fieldName] = ['action' => 'remove'];
                    break;
                }
            }
        }

        return $fieldValues;
    }

    /**
     * @return array<string, bool>
     */
    private function fileHasValues(): array
    {
        $values = [];

        foreach ($this->fields() as $field) {
            if (! $field instanceof FileField) {
                continue;
            }

            $value = $this->record?->getAttribute($field->name());
            $values[$field->name()] = is_string($value) && trim($value) !== '';
        }

        return $values;
    }

    /**
     * @return array<string, array{name:string, size:int|null, mime:string|null, image:bool}>
     */
    private function fileMetadata(): array
    {
        $metadata = [];

        foreach ($this->fields() as $field) {
            if (! $field instanceof FileField) {
                continue;
            }

            $path = $this->record?->getAttribute($field->name());
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
     * @return array<string, bool>
     */
    private function filePending(): array
    {
        $pending = [];

        foreach ($this->fields() as $field) {
            if ($field instanceof FileField) {
                $pending[$field->name()] = ($this->fieldValues[$field->name()] ?? null) instanceof UploadedFile;
            }
        }

        return $pending;
    }

    /**
     * @return array<string, string>
     */
    private function fileDownloadUrls(): array
    {
        if (! $this->record instanceof Model) {
            return [];
        }

        $urls = [];
        foreach ($this->visibleFields() as $field) {
            if (! $field instanceof FileField || ! $field->canRead(auth()->user(), $this->record)) {
                continue;
            }

            $value = $this->record->getAttribute($field->name());
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $urls[$field->name()] = route(
                config('seviaflow-ui.route_name_prefix', 'seviaflow.').'resources.files.download',
                [
                    'resource' => $this->resourceSlug,
                    'record' => $this->record->getKey(),
                    'field' => $field->name(),
                ]
            );
        }

        return $urls;
    }

    private function isVisibleFileField(string $fieldName): bool
    {
        foreach ($this->visibleFields() as $field) {
            if ($field instanceof FileField && $field->name() === $fieldName) {
                return true;
            }
        }

        return false;
    }

    private function findVisibleField(string $fieldName): ?Field
    {
        foreach ($this->visibleFields() as $field) {
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

    private function formPersistence(): FormPersistence
    {
        return new FormPersistence($this->formFields());
    }

    private function operationService(): ResourceOperationService
    {
        return new ResourceOperationService($this->formFields());
    }

    private function authorization(): ResourceAuthorization
    {
        return ResourceAuthorization::for($this->resource);
    }

    private function belongsToFieldOptions(): BelongsToFieldOptions
    {
        return new BelongsToFieldOptions;
    }

    /**
     * @param  class-string<\SeviaFlow\Core\Resources\Resource>  $resourceClass
     */
    private function resourceSlugForClass(string $resourceClass): string
    {
        foreach ((array) config('seviaflow.resources', []) as $slug => $class) {
            if (is_string($slug) && $class === $resourceClass) {
                return $slug;
            }
        }

        return $resourceClass;
    }

    /**
     * @return array{
     *   field: BelongsToField|null,
     *   resourceClass: class-string<\SeviaFlow\Core\Resources\Resource>|null,
     *   modelClass: class-string<Model>|null,
     *   fields: array<int, Field>,
     *   belongsToOptions: array<string, array<string, string>>,
     *   label: string|null,
     *   title: string|null,
     *   visible: bool
     * }
     */
    private function inlineCreateState(): array
    {
        $field = $this->findInlineCreateField($this->inlineCreateFieldName ?? '');
        $resourceClass = $field?->getInlineCreateResourceClass();
        $modelClass = null;
        $fields = [];
        $belongsToOptions = [];
        $label = null;
        $title = null;
        $visible = false;

        if ($field instanceof BelongsToField && $resourceClass !== null) {
            $label = $field->getInlineCreateLabel() ?? ('Create '.$field->getLabel());
            $title = 'Create '.$field->getLabel();
            $visible = ResourceAuthorization::for($resourceClass)->canCreate();

            if ($visible) {
                /** @var class-string<Model> $resolvedModelClass */
                $resolvedModelClass = $resourceClass::model();
                $modelClass = $resolvedModelClass;
                $fields = (new FormFields)->visible(
                    $resourceClass::form(new Form)->getAllFields(),
                    ResourceContext::Create
                );
                $belongsToOptions = $this->belongsToFieldOptions()->resolve(
                    $fields,
                    $resolvedModelClass,
                    [],
                    $this->inlineCreateValues
                );
            }
        }

        return [
            'field' => $field,
            'resourceClass' => $resourceClass,
            'modelClass' => $modelClass,
            'fields' => $fields,
            'belongsToOptions' => $belongsToOptions,
            'label' => $label,
            'title' => $title,
            'visible' => $visible,
        ];
    }

    private function findInlineCreateField(string $fieldName): ?BelongsToField
    {
        if ($fieldName === '') {
            return null;
        }

        foreach ($this->visibleFields() as $field) {
            if ($field instanceof BelongsToField && $field->name() === $fieldName) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<string, bool>
     */
    private function inlineCreateAllowedMap(): array
    {
        $map = [];

        foreach ($this->visibleFields() as $field) {
            if (! $field instanceof BelongsToField) {
                continue;
            }

            $resourceClass = $field->getInlineCreateResourceClass();
            $map[$field->name()] = $resourceClass !== null
                ? ResourceAuthorization::for($resourceClass)->canCreate()
                : false;
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function belongsToPlaceholderMap(): array
    {
        $placeholders = [];

        foreach ($this->visibleFields() as $field) {
            if (! $field instanceof BelongsToField || ! $field->isDependent()) {
                continue;
            }

            $parentField = (string) $field->getDependsOnField();
            $parentValue = $this->fieldValues[$parentField] ?? null;

            if ($parentValue === null || $parentValue === '') {
                $placeholders[$field->name()] = 'Select '.$this->humanizeFieldName($parentField).' first';
            }
        }

        return $placeholders;
    }

    private function humanizeFieldName(string $name): string
    {
        if (str_ends_with($name, '_id')) {
            $name = substr($name, 0, -3);
        }

        return strtolower(trim(str_replace('_', ' ', $name)));
    }
}
