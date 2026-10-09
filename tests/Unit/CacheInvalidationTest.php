<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\DB;
use SeviaFlow\Core\Events\Auth\RoleAssignmentsChanged;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceEventDispatcher;
use SeviaFlow\Ui\Dashboard\Widgets\WidgetCache;
use SeviaFlow\Ui\Support\Cache\CacheInvalidator;

beforeEach(function (): void {
    config()->set('cache.default', 'array');
    config()->set('seviaflow.performance.cache.store', 'array');
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow-ui.cache.dashboard', true);
    config()->set('seviaflow-ui.cache.isolation_context', 'shared-host');
    config()->set('seviaflow-ui.cache.permission_version', 'permissions-1');
    config()->set('seviaflow-ui.cache.data_version', 'data-1');
    config()->set('database.default', 'testing');
    flushArrayCache();
});

it('invalidates heated permission cache after a role assignment event', function (): void {
    $user = new CacheInvalidationUser;
    $user->setAttribute('id', 7);
    $cache = new WidgetCache;
    $context = ['tenant' => 'tenant-a', 'connection' => 'shared'];
    $calls = 0;

    $first = $cache->remember('stats', 'orders', 'main', $user, $context, 60, function () use (&$calls): string {
        $calls++;

        return 'allowed-'.$calls;
    });

    $sameRequest = $cache->remember('stats', 'orders', 'main', $user, $context, 60, fn (): string => 'stale');
    event(new RoleAssignmentsChanged($user, $context));
    $nextRequest = $cache->remember('stats', 'orders', 'main', $user, $context, 60, function () use (&$calls): string {
        $calls++;

        return 'revoked-'.$calls;
    });

    expect($first)->toBe('allowed-1')
        ->and($sameRequest)->toBe('allowed-1')
        ->and($nextRequest)->toBe('revoked-2')
        ->and($calls)->toBe(2);
});

it('invalidates heated permission cache after a permission scope change', function (): void {
    $user = new CacheInvalidationUser;
    $user->setAttribute('id', 7);
    $cache = new WidgetCache;
    $context = ['tenant' => 'tenant-a', 'connection' => 'shared'];
    $calls = 0;

    expect($cache->remember('stats', 'orders', 'main', $user, $context, 60, function () use (&$calls): string {
        $calls++;

        return 'before-'.$calls;
    }))->toBe('before-1');

    app(CacheInvalidator::class)->permissionsChanged($context);

    expect($cache->remember('stats', 'orders', 'main', $user, $context, 60, function () use (&$calls): string {
        $calls++;

        return 'after-'.$calls;
    }))->toBe('after-2');
});

it('keeps tenant results isolated for the same actor and connection', function (): void {
    $user = new CacheInvalidationUser;
    $user->setAttribute('id', 7);
    $cache = new WidgetCache;
    $calls = 0;

    $tenantA = $cache->remember('stats', 'orders', 'main', $user, [
        'tenant' => 'tenant-a',
        'connection' => 'shared',
    ], 60, function () use (&$calls): string {
        $calls++;

        return 'tenant-a-'.$calls;
    });
    $tenantB = $cache->remember('stats', 'orders', 'main', $user, [
        'tenant' => 'tenant-b',
        'connection' => 'shared',
    ], 60, function () use (&$calls): string {
        $calls++;

        return 'tenant-b-'.$calls;
    });
    $tenantAAgain = $cache->remember('stats', 'orders', 'main', $user, [
        'tenant' => 'tenant-a',
        'connection' => 'shared',
    ], 60, fn (): string => 'wrong');

    expect($tenantA)->toBe('tenant-a-1')
        ->and($tenantB)->toBe('tenant-b-2')
        ->and($tenantAAgain)->toBe('tenant-a-1')
        ->and($calls)->toBe(2);
});

it('invalidates data after commit but not after rollback', function (): void {
    $user = new CacheInvalidationUser;
    $user->setAttribute('id', 7);
    $cache = new WidgetCache;
    $context = ['tenant' => 'tenant-a', 'connection' => 'shared'];
    $calls = 0;
    $dispatcher = new ResourceEventDispatcher;

    $remember = function (string $value) use ($cache, $user, $context, &$calls): string {
        return $cache->remember('stats', 'orders', 'main', $user, $context, 60, function () use ($value, &$calls): string {
            $calls++;

            return $value.'-'.$calls;
        });
    };

    expect($remember('before'))->toBe('before-1');

    DB::beginTransaction();
    $dispatcher->dispatchAfterCommit(
        CacheInvalidationResource::class,
        ResourceEventContext::OPERATION_UPDATED,
        new CacheInvalidationRecord,
        cacheInvalidationContext($context, 'rollback'),
    );
    DB::rollBack();

    expect($remember('rollback'))->toBe('before-1');

    DB::transaction(function () use ($dispatcher, $context): void {
        $dispatcher->dispatchAfterCommit(
            CacheInvalidationResource::class,
            ResourceEventContext::OPERATION_UPDATED,
            new CacheInvalidationRecord,
            cacheInvalidationContext($context, 'commit'),
        );
    });

    expect($remember('after'))->toBe('after-2')
        ->and($calls)->toBe(2);
});

function cacheInvalidationContext(array $context, string $correlationId): ResourceEventContext
{
    return new ResourceEventContext(
        user: null,
        resourceClass: CacheInvalidationResource::class,
        resourceSlug: 'cache-invalidation',
        operation: ResourceEventContext::OPERATION_UPDATED,
        source: ResourceEventContext::SOURCE_SYSTEM,
        metadata: ['tenant' => $context['tenant']],
        connection: $context['connection'],
        correlationId: $correlationId,
    );
}

final class CacheInvalidationUser extends AuthenticatableUser
{
    protected $table = 'cache_invalidation_users';
}

final class CacheInvalidationRecord extends Model
{
    protected $table = 'cache_invalidation_records';
}

final class CacheInvalidationResource extends Resource
{
    protected static string $model = CacheInvalidationRecord::class;
}
