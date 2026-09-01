<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Widgets;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use SeviaFlow\Core\Analytics\AnalyticsExecutor;
use SeviaFlow\Core\Analytics\AnalyticsQuery;
use Throwable;

final class AnalyticsChartWidget extends Widget
{
    /**
     * @var array<int, string>
     */
    private const array ALLOWED_TYPES = ['bar', 'line', 'grouped_bar', 'stacked_bar', 'doughnut'];

    private string $type = 'bar';

    private ?AnalyticsQuery $query = null;

    private ?string $measure = null;

    private bool $loading = false;

    public function type(string $type): static
    {
        $normalized = strtolower(trim($type));

        if (! in_array($normalized, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('AnalyticsChartWidget type [%s] is not supported.', $type));
        }

        $this->type = $normalized;

        return $this;
    }

    public function query(AnalyticsQuery $query): static
    {
        $this->query = $query;

        return $this;
    }

    public function measure(?string $measure): static
    {
        $this->measure = $measure !== null ? trim($measure) : null;

        return $this;
    }

    public function loading(bool $loading = true): static
    {
        $this->loading = $loading;

        return $this;
    }

    public function isVisible(mixed $user = null): bool
    {
        if (! parent::isVisible($user)) {
            return false;
        }

        if (! $this->query instanceof AnalyticsQuery) {
            return false;
        }

        $actor = $user instanceof Authenticatable ? $user : null;

        return app(AnalyticsExecutor::class)->canExecute($this->query, $actor);
    }

    public function view(): string
    {
        return 'seviaflow-ui::dashboard.widgets.analytics-chart';
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(mixed $user = null, ?string $dashboardSlug = null): array
    {
        if ($this->loading) {
            return $this->state('loading');
        }

        if (! $this->query instanceof AnalyticsQuery) {
            return $this->state('error', 'Analytics query is not configured.');
        }

        $actor = $user instanceof Authenticatable ? $user : null;

        try {
            $result = app(WidgetCache::class)->remember(
                widgetType: 'analytics_chart',
                widgetName: $this->name(),
                dashboardSlug: $dashboardSlug,
                user: $actor,
                context: [
                    'query' => $this->query->toArray(),
                    'measure' => $this->measure,
                    'type' => $this->type,
                ],
                ttl: $this->getCacheTtl(),
                resolver: fn () => app(AnalyticsExecutor::class)->execute($this->query, $actor)->toArray(),
            );
        } catch (Throwable) {
            return $this->state('error', 'Unable to load analytics.');
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = is_array($result['rows'] ?? null) ? $result['rows'] : [];
        if ($rows === []) {
            return $this->state('empty');
        }

        $measure = $this->measure ?? $this->query->measuresDefinition()[0] ?? null;
        if (! is_string($measure) || $measure === '') {
            return $this->state('error', 'Analytics measure is not configured.');
        }

        /** @var array<string, mixed> $metadata */
        $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];
        $measureLabel = $this->measureLabel($metadata, $measure);
        $labels = [];
        $values = [];

        foreach ($rows as $index => $row) {
            $labels[] = $this->rowLabel($row, $index);
            $values[] = $row['measures'][$measure] ?? 0;
        }

        return array_merge($this->state('ready'), [
            'chartType' => $this->type,
            'chartData' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => $measureLabel,
                    'data' => $values,
                ]],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function state(string $state, ?string $message = null): array
    {
        return [
            'state' => $state,
            'message' => $message,
            'chartType' => $this->type,
            'chartData' => [
                'labels' => [],
                'datasets' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowLabel(array $row, int $index): string
    {
        if (is_array($row['time'] ?? null)) {
            return (string) ($row['time']['label'] ?? $row['time']['value'] ?? 'Period '.($index + 1));
        }

        /** @var array<string, array{value:mixed,label:string}> $dimensions */
        $dimensions = is_array($row['dimensions'] ?? null) ? $row['dimensions'] : [];
        $first = array_values($dimensions)[0] ?? null;

        return is_array($first) ? (string) $first['label'] : 'Group '.($index + 1);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function measureLabel(array $metadata, string $measure): string
    {
        /** @var array<string, mixed> $dataset */
        $dataset = is_array($metadata['dataset'] ?? null) ? $metadata['dataset'] : [];
        /** @var array<string, mixed> $measures */
        $measures = is_array($dataset['measures'] ?? null) ? $dataset['measures'] : [];
        /** @var array<string, mixed> $measureMetadata */
        $measureMetadata = is_array($measures[$measure] ?? null) ? $measures[$measure] : [];

        return is_string($measureMetadata['label'] ?? null) ? $measureMetadata['label'] : $measure;
    }
}
