<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Support\Cache;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;

final class CacheVersionStore
{
    /**
     * @param  array<string, mixed>  $scope
     */
    public function dataVersion(array $scope): string
    {
        return $this->version('data', $scope, 'data_version');
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function permissionVersion(Authenticatable|Model|null $actor, array $scope): string
    {
        $global = $this->counter('permission', $scope);
        $actorVersion = $this->counter('permission', [
            'actor' => $this->actor($actor),
            'scope' => $scope,
        ]);
        $seed = $this->seed('permission_version');

        return $global === 0 && $actorVersion === 0
            ? $seed
            : $seed.'-'.$global.'-'.$actorVersion;
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function bumpData(array $scope): void
    {
        $this->increment('data', $scope);
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function bumpPermission(Authenticatable|Model|null $actor, array $scope): void
    {
        $this->increment('permission', [
            'actor' => $this->actor($actor),
            'scope' => $scope,
        ]);
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function bumpPermissionScope(array $scope): void
    {
        $this->increment('permission', $scope);
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function version(string $kind, array $scope, string $configKey): string
    {
        $counter = $this->counter($kind, $scope);

        return $counter === 0 ? $this->seed($configKey) : $this->seed($configKey).'-'.$counter;
    }

    private function seed(string $configKey): string
    {
        $seed = config('seviaflow-ui.cache.'.$configKey, 'v1');

        return is_scalar($seed) && trim((string) $seed) !== '' ? (string) $seed : 'v1';
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function counter(string $kind, array $scope): int
    {
        $counter = $this->cache()->get($this->key($kind, $scope), 0);

        return is_numeric($counter) ? (int) $counter : 0;
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function increment(string $kind, array $scope): void
    {
        $cache = $this->cache();
        $key = $this->key($kind, $scope);

        $cache->add($key, 0, null);
        $cache->increment($key);
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function key(string $kind, array $scope): string
    {
        $this->sortRecursively($scope);

        return 'seviaflow.cache.version.'.$kind.'.'.sha1(json_encode(
            $scope,
            JSON_THROW_ON_ERROR
        ));
    }

    private function cache(): CacheRepository
    {
        $store = config('seviaflow.performance.cache.store');
        if (! is_string($store) || trim($store) === '') {
            return cache()->store();
        }

        return cache()->store($store);
    }

    /**
     * @return array{type:string, key:string}
     */
    private function actor(Authenticatable|Model|null $actor): array
    {
        if ($actor instanceof Model) {
            return [
                'type' => $actor->getMorphClass(),
                'key' => (string) $actor->getKey(),
            ];
        }

        if (! $actor instanceof Authenticatable) {
            return ['type' => 'guest', 'key' => ''];
        }

        return [
            'type' => $actor::class,
            'key' => (string) $actor->getAuthIdentifier(),
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function sortRecursively(array &$value): void
    {
        ksort($value);

        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursively($item);
            }
        }
    }
}
