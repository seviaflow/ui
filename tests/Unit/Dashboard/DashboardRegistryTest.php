<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use SeviaFlow\Ui\Dashboard\Dashboard;
use SeviaFlow\Ui\Dashboard\DashboardRegistry;
use SeviaFlow\Ui\Dashboard\DefaultDashboard;

it('supports registering multiple dashboards', function (): void {
    $registry = new DashboardRegistry([
        RegistryOperationsDashboard::class,
        RegistrySalesDashboard::class,
    ]);

    expect(array_keys($registry->all()))
        ->toBe(['operations', 'sales']);
});

it('resolves configured default dashboard when visible', function (): void {
    config()->set('seviaflow-ui.dashboard.default', 'sales');

    $registry = new DashboardRegistry([
        RegistryOperationsDashboard::class,
        RegistrySalesDashboard::class,
    ]);

    $resolved = $registry->resolveDefault(null);
    expect($resolved)->toBe(RegistrySalesDashboard::class);
});

it('falls back to first visible dashboard when configured default is hidden', function (): void {
    config()->set('seviaflow-ui.dashboard.default', 'hidden');

    $registry = new DashboardRegistry([
        RegistryHiddenDashboard::class,
        RegistryOperationsDashboard::class,
    ]);

    $resolved = $registry->resolveDefault(new RegistryUser);
    expect($resolved)->toBe(RegistryOperationsDashboard::class);
});

it('uses built in fallback dashboard when no dashboards are configured', function (): void {
    $registry = new DashboardRegistry([]);

    $resolved = $registry->resolveDefault(new RegistryUser);
    expect($resolved)->toBe(DefaultDashboard::class);
});

it('returns null when requested dashboard is not visible', function (): void {
    $registry = new DashboardRegistry([
        RegistryHiddenDashboard::class,
    ]);

    $resolved = $registry->resolve('hidden', new RegistryUser);
    expect($resolved)->toBeNull();
});

it('fails clearly for duplicate dashboard slug', function (): void {
    expect(fn (): DashboardRegistry => new DashboardRegistry([
        RegistryOperationsDashboard::class,
        RegistryOperationsDuplicateDashboard::class,
    ]))->toThrow(InvalidArgumentException::class, 'Duplicate dashboard slug');
});

it('fails clearly for invalid dashboard slug', function (): void {
    expect(fn (): DashboardRegistry => new DashboardRegistry([
        RegistryInvalidSlugDashboard::class,
    ]))->toThrow(InvalidArgumentException::class, 'slug');
});

final class RegistryOperationsDashboard extends Dashboard
{
    public static function slug(): string
    {
        return 'operations';
    }

    public static function label(): string
    {
        return 'Operations';
    }
}

final class RegistrySalesDashboard extends Dashboard
{
    public static function slug(): string
    {
        return 'sales';
    }

    public static function label(): string
    {
        return 'Sales';
    }
}

final class RegistryHiddenDashboard extends Dashboard
{
    public static function slug(): string
    {
        return 'hidden';
    }

    public static function label(): string
    {
        return 'Hidden';
    }

    public static function visibleFor(mixed $user): bool
    {
        return false;
    }
}

final class RegistryOperationsDuplicateDashboard extends Dashboard
{
    public static function slug(): string
    {
        return 'operations';
    }

    public static function label(): string
    {
        return 'Operations Duplicate';
    }
}

final class RegistryInvalidSlugDashboard extends Dashboard
{
    public static function slug(): string
    {
        return 'Invalid Slug';
    }

    public static function label(): string
    {
        return 'Invalid';
    }
}

final class RegistryUser extends AuthenticatableUser {}
