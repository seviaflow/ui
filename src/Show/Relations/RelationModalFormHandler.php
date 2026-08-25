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
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Relations\HasOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphManyRelation;
use SeviaFlow\Core\Resources\Relations\MorphOneRelation;
use SeviaFlow\Core\Resources\ResourceContext;

final class RelationModalFormHandler
{
    /**
     * @param  array<int, Field>  $fields
     * @return array<string, mixed>
     */
    public function initializeValuesFromFields(array $fields, ?Model $record): array
    {
        $values = [];

        foreach ($fields as $field) {
            $name = $field->name();
            $value = $record?->getAttribute($name);

            if ($field instanceof ToggleField) {
                $values[$name] = (bool) $value;

                continue;
            }

            $values[$name] = $value === null ? '' : (string) $value;
        }

        return $values;
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
     * @return array<string, array<string, string>>
     */
    public function belongsToOptions(array $fields, string $modelClass, array $currentValues): array
    {
        return (new BelongsToFieldOptions)->resolve($fields, $modelClass, [], $currentValues);
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     */
    public function fillModelWithPersistableValues(Model $model, array $fields, array $values, ResourceContext $context): void
    {
        foreach ((new FormFields)->persistable($fields, $context) as $field) {
            $value = $values[$field->name()] ?? null;

            if ($field instanceof ToggleField) {
                $model->setAttribute($field->name(), (bool) $value);

                continue;
            }

            if ($field instanceof NumberField) {
                if ($value === null || $value === '') {
                    $model->setAttribute($field->name(), null);

                    continue;
                }

                if (is_int($value) || is_float($value)) {
                    $model->setAttribute($field->name(), $value);

                    continue;
                }

                $model->setAttribute($field->name(), str_contains((string) $value, '.') ? (float) $value : (int) $value);

                continue;
            }

            $model->setAttribute($field->name(), $value === '' ? null : $value);
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     */
    public function createRelatedRecord(HasOne|HasMany|MorphOne|MorphMany $relationship, string $modelClass, array $fields, array $values): Model
    {
        $model = new $modelClass;
        $this->fillModelWithPersistableValues($model, $fields, $values, ResourceContext::Create);
        $relationship->save($model);

        return $model;
    }
}
