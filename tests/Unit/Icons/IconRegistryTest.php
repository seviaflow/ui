<?php

declare(strict_types=1);

use SeviaFlow\Ui\Icons\IconDefinition;
use SeviaFlow\Ui\Icons\IconRegistry;

it('resolves known icons from the catalog', function (): void {
    $definition = IconRegistry::find('users');

    expect($definition)->toBeInstanceOf(IconDefinition::class)
        ->and($definition?->name)->toBe('users')
        ->and($definition?->category)->toBe('people')
        ->and($definition?->svgPath)->toEndWith('/resources/icons/outline/users.svg')
        ->and(IconRegistry::has('users'))->toBeTrue();
});

it('normalizes surrounding whitespace only', function (): void {
    expect(IconRegistry::find(' users ')?->name)->toBe('users')
        ->and(IconRegistry::find("users\n")?->name)->toBe('users')
        ->and(IconRegistry::find('USERs'))->toBeNull()
        ->and(IconRegistry::find('user group'))->toBeNull();
});

it('returns no definition for unknown empty or traversal shaped names', function (): void {
    foreach (['', '   ', 'definitely-not-an-icon', '../users', '..\\users', '/tmp/users', 'outline/users'] as $name) {
        expect(IconRegistry::find($name))->toBeNull()
            ->and(IconRegistry::has($name))->toBeFalse();
    }
});

it('ships a unique lowercase kebab case catalog with allowed categories', function (): void {
    $definitions = IconRegistry::all();
    $names = array_map(static fn (IconDefinition $definition): string => $definition->name, $definitions);
    $allowedCategories = [
        'general',
        'people',
        'business',
        'commerce',
        'files',
        'communication',
        'data',
        'navigation',
        'status',
        'security',
        'development',
    ];

    expect($definitions)->toHaveCount(71)
        ->and($names)->toHaveCount(count(array_unique($names)));

    foreach ($definitions as $definition) {
        expect($definition->name)->toMatch('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
            ->and($definition->category)->toBeIn($allowedCategories)
            ->and($definition->keywords)->not->toBeEmpty()
            ->and($definition->keywords)->toHaveCount(count(array_unique($definition->keywords)));
    }
});

it('has exactly one trusted SVG asset for every catalog entry', function (): void {
    $definitions = IconRegistry::all();
    $catalogNames = array_map(static fn (IconDefinition $definition): string => $definition->name, $definitions);
    sort($catalogNames);

    $svgFiles = glob(IconRegistry::outlinePath().'/*.svg');
    expect($svgFiles)->not->toBeFalse();

    $svgNames = array_map(
        static fn (string $path): string => basename($path, '.svg'),
        $svgFiles === false ? [] : $svgFiles
    );
    sort($svgNames);

    foreach ($definitions as $definition) {
        expect(is_file($definition->svgPath))->toBeTrue();
    }

    expect($svgNames)->toBe($catalogNames);
});
