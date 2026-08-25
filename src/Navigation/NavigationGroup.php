<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Navigation;

final class NavigationGroup
{
    /**
     * @param  array<int, NavigationItem>  $items
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $items,
    ) {}
}
