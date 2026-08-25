<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Navigation;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class NavigationMetadataCache
{
    private const CACHE_KEY = 'seviaflow.navigation.base';

    public function __construct(
        private readonly NavigationMetadataResolver $resolver
    ) {}

    /**
     * @return array{
     *   groups: array<int, array{
     *     key:string,
     *     label:string,
     *     items: array<int, array<string,mixed>>
     *   }>
     * }
     */
    public function resolve(): array
    {
        if (! $this->isEnabled()) {
            return $this->resolver->resolve();
        }

        $cache = $this->cache();

        /** @var array{
         *   groups: array<int, array{
         *     key:string,
         *     label:string,
         *     items: array<int, array<string,mixed>>
         *   }>
         * } $metadata
         */
        $metadata = $cache->remember(self::CACHE_KEY, $this->ttl(), fn (): array => $this->resolver->resolve());

        return $metadata;
    }

    public function clear(): void
    {
        $this->cache()->forget(self::CACHE_KEY);
    }

    private function isEnabled(): bool
    {
        return (bool) config('seviaflow.performance.cache.enabled', false)
            && (bool) config('seviaflow-ui.cache.navigation', true);
    }

    private function ttl(): int
    {
        return max(1, (int) config('seviaflow.performance.cache.ttl', 3600));
    }

    private function cache(): CacheRepository
    {
        $store = config('seviaflow.performance.cache.store');
        if (! is_string($store) || trim($store) === '') {
            return cache()->store();
        }

        return cache()->store($store);
    }
}
