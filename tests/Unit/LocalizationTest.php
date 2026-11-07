<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\View;
use SeviaFlow\Core\Resources\Fields\RichTextField;
use SeviaFlow\Ui\Auth\PasswordResetStatus;
use SeviaFlow\Ui\Navigation\NavigationMetadataCache;
use SeviaFlow\Ui\Support\Cache\CacheContext;

it('ships matching complete English and Polish translation catalogs', function (): void {
    foreach (['ui', 'panel', 'passwords'] as $group) {
        $english = Arr::dot(require __DIR__.'/../../resources/lang/en/'.$group.'.php');
        $polish = Arr::dot(require __DIR__.'/../../resources/lang/pl/'.$group.'.php');
        expect(array_keys($english))->toBe(array_keys($polish));
        foreach (['en' => $english, 'pl' => $polish] as $locale => $lines) {
            foreach ($lines as $key => $value) {
                expect($value)->toBeString();
                expect($value === '')->toBeFalse();
                expect(app('translator')->hasForLocale('seviaflow-ui::'.$group.'.'.$key, $locale))->toBeTrue();
                preg_match_all('/:[a-z_]+/', $english[$key], $enParameters);
                preg_match_all('/:[a-z_]+/', $polish[$key], $plParameters);
                $enParameters[0] = array_values(array_unique($enParameters[0]));
                $plParameters[0] = array_values(array_unique($plParameters[0]));
                sort($enParameters[0]);
                sort($plParameters[0]);
                expect($enParameters[0])->toBe($plParameters[0]);
            }
        }
    }
});

it('resolves every translation key used by package PHP and Blade sources in both locales', function (): void {
    foreach (['src', 'resources/views'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../'.$directory));
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all('/seviaflow-ui::(?:panel|ui|passwords)\.[a-z_0-9.]+/', file_get_contents($file->getPathname()), $matches);
            foreach (array_unique($matches[0]) as $key) {
                foreach (['en', 'pl'] as $locale) {
                    expect(app('translator')->hasForLocale($key, $locale))->toBeTrue();
                }
            }
        }
    }
});

it('supports standard namespaced application overrides and fallback locales', function (): void {
    app()->setLocale('pl');
    app('translator')->addLines(['panel.save' => 'Zachowaj'], 'pl', 'seviaflow-ui');
    expect(__('seviaflow-ui::panel.save'))->toBe('Zachowaj');
    app()->setLocale('de');
    app('translator')->setFallback('en');
    expect(__('seviaflow-ui::panel.save'))->toBe('Save');
});

it('keeps application password translations and custom broker statuses', function (): void {
    app()->setLocale('pl');
    app('translator')->addLines(['passwords.sent' => 'Własny komunikat'], 'pl');
    expect(PasswordResetStatus::message('passwords.sent'))->toBe('Własny komunikat')
        ->and(PasswordResetStatus::message('custom-broker-status'))->toBe('custom-broker-status');
});

it('isolates navigation and dashboard caches by locale and invalidates all navigation locales', function (): void {
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow.performance.cache.store', 'array');
    flushArrayCache();
    $cache = app(NavigationMetadataCache::class);

    app()->setLocale('en');
    $english = $cache->resolve();
    $englishContext = app(CacheContext::class)->forActor(null);
    app()->setLocale('pl');
    $polish = $cache->resolve();
    expect($polish['groups'][0]['label'])->toBe('Główne')
        ->and($english['groups'][0]['label'])->toBe('Main')
        ->and(app(CacheContext::class)->forActor(null))->not->toBe($englishContext);

    app()->setLocale('en');
    expect($cache->resolve())->toBe($english);
    app('translator')->addLines(['panel.main' => 'Changed'], 'en', 'seviaflow-ui');
    app('translator')->addLines(['panel.main' => 'Zmienione'], 'pl', 'seviaflow-ui');
    $cache->clear();
    expect($cache->resolve()['groups'][0]['label'])->toBe('Changed');
    app()->setLocale('pl');
    expect($cache->resolve()['groups'][0]['label'])->toBe('Zmienione');
});

it('uses Polish count messages for zero, one and larger counts', function (int $count): void {
    app()->setLocale('pl');
    $expected = $count === 1 ? 'Zaznaczony rekord: 1' : 'Zaznaczone rekordy: '.$count;
    expect(trans_choice('seviaflow-ui::panel.selected_records', $count, ['count' => $count]))->toBe($expected);
})->with([[0], [1], [2], [5], [12], [22], [101]]);

it('renders localized editor messages as escaped attributes for JavaScript', function (string $locale, string $prompt, string $loading): void {
    app()->setLocale($locale);
    $html = View::file(__DIR__.'/../../resources/views/fields/controls/rich-text.blade.php', [
        'field' => RichTextField::make('body'),
        'fieldId' => 'body',
        'fieldPath' => 'fieldValues.body',
        'inputValue' => 'Save',
        'isReadonly' => false,
        'isDisabled' => false,
        'isRequired' => false,
        'hasError' => false,
        'describedBy' => '',
    ])->render();

    expect($html)->toContain('data-sf-richtext-link-prompt="'.$prompt.'"')
        ->toContain('data-sf-richtext-loading-message="'.$loading.'"')
        ->toContain('data-sf-richtext-load-error=')
        ->toContain('data-sf-richtext-start-error=');
})->with([
    'English' => ['en', 'Enter a safe link URL', 'Loading rich text editor…'],
    'Polish' => ['pl', 'Wpisz bezpieczny adres odnośnika', 'Ładowanie edytora tekstu…'],
]);
