<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use SeviaFlow\Core\Exceptions\ResourceSlugNotRegisteredException;
use SeviaFlow\Core\Forms\BelongsToFieldOptions;
use SeviaFlow\Core\Forms\FormFields;
use SeviaFlow\Core\Forms\FormPersistence;
use SeviaFlow\Core\Forms\FormStateInitializer;
use SeviaFlow\Core\Forms\FormValidationRules;
use SeviaFlow\Core\Resources\Fields\BelongsToField;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\ResourceAuthorization;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Resources\Section;

final class ResourceForm extends Component
{
    use WithFileUploads;

    public string $resourceSlug;

    /**
     * @var class-string
     */
    public string $resource;

    /**
     * @var class-string<Model>
     */
    public string $modelClass;

    /**
     * @var Model|null
     */
    public $record = null;

    public bool $isEditMode = false;

    /**
     * @var array<string, mixed>
     */
    public array $fieldValues = [];

    /**
     * @var array<string, string>
     */
    public array $belongsToSearch = [];

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

        $this->initializeFieldValues();
    }

    public function render(): View
    {
        return view('seviaflow-ui::livewire.resource-form', [
            'fields' => $this->visibleFields(),
            'sections' => $this->visibleSections(),
            'belongsToOptions' => $this->belongsToFieldOptions()->resolve(
                $this->visibleFields(),
                $this->modelClass,
                $this->belongsToSearch,
                $this->fieldValues
            ),
            'belongsToPlaceholder' => $this->belongsToPlaceholderMap(),
            'inlineCreateAllowed' => $this->inlineCreateAllowedMap(),
            'inlineCreateState' => $this->inlineCreateState(),
            'context' => $this->context(),
            'cancelUrl' => route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'resources.index', [
                'resource' => $this->resourceSlug,
            ]),
        ])->layout('seviaflow-ui::components.app.layout', [
            'contentWidth' => 'standard',
        ]);
    }

    public function save(): void
    {
        $this->errorMessage = null;

        if ($this->record instanceof Model) {
            if (! $this->authorization()->canUpdate(null, $this->record)) {
                abort(403);
            }
        } elseif (! $this->authorization()->canCreate()) {
            abort(403);
        }

        $validated = $this->validate($this->rules(), messages: [], attributes: $this->validationAttributes());

        $model = $this->record ?? new $this->modelClass;
        $fieldValues = is_array($validated['fieldValues'] ?? null) ? $validated['fieldValues'] : [];
        $this->statusMessage = null;

        try {
            $this->record = $this->formPersistence()->persistWithResourceEvents(
                resourceClass: $this->resource,
                resourceSlug: $this->resourceSlug,
                model: $model,
                fields: $this->fields(),
                validatedFieldValues: $fieldValues,
                context: $this->context(),
                user: auth()->user(),
                request: request()
            );
            $this->isEditMode = true;
            $this->initializeFieldValues();
            $this->statusMessage = 'Record saved successfully.';
        } catch (\InvalidArgumentException|\LogicException $exception) {
            $this->errorMessage = $exception->getMessage();
        } catch (\Throwable $exception) {
            report($exception);
            $this->errorMessage = 'Saving failed. Please try again.';
        }
    }

    public function updatedFieldValues(mixed $value, string $fieldPath): void
    {
        $changedField = str_starts_with($fieldPath, 'fieldValues.')
            ? substr($fieldPath, strlen('fieldValues.'))
            : $fieldPath;

        if ($changedField === '') {
            return;
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
        $fields = $this->inlineCreateState()['fields'];
        $this->inlineCreateValues = $this->formStateInitializer()->initialize($fields, null);
        $this->resetErrorBag();
    }

    public function cancelInlineCreate(): void
    {
        $this->inlineCreateFieldName = null;
        $this->inlineCreateValues = [];
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

        $validated = $this->validate(
            $this->formValidationRules()->build(
                $fields,
                ResourceContext::Create,
                $modelClass,
                'inlineCreateValues',
                $this->inlineCreateValues
            ),
            messages: [],
            attributes: $this->formValidationRules()->attributes($fields, ResourceContext::Create, 'inlineCreateValues')
        );

        $values = is_array($validated['inlineCreateValues'] ?? null) ? $validated['inlineCreateValues'] : [];
        $model = new $modelClass;

        $created = $this->formPersistence()->persist(
            $model,
            $fields,
            $values,
            ResourceContext::Create
        );

        $this->fieldValues[$field->name()] = (string) $created->getKey();
        $this->statusMessage = 'Related record created successfully.';
        $this->cancelInlineCreate();
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
            $this->record
        );
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return $this->formValidationRules()->attributes($this->fields(), $this->context());
    }

    private function initializeFieldValues(): void
    {
        $this->fieldValues = $this->formStateInitializer()->initialize($this->fields(), $this->record);
        $this->belongsToSearch = [];
        $this->inlineCreateFieldName = null;
        $this->inlineCreateValues = [];
    }

    private function query(): Builder
    {
        $model = new $this->modelClass;

        return $model->newQuery();
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
        return $this->formFields()->visible($this->fields(), $this->context());
    }

    /**
     * @return array<int, Section>
     */
    private function visibleSections(): array
    {
        return $this->formFields()->visibleSections($this->sections(), $this->context());
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

    private function formValidationRules(): FormValidationRules
    {
        return new FormValidationRules($this->formFields());
    }

    private function formPersistence(): FormPersistence
    {
        return new FormPersistence($this->formFields());
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
