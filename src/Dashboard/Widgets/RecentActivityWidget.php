<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Widgets;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Ui\Dashboard\RecentActivityService;

final class RecentActivityWidget extends Widget
{
    private int $limit = 10;

    /**
     * @var array<int, class-string<resource>>
     */
    private array $resourceClasses = [];

    public function limit(int $limit): static
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('RecentActivityWidget limit must be greater than zero.');
        }

        $this->limit = min($limit, $this->maxLimit());

        return $this;
    }

    /**
     * @param  array<int, class-string<resource>>  $resources
     */
    public function resources(array $resources): static
    {
        $normalized = [];

        foreach ($resources as $resourceClass) {
            if (! is_string($resourceClass) || ! is_a($resourceClass, Resource::class, true)) {
                throw new InvalidArgumentException('RecentActivityWidget resources() must contain only Resource class strings.');
            }

            $normalized[] = $resourceClass;
        }

        $this->resourceClasses = array_values(array_unique($normalized));

        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function view(): string
    {
        return 'seviaflow-ui::dashboard.widgets.recent-activity';
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(mixed $user = null, ?string $dashboardSlug = null): array
    {
        $actor = $user instanceof Authenticatable ? $user : null;

        $items = app(WidgetCache::class)->remember(
            widgetType: 'recent-activity',
            widgetName: $this->name(),
            dashboardSlug: $dashboardSlug,
            user: $actor,
            context: [
                'resources' => $this->resourceClasses,
                'limit' => $this->limit,
            ],
            ttl: $this->getCacheTtl(),
            resolver: fn (): array => app(RecentActivityService::class)->items(
                configuredResources: $this->configuredResources(),
                user: $actor,
                limit: $this->limit
            )
        );

        return [
            'items' => $items,
            'emptyMessage' => 'No recent activity found.',
        ];
    }

    /**
     * @return array<string, class-string<resource>>
     */
    private function configuredResources(): array
    {
        /** @var array<string, class-string<resource>> $configured */
        $configured = (array) config('seviaflow.resources', []);

        if ($this->resourceClasses === []) {
            return $configured;
        }

        return array_filter(
            $configured,
            fn (string $resourceClass): bool => in_array($resourceClass, $this->resourceClasses, true)
        );
    }

    private function maxLimit(): int
    {
        $configured = (int) config('seviaflow-ui.dashboard.recent_activity_widgets.max_limit', 25);

        return $configured > 0 ? $configured : 25;
    }
}
