<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Widgets;

use Illuminate\Support\Str;
use InvalidArgumentException;

class Widget
{
    private ?string $label = null;

    private ?string $description = null;

    private int|string $columnSpan = 1;

    /**
     * @var callable|null
     */
    private $visibilityCallback = null;

    private ?int $cacheTtl = null;

    final protected function __construct(
        private readonly string $name
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Widget name cannot be empty.');
        }
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function label(?string $label = null): static
    {
        $this->label = $label !== null ? trim($label) : null;

        return $this;
    }

    public function getLabel(): string
    {
        if (is_string($this->label) && $this->label !== '') {
            return $this->label;
        }

        return Str::of($this->name)
            ->replace(['-', '_'], ' ')
            ->headline()
            ->toString();
    }

    public function description(?string $description = null): static
    {
        $this->description = $description !== null ? trim($description) : null;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description !== '' ? $this->description : null;
    }

    public function columnSpan(int|string $span): static
    {
        if (is_int($span) && $span < 1) {
            throw new InvalidArgumentException('Widget column span must be greater than zero.');
        }

        if (is_string($span) && trim($span) === '') {
            throw new InvalidArgumentException('Widget column span string cannot be empty.');
        }

        $this->columnSpan = is_string($span) ? trim($span) : $span;

        return $this;
    }

    public function getColumnSpan(): int|string
    {
        return $this->columnSpan;
    }

    public function visibleIf(callable $callback): static
    {
        $this->visibilityCallback = $callback;

        return $this;
    }

    public function isVisible(mixed $user = null): bool
    {
        if (! is_callable($this->visibilityCallback)) {
            return true;
        }

        return (bool) call_user_func($this->visibilityCallback, $user, $this);
    }

    public function cacheFor(int $seconds): static
    {
        if ($seconds < 1) {
            throw new InvalidArgumentException('Widget cache TTL must be greater than zero seconds.');
        }

        $this->cacheTtl = $seconds;

        return $this;
    }

    public function getCacheTtl(): ?int
    {
        return $this->cacheTtl;
    }

    public function view(): string
    {
        return 'seviaflow-ui::dashboard.widgets.placeholder';
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(mixed $user = null, ?string $dashboardSlug = null): array
    {
        return [];
    }
}
