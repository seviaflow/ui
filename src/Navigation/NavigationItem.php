<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Navigation;

final class NavigationItem
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $url,
        public readonly bool $active = false,
        public readonly ?string $icon = null,
        public readonly ?string $group = null,
        public readonly ?int $order = null,
        public readonly string $method = 'GET',
    ) {}
}
