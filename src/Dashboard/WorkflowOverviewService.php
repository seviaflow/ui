<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceAuthorization;
use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Workflow;

final class WorkflowOverviewService
{
    /**
     * @param  array<string, class-string<resource>>  $configuredResources
     * @return array<int, array{
     *     slug: string,
     *     label: string,
     *     stateField: string,
     *     states: array<int, string>,
     *     url: string,
     *     attentionCount: int|null
     * }>
     */
    public function items(array $configuredResources, ?Authenticatable $user, string $routeNamePrefix, bool $showAttentionCounts): array
    {
        if (! $user instanceof Authenticatable) {
            return [];
        }

        $items = [];

        foreach ($configuredResources as $slug => $resourceClass) {
            if (! is_string($slug) || $slug === '' || ! is_string($resourceClass) || ! is_a($resourceClass, Resource::class, true)) {
                continue;
            }

            if (! ResourceAuthorization::for($resourceClass)->canViewAny($user)) {
                continue;
            }

            $workflow = $resourceClass::workflow(new Workflow);
            $states = $workflow->getStates();

            if ($states === []) {
                continue;
            }

            $items[] = [
                'slug' => $slug,
                'label' => $this->resourceLabel($slug, $resourceClass),
                'stateField' => $workflow->getStateField(),
                'states' => array_map(static fn (State $state): string => $state->getLabel(), $states),
                'url' => route($routeNamePrefix.'resources.index', ['resource' => $slug]),
                'attentionCount' => $showAttentionCounts
                    ? $this->attentionCount($resourceClass, $workflow)
                    : null,
            ];
        }

        usort($items, static fn (array $left, array $right): int => strcmp($left['label'], $right['label']));

        return $items;
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

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function attentionCount(string $resourceClass, Workflow $workflow): ?int
    {
        $finalStates = array_values(array_map(
            static fn (State $state): string => $state->name(),
            array_filter($workflow->getStates(), static fn (State $state): bool => $state->isFinal())
        ));

        if ($finalStates === []) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $resourceClass::model();
        $stateField = $workflow->getStateField();

        $query = $resourceClass::query($modelClass::query());

        return (int) $query
            ->where(function ($builder) use ($stateField, $finalStates): void {
                $builder
                    ->whereNull($stateField)
                    ->orWhereNotIn($stateField, $finalStates);
            })
            ->count();
    }
}
