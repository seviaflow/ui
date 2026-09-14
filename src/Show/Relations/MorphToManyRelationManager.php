<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Relations\MorphToManyRelation;
use SeviaFlow\Core\Resources\ResourceScopedQuery;

final class MorphToManyRelationManager
{
    public function relationship(Model $parent, MorphToManyRelation $relation): MorphToMany
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

        return $relationship;
    }

    /**
     * @param  class-string  $resourceClass
     * @return array<string, string>
     */
    public function attachOptions(Model $parent, MorphToManyRelation $relation, string $resourceClass): array
    {
        $relationship = $this->relationship($parent, $relation);
        $relatedModel = $relationship->getRelated();
        $ownerKey = $relatedModel->getKeyName();
        $titleAttribute = $relation->getTitleAttribute();

        $attachedIds = $relationship->newPivotQuery()
            ->pluck($relationship->getRelatedPivotKeyName())
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        $query = ResourceScopedQuery::make(
            $resourceClass,
            $relatedModel->newQuery()->select([$ownerKey, $titleAttribute])
        );

        if ($attachedIds !== []) {
            $query->whereNotIn($ownerKey, $attachedIds);
        }

        $records = $query
            ->orderBy($ownerKey)
            ->limit($relation->getOptionsLimit())
            ->get();

        $options = [];
        foreach ($records as $record) {
            $options[(string) $record->getKey()] = (string) $record->getAttribute($titleAttribute);
        }

        return $options;
    }

    public function resolveAttachedRecord(Model $parent, MorphToManyRelation $relation, int|string|null $relatedRecordId): ?Model
    {
        if ($relatedRecordId === null || $relatedRecordId === '') {
            return null;
        }

        return ResourceScopedQuery::find(
            $relation->getResourceClass(),
            $relatedRecordId,
            ResourceScopedQuery::ACTIVE,
            $this->relationship($parent, $relation)->getQuery()
        );
    }

    public function resolveRelatedRecord(Model $parent, MorphToManyRelation $relation, int|string|null $relatedRecordId): ?Model
    {
        if ($relatedRecordId === null || $relatedRecordId === '') {
            return null;
        }

        return ResourceScopedQuery::find(
            $relation->getResourceClass(),
            $relatedRecordId
        );
    }

    /**
     * @param  class-string  $resourceClass
     */
    public function resolveAttachableRecord(Model $parent, MorphToManyRelation $relation, int|string $relatedRecordId, string $resourceClass): ?Model
    {
        $relatedId = (string) $relatedRecordId;

        if ($relatedId === '') {
            return null;
        }

        $availableIds = array_keys($this->attachOptions($parent, $relation, $resourceClass));
        $availableIds = array_map(static fn (mixed $value): string => (string) $value, $availableIds);

        if (! in_array($relatedId, $availableIds, true)) {
            return null;
        }

        return ResourceScopedQuery::find($resourceClass, $relatedId);
    }

    public function isAttached(Model $parent, MorphToManyRelation $relation, int|string $relatedRecordId): bool
    {
        $relationship = $this->relationship($parent, $relation);

        return $relationship
            ->newPivotQuery()
            ->where($relationship->getRelatedPivotKeyName(), $relatedRecordId)
            ->exists();
    }

    public function attach(Model $parent, MorphToManyRelation $relation, int|string $relatedRecordId, array $pivotValues = []): void
    {
        $this->relationship($parent, $relation)->attach($relatedRecordId, $pivotValues);
    }

    public function detach(Model $parent, MorphToManyRelation $relation, int|string $relatedRecordId): void
    {
        $this->relationship($parent, $relation)->detach($relatedRecordId);
    }
}
