<?php

declare(strict_types=1);

use SeviaFlow\Ui\Navigation\NavigationItem;

it('renders sidebar links with known icons', function (): void {
    $html = view('seviaflow-ui::components.sidebar-link', [
        'item' => new NavigationItem(
            key: 'resource:customers',
            label: 'Customers',
            url: '/admin/resources/customers',
            active: true,
            icon: 'users',
        ),
    ])->render();

    expect($html)->toContain('href="/admin/resources/customers"')
        ->and($html)->toContain('sf-sidebar-link-active')
        ->and($html)->toContain('data-seviaflow-icon="users"')
        ->and($html)->toContain('Customers');
});

it('renders sidebar fallback icons when configured icon is null or unknown', function (?string $icon): void {
    $html = view('seviaflow-ui::components.sidebar-link', [
        'item' => new NavigationItem(
            key: 'resource:customers',
            label: 'Customers',
            url: '/admin/resources/customers',
            icon: $icon,
        ),
    ])->render();

    expect($html)->toContain('href="/admin/resources/customers"')
        ->and($html)->toContain('Customers')
        ->and($html)->toContain('data-seviaflow-icon="circle-stack"')
        ->and($html)->not->toContain('definitely-not-an-icon');
})->with([
    'null' => null,
    'unknown' => 'definitely-not-an-icon',
]);
