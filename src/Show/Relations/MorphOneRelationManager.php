<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Relations\MorphOneRelation;

final class MorphOneRelationManager
{
    public function relationship(Model $parent, MorphOneRelation $relation): MorphOne
    {
        if (! method_exists($parent, $relation->getRelationshipName())) {
            throw new InvalidArgumentException(sprintf(
                'Relation [%s] on model [%s] must be an instance of [%s].',
                $relation->getRelationshipName(),
                $parent::class,
                MorphOne::class
            ));
        }

        $relationship = $parent->{$relation->getRelationshipName()}();

        if (! $relationship instanceof MorphOne) {
            throw new InvalidArgumentException(sprintf(
                'Relation [%s] on model [%s] must be an instance of [%s].',
                $relation->getRelationshipName(),
                $parent::class,
                MorphOne::class
            ));
        }

        return $relationship;
    }

    public function resolveRecord(Model $parent, MorphOneRelation $relation): ?Model
    {
        return $this->relationship($parent, $relation)->first();
    }

    public function resolveRecordById(Model $parent, MorphOneRelation $relation, int|string|null $recordId): ?Model
    {
        if ($recordId === null || $recordId === '') {
            return null;
        }

        return $this->relationship($parent, $relation)->whereKey($recordId)->first();
    }
}
