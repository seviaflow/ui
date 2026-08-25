<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Support\Cache;

use SeviaFlow\Core\Support\Cache\CacheOperationResult;
use SeviaFlow\Core\Support\Cache\CacheParticipant;
use SeviaFlow\Ui\Dashboard\DashboardSummaryCache;

final class DashboardSummaryCacheParticipant implements CacheParticipant
{
    public function __construct(
        private readonly DashboardSummaryCache $cache
    ) {}

    public function name(): string
    {
        return 'dashboard summaries';
    }

    public function warm(): CacheOperationResult
    {
        return CacheOperationResult::skipped('Dashboard summary cache skipped (user-scoped, warmed on request)');
    }

    public function clear(): CacheOperationResult
    {
        $this->cache->clear();

        return CacheOperationResult::done('Dashboard summary cache cleared');
    }
}
