<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Relations\MorphManyRelation;

final class MorphManyRelationManager
{
    public function relationship(Model $parent, MorphManyRelation $relation): MorphMany
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

        return $relationship;
    }

    public function resolveRecord(Model $parent, MorphManyRelation $relation, int|string|null $recordId): ?Model
    {
        if ($recordId === null || $recordId === '') {
            return null;
        }

        return $this->relationship($parent, $relation)->whereKey($recordId)->first();
    }

    public function resolveRecordWithTrashed(Model $parent, MorphManyRelation $relation, int|string|null $recordId): ?Model
    {
        if ($recordId === null || $recordId === '') {
            return null;
        }

        $query = $this->relationship($parent, $relation)->getQuery();

        if ($relation->supportsSoftDeletes()) {
            call_user_func([$query, 'withTrashed']);
        }

        return $query->whereKey($recordId)->first();
    }
}
