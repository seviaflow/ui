<?php

declare(strict_types=1);

it('ships official brand source assets and copies them into dist', function (): void {
    $root = dirname(__DIR__, 3);

    expect(is_file($root.'/resources/public/brand/seviaflow-logo.png'))->toBeTrue()
        ->and(is_file($root.'/resources/public/brand/seviaflow-mark.png'))->toBeTrue()
        ->and(is_file($root.'/public/dist/brand/seviaflow-logo.png'))->toBeTrue()
        ->and(is_file($root.'/public/dist/brand/seviaflow-mark.png'))->toBeTrue();
});

it('renders stable public brand urls through the shared component', function (): void {
    $html = view('seviaflow-ui::components.brand', [
        'href' => '/admin',
    ])->render();

    expect($html)->toContain('href="/admin"')
        ->and($html)->toContain('/vendor/seviaflow/ui/brand/seviaflow-logo.png')
        ->and($html)->toContain('/vendor/seviaflow/ui/brand/seviaflow-mark.png')
        ->and($html)->not->toContain('sf-auth-logo')
        ->and($html)->not->toContain('>SF<');
});

it('renders official mark with an accessible text lockup on dark surfaces', function (): void {
    $html = view('seviaflow-ui::components.brand', [
        'href' => '/admin',
        'surface' => 'dark',
    ])->render();

    expect($html)->toContain('sf-brand-on-dark')
        ->and($html)->toContain('/vendor/seviaflow/ui/brand/seviaflow-mark.png')
        ->and($html)->toContain('sf-brand-text-lockup')
        ->and($html)->toContain('Sevia')
        ->and($html)->toContain('Flow')
        ->and($html)->not->toContain('seviaflow-logo-original-white.png')
        ->and($html)->not->toContain('>SF<');
});
