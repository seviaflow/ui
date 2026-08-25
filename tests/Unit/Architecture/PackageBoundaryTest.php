<?php

declare(strict_types=1);

it('ui depends on core and livewire without hard API imports', function (): void {
    $root = dirname(__DIR__, 3);
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true);

    $requirements = $composer['require'] ?? [];

    expect($requirements)->toHaveKey('seviaflow/core');
    expect($requirements)->toHaveKey('livewire/livewire');
    expect(array_key_exists('seviaflow/api', $requirements))->toBeFalse();
    expect(array_key_exists('seviaflow/devtools', $requirements))->toBeFalse();

    expect(str_contains(scanPackageText($root, ['src', 'routes', 'resources', 'config']), 'SeviaFlow\\Api'))->toBeFalse();
});

function scanPackageText(string $root, array $directories): string
{
    $buffer = '';

    foreach ($directories as $directory) {
        $path = $root.'/'.$directory;
        if (! is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $buffer .= (string) file_get_contents($file->getPathname());
            }
        }
    }

    return $buffer;
}
