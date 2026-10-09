<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Support\Cache;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class CacheContext
{
    public function __construct(
        private readonly CacheVersionStore $versions = new CacheVersionStore,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function forActor(Authenticatable|Model|null $actor, array $context = []): array
    {
        $scope = $this->scope($context);
        $cacheContext = [
            'actor' => $this->actor($actor),
            'host' => $scope['host'],
            'connection' => $scope['connection'],
            'tenant' => $scope['tenant'],
            'permission_version' => $this->versions->permissionVersion($actor, $scope),
            'data_version' => $this->versions->dataVersion($scope),
            'payload' => $context,
        ];

        $this->sortRecursively($cacheContext);

        return $cacheContext;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{host:string, connection:string, tenant:mixed}
     */
    public function scope(array $context = [], ?string $connection = null): array
    {
        $scope = [
            'host' => $this->host(),
            'connection' => $this->connection($context, $connection),
            'tenant' => $this->tenant($context),
        ];

        $this->sortRecursively($scope);

        return $scope;
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
    private function connection(array $context, ?string $connection = null): string
    {
        $connection = $connection ?? $context['connection'] ?? config('database.default', 'default');

        return is_scalar($connection) && trim((string) $connection) !== ''
            ? (string) $connection
            : 'default';
    }

    private function tenant(array $context): mixed
    {
        if (! array_key_exists('tenant', $context)) {
            return 'unscoped';
        }

        return $this->normalize($context['tenant']);
    }

    private function normalize(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[(string) $key] = $this->normalize($item);
            }
            ksort($normalized);

            return $normalized;
        }

        return get_debug_type($value);
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
