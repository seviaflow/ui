<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Relations\HasOneRelation;

final class HasOneRelationManager
{
    public function relationship(Model $parent, HasOneRelation $relation): HasOne
    {
        if (! method_exists($parent, $relation->getRelationshipName())) {
            throw new InvalidArgumentException(sprintf(
                'Relation [%s] on model [%s] must be an instance of [%s].',
                $relation->getRelationshipName(),
                $parent::class,
                HasOne::class
            ));
        }

        $relationship = $parent->{$relation->getRelationshipName()}();

        if (! $relationship instanceof HasOne) {
            throw new InvalidArgumentException(sprintf(
                'Relation [%s] on model [%s] must be an instance of [%s].',
                $relation->getRelationshipName(),
                $parent::class,
                HasOne::class
            ));
        }

        return $relationship;
    }

    public function resolveRecord(Model $parent, HasOneRelation $relation): ?Model
    {
        return $this->relationship($parent, $relation)->first();
    }

    public function resolveRecordById(Model $parent, HasOneRelation $relation, int|string|null $recordId): ?Model
    {
        if ($recordId === null || $recordId === '') {
            return null;
        }

        return $this->relationship($parent, $relation)->whereKey($recordId)->first();
    }
}
