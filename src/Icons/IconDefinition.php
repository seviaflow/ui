<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Icons;

final class IconDefinition
{
    /**
     * @param  array<int, string>  $keywords
     */
    public function __construct(
        public readonly string $name,
        public readonly string $category,
        public readonly array $keywords,
        public readonly string $svgPath,
    ) {}
}
