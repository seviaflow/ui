<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Relations;

use SeviaFlow\Core\Resources\Relations\BelongsToManyRelation;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Relations\HasOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphManyRelation;
use SeviaFlow\Core\Resources\Relations\MorphOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphToManyRelation;

final class RelationManagerResolver
{
    /**
     * @param  class-string  $resourceClass
     * @return array<int, HasOneRelation|HasManyRelation|BelongsToManyRelation|MorphOneRelation|MorphManyRelation|MorphToManyRelation>
     */
    public function relations(string $resourceClass): array
    {
        $relations = [];

        foreach ($resourceClass::relations() as $relation) {
            if ($relation instanceof HasOneRelation || $relation instanceof HasManyRelation || $relation instanceof BelongsToManyRelation || $relation instanceof MorphOneRelation || $relation instanceof MorphManyRelation || $relation instanceof MorphToManyRelation) {
                $relations[] = $relation;
            }
        }

        return $relations;
    }

    /**
     * @param  class-string  $resourceClass
     */
    public function findHasOne(string $resourceClass, string $relationshipName): ?HasOneRelation
    {
        foreach ($this->relations($resourceClass) as $relation) {
            if ($relation instanceof HasOneRelation && $relation->getRelationshipName() === $relationshipName) {
                return $relation;
            }
        }

        return null;
    }

    /**
     * @param  class-string  $resourceClass
     */
    public function findHasMany(string $resourceClass, string $relationshipName): ?HasManyRelation
    {
        foreach ($this->relations($resourceClass) as $relation) {
            if ($relation instanceof HasManyRelation && $relation->getRelationshipName() === $relationshipName) {
                return $relation;
            }
        }

        return null;
    }

    /**
     * @param  class-string  $resourceClass
     */
    public function findBelongsToMany(string $resourceClass, string $relationshipName): ?BelongsToManyRelation
    {
        foreach ($this->relations($resourceClass) as $relation) {
            if ($relation instanceof BelongsToManyRelation && $relation->getRelationshipName() === $relationshipName) {
                return $relation;
            }
        }

        return null;
    }

    /**
     * @param  class-string  $resourceClass
     */
    public function findMorphToMany(string $resourceClass, string $relationshipName): ?MorphToManyRelation
    {
        foreach ($this->relations($resourceClass) as $relation) {
            if ($relation instanceof MorphToManyRelation && $relation->getRelationshipName() === $relationshipName) {
                return $relation;
            }
        }

        return null;
    }

    /**
     * @param  class-string  $resourceClass
     */
    public function findMorphOne(string $resourceClass, string $relationshipName): ?MorphOneRelation
    {
        foreach ($this->relations($resourceClass) as $relation) {
            if ($relation instanceof MorphOneRelation && $relation->getRelationshipName() === $relationshipName) {
                return $relation;
            }
        }

        return null;
    }

    /**
     * @param  class-string  $resourceClass
     */
    public function findMorphMany(string $resourceClass, string $relationshipName): ?MorphManyRelation
    {
        foreach ($this->relations($resourceClass) as $relation) {
            if ($relation instanceof MorphManyRelation && $relation->getRelationshipName() === $relationshipName) {
                return $relation;
            }
        }

        return null;
    }
}
