<?php

declare(strict_types=1);

it('package javascript initializes dashboard charts', function (): void {
    $js = file_get_contents(__DIR__.'/../../../resources/js/seviaflow.js');

    expect($js)->not->toBeFalse()
        ->and($js)->toContain('data-sf-chart')
        ->and($js)->toContain('new Chart')
        ->and($js)->toContain('grouped_bar')
        ->and($js)->toContain('stacked_bar')
        ->and($js)->toContain('data-sf-dashboard-layout-editor')
        ->and($js)->toContain('initDashboardLayoutEditor')
        ->and($js)->toContain('data-sf-command-palette')
        ->and($js)->toContain('data-sf-drawer-target')
        ->and($js)->toContain('data-sf-density')
        ->and($js)->toContain('livewire:navigated');
});

it('package dependencies include chart js for bundled assets', function (): void {
    $package = json_decode((string) file_get_contents(__DIR__.'/../../../package.json'), true);
    $devDependencies = is_array($package['devDependencies'] ?? null) ? $package['devDependencies'] : [];

    expect($devDependencies)->toHaveKey('chart.js');
});
