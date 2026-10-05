<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use SeviaFlow\Ui\Dashboard\Widgets\WidgetCache;
use SeviaFlow\Ui\Support\Cache\CacheContext;

beforeEach(function (): void {
    config()->set('cache.default', 'array');
    config()->set('seviaflow.performance.cache.store', 'array');
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow-ui.cache.dashboard', true);
    config()->set('seviaflow-ui.cache.isolation_context', 'host-a');
    config()->set('seviaflow-ui.cache.permission_version', 'permissions-1');
    config()->set('seviaflow-ui.cache.data_version', 'data-1');
    config()->set('database.default', 'testing');
    flushArrayCache();
});

it('builds a stable context with actor, host, connection and invalidation versions', function (): void {
    $user = new CacheContextTestUser;
    $user->setAttribute('id', 7);

    $context = (new CacheContext)->forActor($user, ['filters' => ['status' => 'open']]);

    expect($context['actor']['type'])->toBe(CacheContextTestUser::class)
        ->and($context['actor']['key'])->toBe('7')
        ->and($context['host'])->toBe('host-a')
        ->and($context['connection'])->toBe('testing')
        ->and($context['permission_version'])->toBe('permissions-1')
        ->and($context['data_version'])->toBe('data-1');
});

it('does not share widget values across host or data contexts', function (): void {
    $user = new CacheContextTestUser;
    $user->setAttribute('id', 7);
    $cache = new WidgetCache;
    $calls = 0;

    $first = $cache->remember('stats', 'orders', 'main', $user, [], 60, function () use (&$calls): int {
        return ++$calls;
    });
    $sameContext = $cache->remember('stats', 'orders', 'main', $user, [], 60, function () use (&$calls): int {
        return ++$calls;
    });

    config()->set('seviaflow-ui.cache.isolation_context', 'host-b');
    $otherHost = $cache->remember('stats', 'orders', 'main', $user, [], 60, function () use (&$calls): int {
        return ++$calls;
    });

    config()->set('seviaflow-ui.cache.data_version', 'data-2');
    $otherDataVersion = $cache->remember('stats', 'orders', 'main', $user, [], 60, function () use (&$calls): int {
        return ++$calls;
    });

    expect($first)->toBe(1)
        ->and($sameContext)->toBe(1)
        ->and($otherHost)->toBe(2)
        ->and($otherDataVersion)->toBe(3)
        ->and($calls)->toBe(3);
});

it('does not share widget values across connection or permission contexts', function (): void {
    $user = new CacheContextTestUser;
    $user->setAttribute('id', 7);
    $cache = new WidgetCache;
    $calls = 0;

    $first = $cache->remember('stats', 'orders', 'main', $user, [], 60, function () use (&$calls): int {
        return ++$calls;
    });
    $sameContext = $cache->remember('stats', 'orders', 'main', $user, [], 60, function () use (&$calls): int {
        return ++$calls;
    });

    config()->set('database.default', 'reporting');
    $otherConnection = $cache->remember('stats', 'orders', 'main', $user, [], 60, function () use (&$calls): int {
        return ++$calls;
    });

    config()->set('seviaflow-ui.cache.permission_version', 'permissions-2');
    $otherPermissionVersion = $cache->remember('stats', 'orders', 'main', $user, [], 60, function () use (&$calls): int {
        return ++$calls;
    });

    expect($first)->toBe(1)
        ->and($sameContext)->toBe(1)
        ->and($otherConnection)->toBe(2)
        ->and($otherPermissionVersion)->toBe(3)
        ->and($calls)->toBe(3);
});

final class CacheContextTestUser extends AuthenticatableUser
{
    protected $table = 'cache_context_users';
}
