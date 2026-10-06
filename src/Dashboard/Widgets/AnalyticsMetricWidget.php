<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Widgets;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use SeviaFlow\Core\Analytics\AnalyticsExecutor;
use SeviaFlow\Core\Analytics\AnalyticsQuery;
use SeviaFlow\Ui\Support\AnalyticsValueFormatter;
use Throwable;

final class AnalyticsMetricWidget extends Widget
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_COLORS = ['gray', 'indigo', 'blue', 'sky', 'emerald', 'amber', 'red', 'purple'];

    private ?AnalyticsQuery $query = null;

    private ?string $measure = null;

    private string $color = 'gray';

    private ?string $icon = null;

    private bool $loading = false;

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

    public function color(string $color): static
    {
        $normalized = strtolower(trim($color));

        if (! in_array($normalized, self::ALLOWED_COLORS, true)) {
            throw new InvalidArgumentException(sprintf('AnalyticsMetricWidget color [%s] is not allowed.', $color));
        }

        $this->color = $normalized;

        return $this;
    }

    public function icon(?string $icon = null): static
    {
        $this->icon = $icon !== null ? trim($icon) : null;

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
        return 'seviaflow-ui::dashboard.widgets.analytics-metric';
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
                widgetType: 'analytics_metric',
                widgetName: $this->name(),
                dashboardSlug: $dashboardSlug,
                user: $actor,
                context: [
                    'query' => $this->query->toArray(),
                    'measure' => $this->measure,
                    'color' => $this->color,
                    'icon' => $this->icon,
                ],
                ttl: $this->getCacheTtl(),
                resolver: fn () => app(AnalyticsExecutor::class)->execute($this->query, $actor)->toArray(),
            );
        } catch (Throwable) {
            return $this->state('error', 'Unable to load analytics.');
        }

        $measure = $this->measure ?? $this->query->measuresDefinition()[0] ?? null;
        if (! is_string($measure) || $measure === '') {
            return $this->state('error', 'Analytics measure is not configured.');
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = is_array($result['rows'] ?? null) ? $result['rows'] : [];
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];
        $value = $rows[0]['measures'][$measure] ?? null;

        if ($value === null) {
            return $this->state('empty');
        }

        $measureMetadata = $this->measureMetadata($metadata, $measure);

        return array_merge($this->state('ready'), [
            'value' => app(AnalyticsValueFormatter::class)->formatMeasure($measureMetadata, $value),
            'valueRaw' => $value,
            'color' => $this->color,
            'icon' => $this->icon !== '' ? $this->icon : null,
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
            'value' => null,
            'valueRaw' => null,
            'color' => $this->color,
            'icon' => $this->icon !== '' ? $this->icon : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function measureMetadata(array $metadata, string $measure): array
    {
        /** @var array<string, mixed> $dataset */
        $dataset = is_array($metadata['dataset'] ?? null) ? $metadata['dataset'] : [];
        /** @var array<string, mixed> $measures */
        $measures = is_array($dataset['measures'] ?? null) ? $dataset['measures'] : [];

        return is_array($measures[$measure] ?? null) ? $measures[$measure] : ['type' => 'number', 'format' => []];
    }
}
