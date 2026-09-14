<?php

declare(strict_types=1);

it('package javascript initializes dashboard charts', function (): void {
    $js = file_get_contents(__DIR__.'/../../../resources/js/seviaflow.js');

    expect($js)->not->toBeFalse()
        ->and($js)->toContain('data-sf-chart')
        ->and($js)->toContain('new Chart')
        ->and($js)->toContain('grouped_bar')
        ->and($js)->toContain('stacked_bar')
        ->and($js)->toContain('dashboardChartState')
        ->and($js)->toContain('sfChartSignature')
        ->and($js)->toContain('MutationObserver')
        ->and($js)->toContain("window.Livewire.hook('morphed'")
        ->and($js)->toContain("window.Livewire.hook('morph.added'")
        ->and($js)->toContain('data-sf-dashboard-layout-editor')
        ->and($js)->toContain('initDashboardLayoutEditor')
        ->and($js)->toContain('data-sf-command-palette')
        ->and($js)->toContain('data-sf-drawer-target')
        ->and($js)->toContain('getFocusableElements')
        ->and($js)->toContain('inertDialogBackground')
        ->and($js)->toContain('restoreDialogFocus')
        ->and($js)->toContain('data-sf-density')
        ->and($js)->toContain('livewire:initialized')
        ->and($js)->toContain('livewire:navigated');
});

it('package dependencies include chart js for bundled assets', function (): void {
    $package = json_decode((string) file_get_contents(__DIR__.'/../../../package.json'), true);
    $devDependencies = is_array($package['devDependencies'] ?? null) ? $package['devDependencies'] : [];

    expect($devDependencies)->toHaveKey('chart.js');
});

it('package css includes generic responsive chart grid styles', function (): void {
    $css = file_get_contents(__DIR__.'/../../../resources/css/seviaflow.css');

    expect($css)->not->toBeFalse()
        ->and($css)->toContain('.sf-chart-grid')
        ->and($css)->toContain('.sf-chart-facet')
        ->and($css)->toContain('grid-template-columns: repeat(2, minmax(0, 1fr))')
        ->and($css)->toContain('.sf-modal-panel')
        ->and($css)->toContain('.sf-livewire-loading')
        ->and($css)->toContain('--sf-focus-color')
        ->and($css)->toContain('overflow-wrap: anywhere');
});

it('registers translated navigation microcopy', function (): void {
    $originalLocale = app()->getLocale();

    app()->setLocale('en');
    expect(__('seviaflow-ui::ui.navigation.primary'))->toBe('Primary navigation')
        ->and(__('seviaflow-ui::ui.fields.required'))->toBe('Required');

    app()->setLocale('pl');
    expect(__('seviaflow-ui::ui.navigation.primary'))->toBe('Nawigacja główna')
        ->and(__('seviaflow-ui::ui.fields.required'))->toBe('Wymagane');

    app()->setLocale($originalLocale);
});
