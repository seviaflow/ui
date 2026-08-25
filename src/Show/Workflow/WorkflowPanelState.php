<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Workflow;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Workflows\Transition;
use SeviaFlow\Core\Resources\Workflows\Workflow;
use SeviaFlow\Core\Workflows\WorkflowActivity;
use SeviaFlow\Core\Workflows\WorkflowTransitionResolver;

final class WorkflowPanelState
{
    public function __construct(
        private readonly WorkflowTransitionResolver $transitionResolver = new WorkflowTransitionResolver,
        private readonly WorkflowTimelineResolver $timelineResolver = new WorkflowTimelineResolver
    ) {}

    /**
     * @param  class-string<resource>  $resourceClass
     * @return array{
     *   visible: bool,
     *   currentStateLabel: string|null,
     *   currentStateColor: string|null,
     *   availableTransitions: array<int, Transition>,
     *   activities: Collection<int, WorkflowActivity>,
     *   userLabel: (
     *      Closure(WorkflowActivity): string
     *   )
     * }
     */
    public function resolve(string $resourceClass, ?Model $record, mixed $user = null): array
    {
        $workflow = $this->workflowDefinition($resourceClass);

        if (! $workflow instanceof Workflow || ! $record instanceof Model) {
            return [
                'visible' => false,
                'currentStateLabel' => null,
                'currentStateColor' => null,
                'availableTransitions' => [],
                'activities' => collect(),
                'userLabel' => fn (WorkflowActivity $activity): string => 'System',
            ];
        }

        $currentStateName = $this->transitionResolver->currentState($resourceClass, $record);
        $state = $currentStateName !== null ? $workflow->getState($currentStateName) : null;
        $currentStateLabel = $state?->getLabel() ?? ($currentStateName !== null ? ucfirst(str_replace('_', ' ', $currentStateName)) : 'Unknown');

        return [
            'visible' => true,
            'currentStateLabel' => $currentStateLabel,
            'currentStateColor' => $state?->getColor(),
            'availableTransitions' => $this->transitionResolver->availableTransitions($resourceClass, $record, $user),
            'activities' => $this->timelineResolver->resolve($record),
            'userLabel' => fn (WorkflowActivity $activity): string => $this->timelineResolver->userLabel($activity),
        ];
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function workflowDefinition(string $resourceClass): ?Workflow
    {
        $workflow = $resourceClass::workflow(new Workflow);

        if ($workflow->getStates() === [] || $workflow->getTransitions() === []) {
            return null;
        }

        return $workflow;
    }
}
