<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Support\Cache;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class CacheContext
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function forActor(Authenticatable|Model|null $actor, array $context = []): array
    {
        $cacheContext = [
            'actor' => $this->actor($actor),
            'host' => $this->host(),
            'connection' => $this->connection($context),
            'permission_version' => $this->version('permission_version'),
            'data_version' => $this->version('data_version'),
            'payload' => $context,
        ];

        $this->sortRecursively($cacheContext);

        return $cacheContext;
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

    private function host(): string
    {
        $configured = config('seviaflow-ui.cache.isolation_context');
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        if (app()->bound('request')) {
            $request = app('request');
            if ($request instanceof Request && $request->getHost() !== '') {
                return $request->getHost();
            }
        }

        $applicationUrl = config('app.url');
        $applicationHost = is_string($applicationUrl) && trim($applicationUrl) !== ''
            ? parse_url($applicationUrl, PHP_URL_HOST)
            : null;

        return is_string($applicationHost) && $applicationHost !== ''
            ? $applicationHost
            : 'application';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function connection(array $context): string
    {
        $connection = $context['connection'] ?? config('database.default', 'default');

        return is_scalar($connection) && trim((string) $connection) !== ''
            ? (string) $connection
            : 'default';
    }

    private function version(string $key): string
    {
        $version = config('seviaflow-ui.cache.'.$key, 'v1');

        return is_scalar($version) && trim((string) $version) !== ''
            ? (string) $version
            : 'v1';
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
