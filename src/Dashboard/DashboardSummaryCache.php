<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Ui\Support\Cache\CacheContext;

final class DashboardSummaryCache
{
    private const INDEX_KEY = 'seviaflow.dashboard.index';

    public function __construct(
        private readonly CacheContext $context = new CacheContext
    ) {}

    /**
     * @template T
     *
     * @param  callable():T  $resolver
     * @return T
     */
    public function rememberForUser(
        string $section,
        ?Authenticatable $user,
        array $context,
        callable $resolver
    ): mixed {
        if (! $this->isEnabled() || ! $user instanceof Model) {
            return $resolver();
        }

        $cache = $this->cache();

        $key = $this->cacheKey($section, $user, $context);
        $this->trackKey($cache, $key);

        return $cache->remember($key, $this->ttl(), $resolver);
    }

    public function clear(): void
    {
        $cache = $this->cache();

        /** @var array<int, string> $keys */
        $keys = $cache->get(self::INDEX_KEY, []);
        foreach ($keys as $key) {
            if (is_string($key) && $key !== '') {
                $cache->forget($key);
            }
        }

        $cache->forget(self::INDEX_KEY);
    }

    private function isEnabled(): bool
    {
        return (bool) config('seviaflow.performance.cache.enabled', false)
            && (bool) config('seviaflow-ui.cache.dashboard', false);
    }

    private function ttl(): int
    {
        return max(
            1,
            (int) config(
                'seviaflow-ui.dashboard.cache_ttl',
                (int) config('seviaflow.performance.cache.ttl', 3600)
            )
        );
    }

    private function cacheKey(string $section, Model $user, array $context): string
    {
        return sprintf(
            'seviaflow.dashboard.%s.%s',
            $section,
            sha1(json_encode(
                $this->context->forActor($user, $context),
                JSON_THROW_ON_ERROR
            ))
        );
    }

    private function cache(): CacheRepository
    {
        $store = config('seviaflow.performance.cache.store');
        if (! is_string($store) || trim($store) === '') {
            return cache()->store();
        }

        return cache()->store($store);
    }

    private function trackKey(CacheRepository $cache, string $key): void
    {
        /** @var array<int, string> $keys */
        $keys = $cache->get(self::INDEX_KEY, []);
        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            $cache->put(self::INDEX_KEY, array_values($keys), $this->ttl());
        }
    }
}
