<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Widgets;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceAuthorization;

final class StatsWidget extends Widget
{
    private int|string|Closure|null $value = null;

    /**
     * @var class-string<resource>|null
     */
    private ?string $resourceClass = null;

    private string $color = 'gray';

    private ?string $icon = null;

    /**
     * @var array<int, string>
     */
    private const ALLOWED_COLORS = ['gray', 'indigo', 'blue', 'sky', 'emerald', 'amber', 'red', 'purple'];

    public function value(int|string|Closure $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function resource(string $resourceClass): static
    {
        if (! is_a($resourceClass, Resource::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'StatsWidget resource [%s] must extend %s.',
                $resourceClass,
                Resource::class
            ));
        }

        $this->resourceClass = $resourceClass;

        return $this;
    }

    public function count(): static
    {
        if (! is_string($this->resourceClass)) {
            throw new InvalidArgumentException('StatsWidget::count() requires resource() to be configured first.');
        }

        $this->value = function (?Authenticatable $user): int {
            /** @var class-string<resource> $resourceClass */
            $resourceClass = $this->resourceClass;
            if (! ResourceAuthorization::for($resourceClass)->canViewAny($user)) {
                return 0;
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = $resourceClass::model();
            $query = $resourceClass::query($modelClass::query());

            return (int) $query->count();
        };

        return $this;
    }

    public function color(string $color): static
    {
        $normalized = strtolower(trim($color));

        if (! in_array($normalized, self::ALLOWED_COLORS, true)) {
            throw new InvalidArgumentException(sprintf(
                'StatsWidget color [%s] is not allowed.',
                $color
            ));
        }

        $this->color = $normalized;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function icon(?string $icon = null): static
    {
        $this->icon = $icon !== null ? trim($icon) : null;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon !== '' ? $this->icon : null;
    }

    public function resolveValue(?Authenticatable $user = null, ?string $dashboardSlug = null): int|string
    {
        $ttl = $this->getCacheTtl();

        return app(WidgetCache::class)->remember(
            widgetType: 'stats',
            widgetName: $this->name(),
            dashboardSlug: $dashboardSlug,
            user: $user,
            context: [
                'resource' => $this->resourceClass,
                'color' => $this->color,
                'icon' => $this->icon,
            ],
            ttl: $ttl,
            resolver: fn (): int|string => $this->evaluateValue($user),
        );
    }

    public function view(): string
    {
        return 'seviaflow-ui::dashboard.widgets.stats';
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(mixed $user = null, ?string $dashboardSlug = null): array
    {
        $actor = $user instanceof Authenticatable ? $user : null;

        return [
            'value' => $this->resolveValue($actor, $dashboardSlug),
            'color' => $this->getColor(),
            'icon' => $this->getIcon(),
        ];
    }

    private function evaluateValue(?Authenticatable $user): int|string
    {
        if ($this->value instanceof Closure) {
            $value = ($this->value)($user, $this);

            return $this->normalizeValue($value);
        }

        if (is_int($this->value) || is_string($this->value)) {
            return $this->value;
        }

        return 0;
    }

    private function normalizeValue(mixed $value): int|string
    {
        if (is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException('StatsWidget value closure must return int|string.');
    }
}
