<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Widgets;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

final class ChartWidget extends Widget
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_TYPES = ['bar', 'line', 'grouped_bar', 'stacked_bar', 'doughnut'];

    private string $type = 'bar';

    /**
     * @var array<string, mixed>|Closure|null
     */
    private array|Closure|null $data = null;

    public function type(string $type): static
    {
        $normalized = strtolower(trim($type));

        if (! in_array($normalized, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'ChartWidget type [%s] is not supported.',
                $type
            ));
        }

        $this->type = $normalized;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param  array<string, mixed>|Closure  $data
     */
    public function data(array|Closure $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveData(?Authenticatable $user = null, ?string $dashboardSlug = null): array
    {
        $ttl = $this->getCacheTtl();

        /** @var array<string, mixed> */
        return app(WidgetCache::class)->remember(
            widgetType: 'chart',
            widgetName: $this->name(),
            dashboardSlug: $dashboardSlug,
            user: $user,
            context: [
                'type' => $this->type,
            ],
            ttl: $ttl,
            resolver: fn (): array => $this->evaluateData($user),
        );
    }

    public function view(): string
    {
        return 'seviaflow-ui::dashboard.widgets.chart';
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(mixed $user = null, ?string $dashboardSlug = null): array
    {
        $actor = $user instanceof Authenticatable ? $user : null;

        return [
            'chartType' => $this->getType(),
            'chartData' => $this->resolveData($actor, $dashboardSlug),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluateData(?Authenticatable $user): array
    {
        if ($this->data instanceof Closure) {
            $resolved = ($this->data)($user, $this);

            return $this->normalizeData($resolved);
        }

        if (is_array($this->data)) {
            return $this->normalizeData($this->data);
        }

        return $this->normalizeData([
            'labels' => [],
            'datasets' => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeData(mixed $data): array
    {
        if (! is_array($data)) {
            throw new InvalidArgumentException('ChartWidget data must be an array.');
        }

        $labels = $data['labels'] ?? [];
        $datasets = $data['datasets'] ?? [];

        if (! is_array($labels) || ! is_array($datasets)) {
            throw new InvalidArgumentException('ChartWidget data requires array labels and datasets.');
        }

        return [
            'labels' => array_values($labels),
            'datasets' => array_values($datasets),
        ];
    }
}
