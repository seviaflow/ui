<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Ui\Dashboard\DashboardSummaryCache;

beforeEach(function (): void {
    config()->set('cache.default', 'array');
    config()->set('seviaflow.performance.cache.store', 'array');
    flushArrayCache();

    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->timestamps();
        });
    }
});

it('is disabled by default and does not cache resolver results', function (): void {
    config()->set('seviaflow.performance.cache.enabled', false);
    config()->set('seviaflow.performance.cache.dashboard', false);

    $cache = app(DashboardSummaryCache::class);
    $user = DashboardCacheUser::query()->create(['name' => 'A', 'email' => 'a@example.com']);
    $calls = 0;

    $cache->rememberForUser('resource_overview', $user, ['x' => 1], function () use (&$calls): array {
        $calls++;

        return ['calls' => $calls];
    });
    $cache->rememberForUser('resource_overview', $user, ['x' => 1], function () use (&$calls): array {
        $calls++;

        return ['calls' => $calls];
    });

    expect($calls)->toBe(2);
});

it('uses user-scoped cache keys to avoid cross-user leakage', function (): void {
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow.performance.cache.dashboard', true);

    $cache = app(DashboardSummaryCache::class);
    $userA = DashboardCacheUser::query()->create(['name' => 'A', 'email' => 'cache-a@example.com']);
    $userB = DashboardCacheUser::query()->create(['name' => 'B', 'email' => 'cache-b@example.com']);

    $calls = 0;

    $firstA = $cache->rememberForUser('workflow_overview', $userA, ['foo' => 'bar'], function () use (&$calls): array {
        $calls++;

        return ['token' => 'u1-'.$calls];
    });

    $secondA = $cache->rememberForUser('workflow_overview', $userA, ['foo' => 'bar'], function () use (&$calls): array {
        $calls++;

        return ['token' => 'u1-'.$calls];
    });

    $firstB = $cache->rememberForUser('workflow_overview', $userB, ['foo' => 'bar'], function () use (&$calls): array {
        $calls++;

        return ['token' => 'u2-'.$calls];
    });

    expect($firstA)->toBe($secondA)
        ->and($firstB)->not->toBe($firstA)
        ->and($calls)->toBe(2);
});

it('clear removes tracked dashboard keys', function (): void {
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow.performance.cache.dashboard', true);

    $cache = app(DashboardSummaryCache::class);
    $user = DashboardCacheUser::query()->create(['name' => 'A', 'email' => 'clear@example.com']);

    $cache->rememberForUser('resource_overview', $user, ['a' => 1], fn (): array => ['ok' => true]);

    expect(cache()->store('array')->get('seviaflow-ui.dashboard.index'))->toBeArray();

    $cache->clear();

    expect(cache()->store('array')->get('seviaflow-ui.dashboard.index'))->toBeNull();
});

final class DashboardCacheUser extends AuthenticatableUser
{
    protected $table = 'users';

    protected $guarded = [];
}
