<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use SeviaFlow\Ui\Dashboard\Dashboard;
use SeviaFlow\Ui\Dashboard\Widgets\DashboardWidgetResolver;
use SeviaFlow\Ui\Dashboard\Widgets\Widget;

it('creates widget with name', function (): void {
    $widget = Widget::make('pending_orders');

    expect($widget->name())->toBe('pending_orders');
});

it('uses humanized name as default label', function (): void {
    $widget = Widget::make('pending_orders');

    expect($widget->getLabel())->toBe('Pending Orders');
});

it('stores column span metadata', function (): void {
    $widget = Widget::make('recent_orders')->columnSpan(2);

    expect($widget->getColumnSpan())->toBe(2);
});

it('visibleIf can hide widget', function (): void {
    $widget = Widget::make('sensitive')->visibleIf(static fn (): bool => false);

    expect($widget->isVisible())->toBeFalse();
});

it('stores cache ttl metadata', function (): void {
    $widget = Widget::make('cached')->cacheFor(120);

    expect($widget->getCacheTtl())->toBe(120);
});

it('resolves only visible widgets for dashboard', function (): void {
    $resolver = new DashboardWidgetResolver;

    $widgets = $resolver->resolve(WidgetVisibleDashboard::class, new WidgetUser);

    expect($widgets)->toHaveCount(1)
        ->and($widgets[0]->name())->toBe('visible_one');
});

it('fails clearly when dashboard defines duplicate widget names', function (): void {
    $resolver = new DashboardWidgetResolver;

    expect(fn (): array => $resolver->resolve(WidgetDuplicateDashboard::class, null))
        ->toThrow(InvalidArgumentException::class, 'duplicate widget name');
});

final class WidgetVisibleDashboard extends Dashboard
{
    public static function slug(): string
    {
        return 'widgets-visible';
    }

    public static function label(): string
    {
        return 'Widgets Visible';
    }

    public static function widgets(): array
    {
        return [
            Widget::make('visible_one'),
            Widget::make('hidden_one')->visibleIf(static fn (): bool => false),
        ];
    }
}

final class WidgetDuplicateDashboard extends Dashboard
{
    public static function slug(): string
    {
        return 'widgets-duplicate';
    }

    public static function label(): string
    {
        return 'Widgets Duplicate';
    }

    public static function widgets(): array
    {
        return [
            Widget::make('same_name'),
            Widget::make('same_name'),
        ];
    }
}

final class WidgetUser extends AuthenticatableUser {}
