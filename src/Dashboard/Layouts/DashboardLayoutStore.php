<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Layouts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use SeviaFlow\Ui\Dashboard\Dashboard;
use SeviaFlow\Ui\Dashboard\Widgets\DashboardWidgetResolver;

final class DashboardLayoutStore
{
    public function __construct(
        private readonly DashboardLayoutRepository $repository = new DashboardLayoutRepository,
        private readonly DashboardWidgetResolver $widgetResolver = new DashboardWidgetResolver,
        private readonly SavedDashboardRepository $savedDashboardRepository = new SavedDashboardRepository,
    ) {}

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     * @return array<int, array{widget:string,x:int,y:int,w:int,h:int,visible:bool}>
     */
    public function getLayout(string $dashboardClass, Authenticatable|Model|null $user = null): array
    {
        $this->assertDashboardClass($dashboardClass);

        $default = $this->defaultLayout($dashboardClass, $user);

        if (! $user instanceof Model) {
            return $default;
        }

        $saved = $this->repository->loadForUser($dashboardClass::slug(), $user);
        if ($saved instanceof DashboardLayout) {
            return $this->mergeWithDefault($saved->layout ?? [], $default);
        }

        $savedDefault = $this->savedDashboardRepository->defaultForDashboard($dashboardClass::slug(), $user);
        if ($savedDefault instanceof SavedDashboard) {
            return $this->mergeWithDefault($savedDefault->layout ?? [], $default);
        }

        return $default;
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     * @param  array<int, array<string,mixed>>  $layout
     * @return array<int, array{widget:string,x:int,y:int,w:int,h:int,visible:bool}>
     */
    public function saveLayout(string $dashboardClass, Authenticatable|Model|null $user, array $layout): array
    {
        $this->assertDashboardClass($dashboardClass);

        if (! $user instanceof Model) {
            throw new InvalidArgumentException('Dashboard layouts require an authenticated user.');
        }

        $normalized = $this->normalizeLayout($dashboardClass, $user, $layout);

        $this->repository->saveForUser($user, $dashboardClass::slug(), $normalized);

        return $normalized;
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     * @param  array<int, array<string,mixed>>  $layout
     * @return array<int, array{widget:string,x:int,y:int,w:int,h:int,visible:bool}>
     */
    public function normalizeLayout(string $dashboardClass, Authenticatable|Model|null $user, array $layout): array
    {
        $this->assertDashboardClass($dashboardClass);

        $default = $this->defaultLayout($dashboardClass, $user);

        return $this->mergeWithDefault($layout, $default);
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     * @return array<int, array{widget:string,x:int,y:int,w:int,h:int,visible:bool}>
     */
    public function resetLayout(string $dashboardClass, Authenticatable|Model|null $user = null): array
    {
        $this->assertDashboardClass($dashboardClass);

        if (! $user instanceof Model) {
            return $this->defaultLayout($dashboardClass, $user);
        }

        $this->repository->resetForUser($dashboardClass::slug(), $user);

        return $this->defaultLayout($dashboardClass, $user);
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     * @return array<int, array{widget:string,x:int,y:int,w:int,h:int,visible:bool}>
     */
    public function defaultLayout(string $dashboardClass, Authenticatable|Model|null $user = null): array
    {
        $this->assertDashboardClass($dashboardClass);

        $widgets = $this->widgetResolver->resolve($dashboardClass, $user);

        $layout = [];
        $y = 0;

        foreach ($widgets as $widget) {
            $span = $widget->getColumnSpan();
            $width = is_int($span) ? max(1, min($span, 3)) : 1;

            $layout[] = [
                'widget' => $widget->name(),
                'x' => 0,
                'y' => $y,
                'w' => $width,
                'h' => 1,
                'visible' => true,
            ];

            $y++;
        }

        return $layout;
    }

    /**
     * @param  array<int, array<string,mixed>>  $layout
     * @param  array<int, array{widget:string,x:int,y:int,w:int,h:int,visible:bool}>  $default
     * @return array<int, array{widget:string,x:int,y:int,w:int,h:int,visible:bool}>
     */
    private function mergeWithDefault(array $layout, array $default): array
    {
        $defaultByWidget = [];
        foreach ($default as $entry) {
            $defaultByWidget[$entry['widget']] = $entry;
        }

        $normalized = [];
        foreach ($layout as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $widget = $entry['widget'] ?? null;
            if (! is_string($widget) || ! isset($defaultByWidget[$widget])) {
                continue;
            }

            $normalized[$widget] = [
                'widget' => $widget,
                'x' => $this->normalizeInt($entry['x'] ?? $defaultByWidget[$widget]['x'], 0),
                'y' => $this->normalizeInt($entry['y'] ?? $defaultByWidget[$widget]['y'], 0),
                'w' => $this->normalizeInt($entry['w'] ?? $defaultByWidget[$widget]['w'], 1),
                'h' => $this->normalizeInt($entry['h'] ?? $defaultByWidget[$widget]['h'], 1),
                'visible' => $this->normalizeBool($entry['visible'] ?? $defaultByWidget[$widget]['visible']),
            ];
        }

        foreach ($default as $entry) {
            if (! isset($normalized[$entry['widget']])) {
                $normalized[$entry['widget']] = $entry;
            }
        }

        return array_values($normalized);
    }

    private function normalizeInt(mixed $value, int $min): int
    {
        if (! is_int($value)) {
            return $min;
        }

        return max($min, $value);
    }

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return (bool) $value;
    }

    /**
     * @param  class-string  $dashboardClass
     */
    private function assertDashboardClass(string $dashboardClass): void
    {
        if (! is_a($dashboardClass, Dashboard::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Dashboard layout class [%s] must extend %s.',
                $dashboardClass,
                Dashboard::class
            ));
        }
    }
}
