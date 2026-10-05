<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Widgets;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use SeviaFlow\Ui\Support\Cache\CacheContext;

final class WidgetCache
{
    private const INDEX_KEY = 'seviaflow.dashboard.widgets.index';

    public function __construct(
        private readonly CacheContext $context = new CacheContext
    ) {}

    /**
     * @template T
     *
     * @param  array<string, mixed>  $context
     * @param  callable():T  $resolver
     * @return T
     */
    public function remember(
        string $widgetType,
        string $widgetName,
        ?string $dashboardSlug,
        ?Authenticatable $user,
        array $context,
        ?int $ttl,
        callable $resolver
    ): mixed {
        if ($ttl === null || $ttl < 1 || ! $this->isEnabled()) {
            return $resolver();
        }

        $cache = $this->cache();

        $key = $this->cacheKey($widgetType, $widgetName, $dashboardSlug, $user, $context);
        $this->trackKey($cache, $key, $ttl);

        return $cache->remember($key, $ttl, $resolver);
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

    /**
     * @param  array<string, mixed>  $context
     */
    private function cacheKey(
        string $widgetType,
        string $widgetName,
        ?string $dashboardSlug,
        ?Authenticatable $user,
        array $context
    ): string {
        $contextFingerprint = sha1(json_encode(
            $this->context->forActor($user, $context),
            JSON_THROW_ON_ERROR
        ));

        return sprintf(
            'seviaflow.dashboard.widget.%s.%s.%s.%s.%s',
            $widgetType,
            $dashboardSlug ?? 'default',
            $widgetName,
            $contextFingerprint,
            sha1((string) config('app.env'))
        );
    }

    private function isEnabled(): bool
    {
        return (bool) config('seviaflow.performance.cache.enabled', false)
            && (bool) config('seviaflow-ui.cache.dashboard', false);
    }

    private function cache(): CacheRepository
    {
        $store = config('seviaflow.performance.cache.store');
        if (! is_string($store) || trim($store) === '') {
            return cache()->store();
        }

        return cache()->store($store);
    }

    private function trackKey(CacheRepository $cache, string $key, int $ttl): void
    {
        /** @var array<int, string> $keys */
        $keys = $cache->get(self::INDEX_KEY, []);
        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            $cache->put(self::INDEX_KEY, array_values($keys), $ttl);
        }
    }
}
