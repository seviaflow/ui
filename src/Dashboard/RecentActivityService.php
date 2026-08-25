<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceAuthorization;
use SeviaFlow\Core\Resources\Workflows\Workflow;
use SeviaFlow\Core\Workflows\WorkflowActivity;

final class RecentActivityService
{
    /**
     * @param  array<string, class-string<resource>>  $configuredResources
     * @return array<int, array{
     *     resourceLabel: string,
     *     transition: string,
     *     fromState: string|null,
     *     toState: string,
     *     recordIdentifier: string,
     *     userLabel: string,
     *     comment: string|null,
     *     happenedAt: string|null
     * }>
     */
    public function items(array $configuredResources, ?Authenticatable $user, int $limit = 10): array
    {
        if (! $user instanceof Authenticatable) {
            return [];
        }

        if (! Schema::hasTable('seviaflow_workflow_activities')) {
            return [];
        }

        $limit = max(1, min($limit, 50));
        $authorizedResources = $this->authorizedWorkflowResources($configuredResources, $user);

        if ($authorizedResources === []) {
            return [];
        }

        $activities = WorkflowActivity::query()
            ->whereIn('resource', array_keys($authorizedResources))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $items = [];
        foreach ($activities as $activity) {
            $resourceClass = (string) $activity->resource;
            $resourceLabel = $authorizedResources[$resourceClass]['label'] ?? class_basename($resourceClass);
            $workflow = $authorizedResources[$resourceClass]['workflow'] ?? null;

            $transitionLabel = (string) str((string) $activity->transition)
                ->replace(['_', '-'], ' ')
                ->lower()
                ->ucfirst();
            $fromState = $activity->from_state;
            $toState = $activity->to_state;

            if ($workflow instanceof Workflow) {
                $transition = $workflow->getTransition((string) $activity->transition);
                if ($transition !== null) {
                    $transitionLabel = $transition->getLabel();
                }

                $fromStateModel = $fromState !== null ? $workflow->getState($fromState) : null;
                if ($fromStateModel !== null) {
                    $fromState = $fromStateModel->getLabel();
                }

                $toStateModel = $workflow->getState((string) $toState);
                if ($toStateModel !== null) {
                    $toState = $toStateModel->getLabel();
                }
            }

            $items[] = [
                'resourceLabel' => $resourceLabel,
                'transition' => $transitionLabel,
                'fromState' => $fromState,
                'toState' => $toState,
                'recordIdentifier' => $this->recordIdentifier($activity),
                'userLabel' => $this->userLabel($activity),
                'comment' => $activity->comment,
                'happenedAt' => $activity->created_at?->toDateTimeString(),
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, class-string<resource>>  $configuredResources
     * @return array<class-string<resource>, array{label: string, workflow: Workflow}>
     */
    private function authorizedWorkflowResources(array $configuredResources, ?Authenticatable $user): array
    {
        $result = [];

        foreach ($configuredResources as $slug => $resourceClass) {
            if (! is_string($slug) || $slug === '' || ! is_string($resourceClass) || ! is_a($resourceClass, Resource::class, true)) {
                continue;
            }

            if (! ResourceAuthorization::for($resourceClass)->canViewAny($user)) {
                continue;
            }

            $workflow = $resourceClass::workflow(new Workflow);
            if ($workflow->getStates() === []) {
                continue;
            }

            $result[$resourceClass] = [
                'label' => $this->resourceLabel($slug, $resourceClass),
                'workflow' => $workflow,
            ];
        }

        return $result;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function resourceLabel(string $slug, string $resourceClass): string
    {
        if (method_exists($resourceClass, 'pluralLabel')) {
            $label = $resourceClass::pluralLabel();
            if (is_string($label) && trim($label) !== '') {
                return $label;
            }
        }

        if (method_exists($resourceClass, 'label')) {
            $label = $resourceClass::label();
            if (is_string($label) && trim($label) !== '') {
                return $label;
            }
        }

        return (string) str($slug)->replace('-', ' ')->headline();
    }

    private function recordIdentifier(WorkflowActivity $activity): string
    {
        return sprintf('%s #%s', class_basename((string) $activity->record_type), (string) $activity->record_id);
    }

    private function userLabel(WorkflowActivity $activity): string
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

        $email = $user->getAttribute('email');
        if (is_string($email) && trim($email) !== '') {
            return $email;
        }

        return 'User #'.$activity->user_id;
    }
}
