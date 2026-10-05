<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use SeviaFlow\Core\Forms\BelongsToFieldOptions;
use SeviaFlow\Core\Forms\FormFields;
use SeviaFlow\Core\Forms\FormStateInitializer;
use SeviaFlow\Core\Operations\ResourceOperationContext;
use SeviaFlow\Core\Operations\ResourceOperationService;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Relations\HasOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphManyRelation;
use SeviaFlow\Core\Resources\Relations\MorphOneRelation;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceEventContext;

final class RelationModalFormHandler
{
    public function __construct(
        private readonly ResourceOperationService $operationService = new ResourceOperationService,
    ) {}

    /**
     * @param  array<int, Field>  $fields
     * @return array<string, mixed>
     */
    public function initializeValuesFromFields(array $fields, ?Model $record): array
    {
        return (new FormStateInitializer)->initialize($fields, $record);
    }

    /**
     * @return array<int, Field>
     */
    public function relationModalFields(HasOneRelation|HasManyRelation|MorphOneRelation|MorphManyRelation $relation, ResourceContext $context): array
    {
        $resourceClass = $relation->getResourceClass();
        $fields = $resourceClass::form(new Form)->getAllFields();

        return (new FormFields)->visible($fields, $context);
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $currentValues
     * @param  array<string, string>  $searchTerms
     * @return array<string, array<string, string>>
     */
    public function belongsToOptions(array $fields, string $modelClass, array $currentValues, array $searchTerms = []): array
    {
        return (new BelongsToFieldOptions)->resolve($fields, $modelClass, $searchTerms, $currentValues);
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     */
    public function fillModelWithPersistableValues(Model $model, array $fields, array $values, ResourceContext $context): void
    {
        $this->operationService->applyValues($model, $fields, $values, $context);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     * @param  class-string<\SeviaFlow\Core\Resources\Resource>|null  $resourceClass
     */
    public function createRelatedRecord(
        HasOne|HasMany|MorphOne|MorphMany $relationship,
        string $modelClass,
        array $fields,
        array $values,
        ?string $resourceClass = null,
        ?string $resourceSlug = null,
        ?ResourceOperationContext $operationContext = null,
    ): Model {
        $model = new $modelClass;

        if ($resourceClass === null) {
            $this->fillModelWithPersistableValues($model, $fields, $values, ResourceContext::Create);
            $relationship->save($model);

            return $model;
        }

        $operationContext ??= new ResourceOperationContext(
            source: ResourceEventContext::SOURCE_RELATION,
        );

        return $this->operationService->execute(
            resourceClass: $resourceClass,
            resourceSlug: $resourceSlug ?? $resourceClass,
            model: $model,
            fields: $fields,
            values: $values,
            context: ResourceContext::Create,
            operationContext: $operationContext,
            save: static function (Model $record) use ($relationship): void {
                $relationship->save($record);
            },
        );
    }

    /**
     * @param  class-string<\SeviaFlow\Core\Resources\Resource>  $resourceClass
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     */
    public function updateRelatedRecord(
        string $resourceClass,
        string $resourceSlug,
        Model $model,
        array $fields,
        array $values,
        ResourceOperationContext $operationContext,
    ): Model {
        return $this->operationService->execute(
            resourceClass: $resourceClass,
            resourceSlug: $resourceSlug,
            model: $model,
            fields: $fields,
            values: $values,
            context: ResourceContext::Edit,
            operationContext: $operationContext,
        );

    }
}
