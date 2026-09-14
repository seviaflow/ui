<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Workflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SeviaFlow\Core\Workflows\WorkflowActivity;

final class WorkflowTimelineResolver
{
    /**
     * @return Collection<int, WorkflowActivity>
     */
    public function resolve(Model $record, int $limit = 10): Collection
    {
        $activity = new WorkflowActivity;
        $activity->setConnection($record->getConnectionName());

        return $activity->newQuery()
            ->forRecord($record)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function userLabel(WorkflowActivity $activity): string
    {
        if ($activity->user_type === null || $activity->user_id === null) {
            return 'System';
        }

        if (! class_exists($activity->user_type) || ! is_subclass_of($activity->user_type, Model::class)) {
            return 'User #'.$activity->user_id;
        }

        $user = $activity->user_type::query()->find($activity->user_id);
        if (! $user instanceof Model) {
            return 'User #'.$activity->user_id;
        }

        $name = $user->getAttribute('name');
        if (is_string($name) && trim($name) !== '') {
            return $name;
        }

        return 'User #'.$activity->user_id;
    }
}
