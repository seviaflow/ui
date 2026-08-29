<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Icons;

final class IconRenderer
{
    /**
     * @var array<string, string>
     */
    private static array $svgCache = [];

    public static function render(?string $name, string $class = ''): string
    {
        if ($name === null) {
            return '';
        }

        $definition = IconRegistry::find($name);
        if ($definition === null) {
            return '';
        }

        $svg = self::$svgCache[$definition->name] ?? null;
        if ($svg === null) {
            $svg = file_get_contents($definition->svgPath);
            if ($svg === false) {
                return '';
            }

            self::$svgCache[$definition->name] = $svg;
        }

        return self::withAttributes($svg, $definition->name, $class);
    }

    private static function withAttributes(string $svg, string $name, string $class): string
    {
        $attributes = [
            'aria-hidden' => 'true',
            'data-seviaflow-icon' => $name,
            'focusable' => 'false',
        ];

        $class = trim($class);
        if ($class !== '') {
            $attributes = ['class' => $class] + $attributes;
        }

        $renderedAttributes = '';
        foreach ($attributes as $attribute => $value) {
            $renderedAttributes .= sprintf(' %s="%s"', $attribute, self::escapeAttribute($value));
        }

        $rendered = preg_replace_callback('/<svg\b([^>]*)>/i', static function (array $matches) use ($renderedAttributes): string {
            $existing = preg_replace(
                '/\s(?:class|aria-hidden|data-seviaflow-icon|focusable)="[^"]*"/i',
                '',
                (string) $matches[1]
            );

            return '<svg'.$existing.$renderedAttributes.'>';
        }, $svg, 1);

        return is_string($rendered) ? $rendered : '';
    }

    private static function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
