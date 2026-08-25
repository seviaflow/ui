<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Relations;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Relations\BelongsToManyRelation;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Relations\HasOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphManyRelation;
use SeviaFlow\Core\Resources\Relations\MorphOneRelation;
use SeviaFlow\Core\Resources\Relations\MorphToManyRelation;

final class RelationBoundaryGuard
{
    public function __construct(
        private readonly HasOneRelationManager $hasOneManager = new HasOneRelationManager,
        private readonly HasManyRelationManager $hasManyManager = new HasManyRelationManager,
        private readonly MorphOneRelationManager $morphOneManager = new MorphOneRelationManager,
        private readonly MorphManyRelationManager $morphManyManager = new MorphManyRelationManager,
        private readonly BelongsToManyRelationManager $belongsToManyManager = new BelongsToManyRelationManager,
        private readonly MorphToManyRelationManager $morphToManyManager = new MorphToManyRelationManager,
    ) {}

    public function resolveHasOneRecord(Model $parent, HasOneRelation $relation): ?Model
    {
        return $this->hasOneManager->resolveRecord($parent, $relation);
    }

    public function resolveHasOneRecordById(Model $parent, HasOneRelation $relation, int|string|null $recordId): ?Model
    {
        return $this->hasOneManager->resolveRecordById($parent, $relation, $recordId);
    }

    public function resolveHasManyRecord(Model $parent, HasManyRelation $relation, int|string|null $recordId): ?Model
    {
        return $this->hasManyManager->resolveRecord($parent, $relation, $recordId);
    }

    public function resolveHasManyRecordWithTrashed(Model $parent, HasManyRelation $relation, int|string|null $recordId): ?Model
    {
        return $this->hasManyManager->resolveRecordWithTrashed($parent, $relation, $recordId);
    }

    public function resolveMorphOneRecord(Model $parent, MorphOneRelation $relation): ?Model
    {
        return $this->morphOneManager->resolveRecord($parent, $relation);
    }

    public function resolveMorphOneRecordById(Model $parent, MorphOneRelation $relation, int|string|null $recordId): ?Model
    {
        return $this->morphOneManager->resolveRecordById($parent, $relation, $recordId);
    }

    public function resolveMorphManyRecord(Model $parent, MorphManyRelation $relation, int|string|null $recordId): ?Model
    {
        return $this->morphManyManager->resolveRecord($parent, $relation, $recordId);
    }

    public function resolveMorphManyRecordWithTrashed(Model $parent, MorphManyRelation $relation, int|string|null $recordId): ?Model
    {
        return $this->morphManyManager->resolveRecordWithTrashed($parent, $relation, $recordId);
    }

    public function resolveAttachedBelongsToManyRecord(Model $parent, BelongsToManyRelation $relation, int|string|null $recordId): ?Model
    {
        return $this->belongsToManyManager->resolveAttachedRecord($parent, $relation, $recordId);
    }

    public function resolveAttachedMorphToManyRecord(Model $parent, MorphToManyRelation $relation, int|string|null $recordId): ?Model
    {
        return $this->morphToManyManager->resolveAttachedRecord($parent, $relation, $recordId);
    }
}
