<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Icons;

use JsonException;
use RuntimeException;

final class IconRegistry
{
    /**
     * @var array<string, IconDefinition>|null
     */
    private static ?array $definitions = null;

    /**
     * @var array<int, string>|null
     */
    private static ?array $categories = null;

    /**
     * @return array<int, IconDefinition>
     */
    public static function all(): array
    {
        self::load();

        return array_values(self::$definitions ?? []);
    }

    /**
     * @return array<int, string>
     */
    public static function categories(): array
    {
        self::load();

        return self::$categories ?? [];
    }

    public static function has(string $name): bool
    {
        return self::find($name) !== null;
    }

    public static function find(string $name): ?IconDefinition
    {
        self::load();

        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return self::$definitions[$name] ?? null;
    }

    public static function catalogPath(): string
    {
        return dirname(__DIR__, 2).'/resources/icons/catalog.json';
    }

    public static function outlinePath(): string
    {
        return dirname(__DIR__, 2).'/resources/icons/outline';
    }

    private static function load(): void
    {
        if (self::$definitions !== null) {
            return;
        }

        $catalogPath = self::catalogPath();
        $catalog = file_get_contents($catalogPath);
        if ($catalog === false) {
            throw new RuntimeException('Unable to read SeviaFlow icon catalog.');
        }

        try {
            $decoded = json_decode($catalog, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to parse SeviaFlow icon catalog.', previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('SeviaFlow icon catalog must be a JSON object.');
        }

        self::$categories = self::readCategories($decoded);
        self::$definitions = self::readDefinitions($decoded, self::$categories);
    }

    /**
     * @param  array<mixed>  $catalog
     * @return array<int, string>
     */
    private static function readCategories(array $catalog): array
    {
        $categories = $catalog['categories'] ?? null;
        if (! is_array($categories)) {
            throw new RuntimeException('SeviaFlow icon catalog categories must be an array.');
        }

        $values = [];
        foreach ($categories as $category) {
            if (! is_string($category) || ! self::isSlug($category)) {
                throw new RuntimeException('SeviaFlow icon catalog contains an invalid category.');
            }

            $values[] = $category;
        }

        if (count($values) !== count(array_unique($values))) {
            throw new RuntimeException('SeviaFlow icon catalog contains duplicate categories.');
        }

        return $values;
    }

    /**
     * @param  array<mixed>  $catalog
     * @param  array<int, string>  $categories
     * @return array<string, IconDefinition>
     */
    private static function readDefinitions(array $catalog, array $categories): array
    {
        $icons = $catalog['icons'] ?? null;
        if (! is_array($icons)) {
            throw new RuntimeException('SeviaFlow icon catalog icons must be an array.');
        }

        $definitions = [];
        $allowedCategories = array_flip($categories);
        foreach ($icons as $icon) {
            if (! is_array($icon)) {
                throw new RuntimeException('SeviaFlow icon catalog icon entries must be objects.');
            }

            $name = $icon['name'] ?? null;
            $category = $icon['category'] ?? null;
            $keywords = $icon['keywords'] ?? null;

            if (! is_string($name) || ! self::isSlug($name)) {
                throw new RuntimeException('SeviaFlow icon catalog contains an invalid icon name.');
            }

            if (isset($definitions[$name])) {
                throw new RuntimeException("SeviaFlow icon catalog contains a duplicate icon name [{$name}].");
            }

            if (! is_string($category) || ! isset($allowedCategories[$category])) {
                throw new RuntimeException("SeviaFlow icon [{$name}] has an invalid category.");
            }

            if (! is_array($keywords)) {
                throw new RuntimeException("SeviaFlow icon [{$name}] keywords must be an array.");
            }

            $keywordValues = [];
            foreach ($keywords as $keyword) {
                if (! is_string($keyword)) {
                    throw new RuntimeException("SeviaFlow icon [{$name}] contains a non-string keyword.");
                }

                $keyword = trim($keyword);
                if ($keyword === '') {
                    throw new RuntimeException("SeviaFlow icon [{$name}] contains an empty keyword.");
                }

                $keywordValues[] = $keyword;
            }

            if (count($keywordValues) !== count(array_unique($keywordValues))) {
                throw new RuntimeException("SeviaFlow icon [{$name}] contains duplicate keywords.");
            }

            $svgPath = self::outlinePath().'/'.$name.'.svg';
            if (! is_file($svgPath)) {
                throw new RuntimeException("SeviaFlow icon [{$name}] is missing its SVG asset.");
            }

            $definitions[$name] = new IconDefinition(
                name: $name,
                category: $category,
                keywords: $keywordValues,
                svgPath: $svgPath,
            );
        }

        return $definitions;
    }

    private static function isSlug(string $value): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1;
    }
}
