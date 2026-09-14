<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\ResourceScopedQuery;

final class HasManyRelationManager
{
    public function relationship(Model $parent, HasManyRelation $relation): HasMany
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

        return $relationship;
    }

    public function resolveRecord(Model $parent, HasManyRelation $relation, int|string|null $recordId): ?Model
    {
        if ($recordId === null || $recordId === '') {
            return null;
        }

        return ResourceScopedQuery::find(
            $relation->getResourceClass(),
            $recordId,
            ResourceScopedQuery::ACTIVE,
            $this->relationship($parent, $relation)->getQuery()
        );
    }

    public function resolveRecordWithTrashed(Model $parent, HasManyRelation $relation, int|string|null $recordId): ?Model
    {
        if ($recordId === null || $recordId === '') {
            return null;
        }

        return ResourceScopedQuery::find(
            $relation->getResourceClass(),
            $recordId,
            $relation->supportsSoftDeletes()
                ? ResourceScopedQuery::WITH_TRASHED
                : ResourceScopedQuery::ACTIVE,
            $this->relationship($parent, $relation)->getQuery()
        );
    }
}
