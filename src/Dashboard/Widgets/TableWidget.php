<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Widgets;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Columns\BelongsToColumn;
use SeviaFlow\Core\Resources\Columns\Column;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceAuthorization;
use SeviaFlow\Ui\Index\TableValueFormatter;

final class TableWidget extends Widget
{
    /**
     * @var class-string<resource>|null
     */
    private ?string $resourceClass = null;

    /**
     * @var array<int, Column>
     */
    private array $columns = [];

    /**
     * @var Closure|null
     */
    private $queryCallback = null;

    private int $limit = 5;

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function resource(string $resourceClass): static
    {
        if (! is_a($resourceClass, Resource::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'TableWidget resource [%s] must extend %s.',
                $resourceClass,
                Resource::class
            ));
        }

        $this->resourceClass = $resourceClass;

        return $this;
    }

    /**
     * @param  array<int, Column>  $columns
     */
    public function columns(array $columns): static
    {
        foreach ($columns as $column) {
            if (! $column instanceof Column) {
                throw new InvalidArgumentException('TableWidget columns() must contain only Column instances.');
            }
        }

        $this->columns = array_values($columns);

        return $this;
    }

    public function query(Closure $callback): static
    {
        $this->queryCallback = $callback;

        return $this;
    }

    public function limit(int $limit): static
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('TableWidget limit must be greater than zero.');
        }

        $this->limit = min($limit, $this->maxLimit());

        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
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
        return 'seviaflow-ui::dashboard.widgets.table';
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(mixed $user = null, ?string $dashboardSlug = null): array
    {
        if (! is_string($this->resourceClass)) {
            throw new InvalidArgumentException('TableWidget requires resource() to be configured.');
        }

        if ($this->columns === []) {
            throw new InvalidArgumentException('TableWidget requires explicit columns() configuration.');
        }

        $actor = $user instanceof Authenticatable ? $user : null;
        $resolvedRows = app(WidgetCache::class)->remember(
            widgetType: 'table',
            widgetName: $this->name(),
            dashboardSlug: $dashboardSlug,
            user: $actor,
            context: [
                'resource' => $this->resourceClass,
                'limit' => $this->limit,
                'columns' => array_map(static fn (Column $column): string => $column->name(), $this->columns),
                'has_query_callback' => $this->queryCallback instanceof Closure,
            ],
            ttl: $this->getCacheTtl(),
            resolver: function () use ($actor): array {
                $rows = $this->resolveRows($actor);
                $formatter = app(TableValueFormatter::class);

                $resolvedRows = [];
                foreach ($rows as $record) {
                    if (! ResourceAuthorization::for($this->resourceClass)->canView($actor, $record)) {
                        continue;
                    }

                    $cells = [];
                    foreach ($this->columns as $column) {
                        $cells[] = $formatter->format($record, $column);
                    }

                    $resolvedRows[] = [
                        'id' => (string) $record->getKey(),
                        'cells' => $cells,
                        'showUrl' => $this->showUrl($record, $actor),
                    ];
                }

                return $resolvedRows;
            }
        );

        return [
            'columns' => array_map(static fn (Column $column): string => $column->getLabel(), $this->columns),
            'rows' => $resolvedRows,
            'emptyMessage' => 'No records found.',
        ];
    }

    /**
     * @return Collection<int, Model>
     */
    private function resolveRows(?Authenticatable $user): Collection
    {
        /** @var class-string<resource> $resourceClass */
        $resourceClass = $this->resourceClass;
        /** @var class-string<Model> $modelClass */
        $modelClass = $resourceClass::model();
        $query = $resourceClass::query($modelClass::query());

        foreach ($this->columns as $column) {
            if ($column instanceof BelongsToColumn) {
                $query->with($column->getRelationshipName());
            }
        }

        if ($this->queryCallback instanceof Closure) {
            $result = ($this->queryCallback)($query, $user, $this);
            if ($result instanceof Builder) {
                $query = $result;
            }
        }

        return $query
            ->limit($this->limit)
            ->get();
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
        $configured = (int) config('seviaflow-ui.dashboard.table_widgets.max_limit', 25);

        return $configured > 0 ? $configured : 25;
    }
}
