<?php

declare(strict_types=1);

use SeviaFlow\Ui\Icons\IconRenderer;

it('renders a known icon as a decorative inline SVG', function (): void {
    $html = IconRenderer::render('users', 'sf-sidebar-icon');

    expect($html)->toContain('<svg')
        ->and($html)->toContain('stroke="currentColor"')
        ->and($html)->toContain('aria-hidden="true"')
        ->and($html)->toContain('focusable="false"')
        ->and($html)->toContain('data-seviaflow-icon="users"')
        ->and($html)->toContain('class="sf-sidebar-icon"');
});

it('renders nothing for empty unknown and traversal shaped names', function (): void {
    foreach ([null, '', ' ', 'definitely-not-an-icon', '../composer', '../../resources/icons/catalog', '/etc/passwd'] as $name) {
        expect(IconRenderer::render($name))->toBe('');
    }
});

it('escapes caller supplied css classes', function (): void {
    $html = IconRenderer::render('users', 'sf-sidebar-icon "unexpected"');

    expect($html)->toContain('class="sf-sidebar-icon &quot;unexpected&quot;"')
        ->and($html)->not->toContain('class="sf-sidebar-icon "unexpected""');
});
