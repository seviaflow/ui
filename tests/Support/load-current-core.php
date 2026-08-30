<?php

declare(strict_types=1);

$currentCoreSource = dirname(__DIR__, 3).'/core/src/';

if (is_dir($currentCoreSource)) {
    spl_autoload_register(static function (string $class) use ($currentCoreSource): void {
        $prefix = 'SeviaFlow\\Core\\';

        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $relativePath = str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        $path = $currentCoreSource.$relativePath;

        if (is_file($path)) {
            require $path;
        }
    }, true, true);
}
