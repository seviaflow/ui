<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Support\Cache;

use SeviaFlow\Core\Support\Cache\CacheOperationResult;
use SeviaFlow\Core\Support\Cache\CacheParticipant;
use SeviaFlow\Ui\Navigation\NavigationMetadataCache;

final class NavigationMetadataCacheParticipant implements CacheParticipant
{
    public function __construct(
        private readonly NavigationMetadataCache $cache
    ) {}

    public function name(): string
    {
        return 'navigation metadata';
    }

    public function warm(): CacheOperationResult
    {
        if (! $this->cacheEnabled()) {
            return CacheOperationResult::skipped('Navigation metadata cache skipped (disabled)');
        }

        $this->cache->resolve();

        return CacheOperationResult::done('Navigation metadata cached');
    }

    public function clear(): CacheOperationResult
    {
        $this->cache->clear();

        return CacheOperationResult::done('Navigation metadata cache cleared');
    }

    private function cacheEnabled(): bool
    {
        return (bool) config('seviaflow.performance.cache.enabled', false)
            && (bool) config('seviaflow-ui.cache.navigation', true);
    }
}
