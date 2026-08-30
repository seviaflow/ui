<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Widgets;

use Illuminate\Contracts\Auth\Authenticatable;
use SeviaFlow\Core\Analytics\AnalyticsExecutor;
use SeviaFlow\Core\Analytics\AnalyticsQuery;
use SeviaFlow\Ui\Support\AnalyticsValueFormatter;
use Throwable;

final class AnalyticsTableWidget extends Widget
{
    private ?AnalyticsQuery $query = null;

    private bool $loading = false;

    public function query(AnalyticsQuery $query): static
    {
        $this->query = $query;

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
        return 'seviaflow-ui::dashboard.widgets.analytics-table';
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
                widgetType: 'analytics_table',
                widgetName: $this->name(),
                dashboardSlug: $dashboardSlug,
                user: $actor,
                context: [
                    'query' => $this->query->toArray(),
                ],
                ttl: $this->getCacheTtl(),
                resolver: fn () => app(AnalyticsExecutor::class)->execute($this->query, $actor)->toArray(),
            );
        } catch (Throwable) {
            return $this->state('error', 'Unable to load analytics.');
        }

        /** @var array<int, array<string, mixed>> $sourceRows */
        $sourceRows = is_array($result['rows'] ?? null) ? $result['rows'] : [];
        if ($sourceRows === []) {
            return $this->state('empty');
        }

        return array_merge($this->state('ready'), [
            'columns' => $this->columns($result),
            'rows' => $this->rows($sourceRows, $result),
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
            'columns' => [],
            'rows' => [],
            'emptyMessage' => 'No analytics rows found.',
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int, string>
     */
    private function columns(array $result): array
    {
        $columns = [];
        /** @var array<string, mixed> $query */
        $query = is_array($result['query'] ?? null) ? $result['query'] : [];
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];
        /** @var array<string, mixed> $dataset */
        $dataset = is_array($metadata['dataset'] ?? null) ? $metadata['dataset'] : [];

        if (is_array($query['time'] ?? null)) {
            $columns[] = 'Period';
        }

        foreach ((array) ($query['dimensions'] ?? []) as $dimension) {
            if (! is_string($dimension)) {
                continue;
            }

            $columns[] = $this->semanticLabel($dataset['dimensions'][$dimension] ?? null, $dimension);
        }

        foreach ((array) ($query['measures'] ?? []) as $measure) {
            if (! is_string($measure)) {
                continue;
            }

            $columns[] = $this->semanticLabel($dataset['measures'][$measure] ?? null, $measure);
        }

        return $columns;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sourceRows
     * @param  array<string, mixed>  $result
     * @return array<int, array<int, string>>
     */
    private function rows(array $sourceRows, array $result): array
    {
        $rows = [];
        /** @var array<string, mixed> $query */
        $query = is_array($result['query'] ?? null) ? $result['query'] : [];
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];
        /** @var array<string, mixed> $dataset */
        $dataset = is_array($metadata['dataset'] ?? null) ? $metadata['dataset'] : [];
        $formatter = app(AnalyticsValueFormatter::class);

        foreach ($sourceRows as $sourceRow) {
            $cells = [];

            if (is_array($sourceRow['time'] ?? null)) {
                $cells[] = $formatter->formatDimension($sourceRow['time']['label'] ?? $sourceRow['time']['value'] ?? null);
            }

            foreach ((array) ($query['dimensions'] ?? []) as $dimension) {
                if (! is_string($dimension)) {
                    continue;
                }

                /** @var array<string, array{value:mixed,label:string}> $dimensions */
                $dimensions = is_array($sourceRow['dimensions'] ?? null) ? $sourceRow['dimensions'] : [];
                $cells[] = $formatter->formatDimension($dimensions[$dimension]['label'] ?? null);
            }

            foreach ((array) ($query['measures'] ?? []) as $measure) {
                if (! is_string($measure)) {
                    continue;
                }

                /** @var array<string, mixed> $measureMetadata */
                $measureMetadata = is_array($dataset['measures'][$measure] ?? null) ? $dataset['measures'][$measure] : ['type' => 'number', 'format' => []];
                /** @var array<string, int|float|null> $measures */
                $measures = is_array($sourceRow['measures'] ?? null) ? $sourceRow['measures'] : [];
                $cells[] = $formatter->formatMeasure($measureMetadata, $measures[$measure] ?? null);
            }

            $rows[] = $cells;
        }

        return $rows;
    }

    private function semanticLabel(mixed $metadata, string $fallback): string
    {
        if (is_array($metadata) && is_string($metadata['label'] ?? null) && trim($metadata['label']) !== '') {
            return $metadata['label'];
        }

        return str((string) str_replace(['_', '-'], ' ', $fallback))->headline()->toString();
    }
}
