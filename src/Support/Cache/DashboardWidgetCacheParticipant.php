<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Support\Cache;

use SeviaFlow\Core\Support\Cache\CacheOperationResult;
use SeviaFlow\Core\Support\Cache\CacheParticipant;
use SeviaFlow\Ui\Dashboard\Widgets\WidgetCache;

final class DashboardWidgetCacheParticipant implements CacheParticipant
{
    public function __construct(
        private readonly WidgetCache $cache
    ) {}

    public function name(): string
    {
        return 'dashboard widgets';
    }

    public function warm(): CacheOperationResult
    {
        return CacheOperationResult::skipped('Dashboard widget cache skipped (user/context-scoped, warmed on request)');
    }

    public function clear(): CacheOperationResult
    {
        $this->cache->clear();

        return CacheOperationResult::done('Dashboard widget cache cleared');
    }
}
