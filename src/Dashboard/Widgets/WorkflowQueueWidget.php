<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Widgets;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceAuthorization;
use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Workflow;
use SeviaFlow\Core\Workflows\WorkflowTransitionResolver;

final class WorkflowQueueWidget extends Widget
{
    /**
     * @var class-string<resource>|null
     */
    private ?string $resourceClass = null;

    /**
     * @var array<int, string>
     */
    private array $states = [];

    private int $limit = 10;

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function resource(string $resourceClass): static
    {
        if (! is_a($resourceClass, Resource::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'WorkflowQueueWidget resource [%s] must extend %s.',
                $resourceClass,
                Resource::class
            ));
        }

        $this->resourceClass = $resourceClass;

        return $this;
    }

    /**
     * @param  array<int, string>  $states
     */
    public function states(array $states): static
    {
        $normalized = [];

        foreach ($states as $state) {
            if (! is_string($state) || trim($state) === '') {
                throw new InvalidArgumentException('WorkflowQueueWidget states() must contain non-empty state names.');
            }

            $normalized[] = trim($state);
        }

        $this->states = array_values(array_unique($normalized));

        return $this;
    }

    public function limit(int $limit): static
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('WorkflowQueueWidget limit must be greater than zero.');
        }

        $this->limit = min($limit, $this->maxLimit());

        return $this;
    }

    public function isVisible(mixed $user = null): bool
    {
        if (! parent::isVisible($user)) {
            return false;
        }

        if (! is_string($this->resourceClass)) {
            return false;
        }

        return ResourceAuthorization::for($this->resourceClass)->canViewAny(
            $user instanceof Authenticatable ? $user : null
        );
    }

    public function view(): string
    {
        return 'seviaflow-ui::dashboard.widgets.workflow-queue';
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(mixed $user = null, ?string $dashboardSlug = null): array
    {
        if (! is_string($this->resourceClass)) {
            throw new InvalidArgumentException('WorkflowQueueWidget requires resource() to be configured.');
        }

        if ($this->states === []) {
            throw new InvalidArgumentException('WorkflowQueueWidget requires states() to be configured.');
        }

        $workflow = $this->workflowDefinition();
        $this->assertStatesExist($workflow);

        $actor = $user instanceof Authenticatable ? $user : null;
        $resolvedRows = app(WidgetCache::class)->remember(
            widgetType: 'workflow-queue',
            widgetName: $this->name(),
            dashboardSlug: $dashboardSlug,
            user: $actor,
            context: [
                'resource' => $this->resourceClass,
                'state_field' => $workflow->getStateField(),
                'states' => $this->states,
                'limit' => $this->limit,
            ],
            ttl: $this->getCacheTtl(),
            resolver: function () use ($actor, $workflow): array {
                $rows = $this->resolveRows($workflow);
                $transitionResolver = app(WorkflowTransitionResolver::class);

                $resolvedRows = [];
                foreach ($rows as $record) {
                    if (! ResourceAuthorization::for($this->resourceClass)->canView($actor, $record)) {
                        continue;
                    }

                    $stateName = (string) ($record->getAttribute($workflow->getStateField()) ?? '');
                    $state = $workflow->getState($stateName);
                    $availableTransitions = $transitionResolver->availableTransitions(
                        resourceClass: $this->resourceClass,
                        record: $record,
                        user: $actor
                    );

                    $resolvedRows[] = [
                        'id' => (string) $record->getKey(),
                        'identifier' => $this->recordIdentifier($record),
                        'stateLabel' => $state?->getLabel() ?? $stateName,
                        'stateBadgeClass' => $this->badgeClass($state?->getColor()),
                        'showUrl' => $this->showUrl($record, $actor),
                        'availableTransitionCount' => count($availableTransitions),
                    ];
                }

                return $resolvedRows;
            }
        );

        return [
            'rows' => $resolvedRows,
            'emptyMessage' => 'No records currently need workflow attention.',
            'indexUrl' => $this->indexUrl($workflow),
        ];
    }

    private function workflowDefinition(): Workflow
    {
        /** @var class-string<resource> $resourceClass */
        $resourceClass = $this->resourceClass;
        $workflow = $resourceClass::workflow(new Workflow);

        if ($workflow->getStates() === []) {
            throw new InvalidArgumentException(sprintf(
                'WorkflowQueueWidget resource [%s] does not define workflow states.',
                $resourceClass
            ));
        }

        return $workflow;
    }

    private function assertStatesExist(Workflow $workflow): void
    {
        foreach ($this->states as $state) {
            if (! $workflow->getState($state) instanceof State) {
                throw new InvalidArgumentException(sprintf(
                    'WorkflowQueueWidget state [%s] is not defined in resource workflow.',
                    $state
                ));
            }
        }
    }

    /**
     * @return Collection<int, Model>
     */
    private function resolveRows(Workflow $workflow): Collection
    {
        /** @var class-string<resource> $resourceClass */
        $resourceClass = $this->resourceClass;
        /** @var class-string<Model> $modelClass */
        $modelClass = $resourceClass::model();

        $query = $resourceClass::query($modelClass::query())
            ->whereIn($workflow->getStateField(), $this->states)
            ->limit($this->limit);

        return $query->get();
    }

    private function recordIdentifier(Model $record): string
    {
        if (is_string($this->resourceClass)) {
            $title = $this->resourceClass::recordTitle($record);

            if ($title !== null) {
                return $title;
            }
        }

        foreach (['name', 'title', 'email'] as $attribute) {
            $value = $record->getAttribute($attribute);
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return class_basename($record).' #'.$record->getKey();
    }

    private function badgeClass(?string $color): string
    {
        return match ($color) {
            'green' => 'sf-badge sf-badge-green',
            'yellow' => 'sf-badge sf-badge-amber',
            'red' => 'sf-badge sf-badge-red',
            'indigo', 'blue', 'purple', 'pink' => 'sf-badge sf-badge-indigo',
            default => 'sf-badge sf-badge-gray',
        };
    }

    private function showUrl(Model $record, ?Authenticatable $user): ?string
    {
        if (! is_string($this->resourceClass)) {
            return null;
        }

        if (! ResourceAuthorization::for($this->resourceClass)->canView($user, $record)) {
            return null;
        }

        $routeName = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.').'resources.show';
        if (! Route::has($routeName)) {
            return null;
        }

        $slug = $this->resourceSlug();
        if ($slug === null) {
            return null;
        }

        return route($routeName, [
            'resource' => $slug,
            'record' => $record->getKey(),
        ]);
    }

    private function indexUrl(Workflow $workflow): ?string
    {
        $slug = $this->resourceSlug();
        if ($slug === null) {
            return null;
        }

        $routeName = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.').'resources.index';
        if (! Route::has($routeName)) {
            return null;
        }

        $query = count($this->states) === 1
            ? ['filter' => [$workflow->getStateField() => $this->states[0]]]
            : [];

        return route($routeName, ['resource' => $slug]).($query === [] ? '' : '?'.http_build_query($query));
    }

    private function resourceSlug(): ?string
    {
        /** @var array<string, class-string<resource>> $resources */
        $resources = (array) config('seviaflow.resources', []);

        foreach ($resources as $slug => $resourceClass) {
            if ($resourceClass === $this->resourceClass) {
                return $slug;
            }
        }

        return null;
    }

    private function maxLimit(): int
    {
        $configured = (int) config('seviaflow-ui.dashboard.workflow_widgets.max_limit', 25);

        return $configured > 0 ? $configured : 25;
    }
}
