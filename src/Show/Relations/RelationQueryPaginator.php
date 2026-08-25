<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Columns\Column;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Relations\BelongsToManyRelation;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Relations\MorphManyRelation;
use SeviaFlow\Core\Resources\Relations\MorphToManyRelation;

final class RelationQueryPaginator
{
    /**
     * @param  class-string  $relatedResourceClass
     */
    public function paginate(
        HasMany|BelongsToMany|MorphMany|MorphToMany $relationship,
        HasManyRelation|BelongsToManyRelation|MorphManyRelation|MorphToManyRelation $relation,
        string $relatedResourceClass
    ): LengthAwarePaginator|Paginator {
        $pivotColumnNames = [];
        $pivotFieldNames = [];
        $supportsPivot = false;

        if ($relationship instanceof HasMany && $relation instanceof HasManyRelation) {
            if ($relation->canRestore() && $relation->supportsSoftDeletes()) {
                if (method_exists($relationship, 'withTrashed')) {
                    $relationship->withTrashed();
                }
            }
        } elseif ($relationship instanceof MorphMany && $relation instanceof MorphManyRelation) {
            if ($relation->canRestore() && $relation->supportsSoftDeletes()) {
                if (method_exists($relationship, 'withTrashed')) {
                    $relationship->withTrashed();
                }
            }
        }

        if ($relationship instanceof BelongsToMany && $relation instanceof BelongsToManyRelation) {
            $supportsPivot = true;
            $pivotColumnNames = array_map(
                static fn (Column $column): string => $column->name(),
                $relation->getPivotColumns()
            );
            $pivotFieldNames = array_map(
                static fn (Field $field): string => $field->name(),
                $relation->getPivotFields()
            );
        } elseif ($relationship instanceof MorphToMany && $relation instanceof MorphToManyRelation) {
            $supportsPivot = true;
            $pivotColumnNames = array_map(
                static fn (Column $column): string => $column->name(),
                $relation->getPivotColumns()
            );
            $pivotFieldNames = array_map(
                static fn (Field $field): string => $field->name(),
                $relation->getPivotFields()
            );
        }

        $withPivot = array_values(array_unique(array_merge($pivotColumnNames, $pivotFieldNames)));

        if ($supportsPivot && $withPivot !== []) {
            $relationship->withPivot($withPivot);
        }

        $query = $relatedResourceClass::query($relationship->getQuery());
        $pageName = 'rel_'.preg_replace('/[^A-Za-z0-9_]/', '_', $relation->getRelationshipName());

        $paginator = $query->paginate($relation->getPerPage(), ['*'], $pageName);

        if ($supportsPivot) {
            $this->hydratePivotRelations($paginator->getCollection(), array_merge($pivotColumnNames, $pivotFieldNames));
        }

        return $paginator;
    }

    /**
     * @param  class-string  $relatedResourceClass
     */
    public function paginateHasMany(Model $parent, HasManyRelation $relation, string $relatedResourceClass): LengthAwarePaginator|Paginator
    {
        if (! method_exists($parent, $relation->getRelationshipName())) {
            throw new InvalidArgumentException(sprintf(
                'Relation [%s] on model [%s] must be an instance of [%s].',
                $relation->getRelationshipName(),
                $parent::class,
                HasMany::class
            ));
        }

        $relationship = $parent->{$relation->getRelationshipName()}();

        if (! $relationship instanceof HasMany) {
            throw new InvalidArgumentException(sprintf(
                'Relation [%s] on model [%s] must be an instance of [%s].',
                $relation->getRelationshipName(),
                $parent::class,
                HasMany::class
            ));
        }

        return $this->paginate($relationship, $relation, $relatedResourceClass);
    }

    /**
     * @param  class-string  $relatedResourceClass
     */
    public function paginateBelongsToMany(Model $parent, BelongsToManyRelation $relation, string $relatedResourceClass): LengthAwarePaginator|Paginator
    {
        if (! method_exists($parent, $relation->getRelationshipName())) {
            throw new InvalidArgumentException(sprintf(
                'Relation [%s] on model [%s] must be an instance of [%s].',
                $relation->getRelationshipName(),
                $parent::class,
                BelongsToMany::class
            ));
        }

        $relationship = $parent->{$relation->getRelationshipName()}();

        if (! $relationship instanceof BelongsToMany) {
            throw new InvalidArgumentException(sprintf(
                'Relation [%s] on model [%s] must be an instance of [%s].',
                $relation->getRelationshipName(),
                $parent::class,
                BelongsToMany::class
            ));
        }

        return $this->paginate($relationship, $relation, $relatedResourceClass);
    }

    /**
     * @param  class-string  $relatedResourceClass
     */
    public function paginateMorphMany(Model $parent, MorphManyRelation $relation, string $relatedResourceClass): LengthAwarePaginator|Paginator
    {
        if (! method_exists($parent, $relation->getRelationshipName())) {
            throw new InvalidArgumentException(sprintf(
                'Relation [%s] on model [%s] must be an instance of [%s].',
                $relation->getRelationshipName(),
                $parent::class,
                MorphMany::class
            ));
        }

        $relationship = $parent->{$relation->getRelationshipName()}();

        if (! $relationship instanceof MorphMany) {
            throw new InvalidArgumentException(sprintf(
                'Relation [%s] on model [%s] must be an instance of [%s].',
                $relation->getRelationshipName(),
                $parent::class,
                MorphMany::class
            ));
        }

        return $this->paginate($relationship, $relation, $relatedResourceClass);
    }

    /**
     * @param  class-string  $relatedResourceClass
     */
    public function paginateMorphToMany(Model $parent, MorphToManyRelation $relation, string $relatedResourceClass): LengthAwarePaginator|Paginator
    {
        if (! method_exists($parent, $relation->getRelationshipName())) {
            throw new InvalidArgumentException(sprintf(
                'Relation [%s] on model [%s] must be an instance of [%s].',
                $relation->getRelationshipName(),
                $parent::class,
                MorphToMany::class
            ));
        }

        $relationship = $parent->{$relation->getRelationshipName()}();

        if (! $relationship instanceof MorphToMany) {
            throw new InvalidArgumentException(sprintf(
                'Relation [%s] on model [%s] must be an instance of [%s].',
                $relation->getRelationshipName(),
                $parent::class,
                MorphToMany::class
            ));
        }

        return $this->paginate($relationship, $relation, $relatedResourceClass);
    }

    /**
     * @param  iterable<int, Model>  $records
     * @param  array<int, string>  $pivotAttributeNames
     */
    private function hydratePivotRelations(iterable $records, array $pivotAttributeNames): void
    {
        $pivotAttributeNames = array_values(array_unique(array_filter($pivotAttributeNames, static fn (string $name): bool => trim($name) !== '')));

        if ($pivotAttributeNames === []) {
            return;
        }

        foreach ($records as $record) {
            if (! $record instanceof Model) {
                continue;
            }

            $pivotValues = [];

            foreach ($pivotAttributeNames as $attributeName) {
                $value = $record->getAttribute('pivot_'.$attributeName);

                if ($value === null) {
                    $value = $record->getAttribute($attributeName);
                }

                if ($value !== null) {
                    $pivotValues[$attributeName] = $value;
                }
            }

            if ($pivotValues === []) {
                continue;
            }

            $pivot = new Pivot;
            $pivot->setRawAttributes($pivotValues, true);
            $record->setRelation('pivot', $pivot);
        }
    }
}
