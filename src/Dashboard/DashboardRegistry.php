<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use SeviaFlow\Core\Auth\Permissions\PermissionAbilities;
use SeviaFlow\Core\Auth\Permissions\PermissionChecker;
use SeviaFlow\Core\Auth\Roles\Role;

final class DashboardRegistry
{
    /**
     * @var array<string, class-string<Dashboard>>
     */
    private array $dashboardsBySlug;

    /**
     * @var array<int, class-string<Dashboard>>
     */
    private array $dashboardsInOrder;

    /**
     * @param  array<int, mixed>|null  $configuredDashboards
     * @param  class-string<Dashboard>  $fallbackDashboard
     */
    public function __construct(
        ?array $configuredDashboards = null,
        private readonly string $fallbackDashboard = DefaultDashboard::class,
    ) {
        $configuredDashboards ??= (array) config('seviaflow-ui.dashboards', []);

        $dashboards = [];
        foreach ($configuredDashboards as $configuredDashboard) {
            if (! is_string($configuredDashboard) || trim($configuredDashboard) === '') {
                continue;
            }

            $dashboards[] = $configuredDashboard;
        }

        if ($dashboards === []) {
            $dashboards[] = $this->fallbackDashboard;
        }
        $dashboards = array_values(array_unique($dashboards));

        $this->dashboardsBySlug = [];
        $this->dashboardsInOrder = [];

        foreach ($dashboards as $dashboardClass) {
            $this->registerDashboard($dashboardClass);
        }
    }

    /**
     * @return array<string, class-string<Dashboard>>
     */
    public function all(): array
    {
        return $this->dashboardsBySlug;
    }

    /**
     * @return array<string, class-string<Dashboard>>
     */
    public function visibleFor(?Authenticatable $user): array
    {
        $visible = [];

        foreach ($this->dashboardsInOrder as $dashboardClass) {
            if (! $this->isVisible($dashboardClass, $user)) {
                continue;
            }

            $visible[$dashboardClass::slug()] = $dashboardClass;
        }

        return $visible;
    }

    /**
     * @return class-string<Dashboard>|null
     */
    public function resolve(string $slug, ?Authenticatable $user): ?string
    {
        $dashboardClass = $this->dashboardsBySlug[$slug] ?? null;
        if ($dashboardClass === null) {
            return null;
        }

        return $this->isVisible($dashboardClass, $user) ? $dashboardClass : null;
    }

    /**
     * @return class-string<Dashboard>|null
     */
    public function resolveDefault(?Authenticatable $user): ?string
    {
        $configuredDefault = config('seviaflow-ui.dashboard.default');

        if (is_string($configuredDefault) && trim($configuredDefault) !== '') {
            $resolved = str_contains($configuredDefault, '\\')
                ? $this->resolveByClass($configuredDefault, $user)
                : $this->resolve($configuredDefault, $user);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        foreach ($this->dashboardsInOrder as $dashboardClass) {
            if ($this->isVisible($dashboardClass, $user)) {
                return $dashboardClass;
            }
        }

        return null;
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     * @return class-string<Dashboard>|null
     */
    private function resolveByClass(string $dashboardClass, ?Authenticatable $user): ?string
    {
        if (! in_array($dashboardClass, $this->dashboardsInOrder, true)) {
            return null;
        }

        return $this->isVisible($dashboardClass, $user) ? $dashboardClass : null;
    }

    /**
     * @param  class-string  $dashboardClass
     */
    private function registerDashboard(string $dashboardClass): void
    {
        if (! is_a($dashboardClass, Dashboard::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Dashboard class [%s] must extend %s.',
                $dashboardClass,
                Dashboard::class
            ));
        }

        $slug = trim($dashboardClass::slug());
        if (! $this->isValidSlug($slug)) {
            throw new InvalidArgumentException(sprintf(
                'Dashboard slug [%s] for class [%s] is invalid. Use lowercase letters, numbers, and dashes.',
                $slug,
                $dashboardClass
            ));
        }

        if (isset($this->dashboardsBySlug[$slug])) {
            throw new InvalidArgumentException(sprintf(
                'Duplicate dashboard slug [%s] detected for classes [%s] and [%s].',
                $slug,
                $this->dashboardsBySlug[$slug],
                $dashboardClass
            ));
        }

        $this->dashboardsBySlug[$slug] = $dashboardClass;
        $this->dashboardsInOrder[] = $dashboardClass;
    }

    private function isValidSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     */
    private function isVisible(string $dashboardClass, ?Authenticatable $user): bool
    {
        return $dashboardClass::visibleFor($user)
            && $this->passesRoleConstraints($dashboardClass, $user)
            && $this->passesPermissionConstraints($dashboardClass, $user);
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     */
    private function passesRoleConstraints(string $dashboardClass, ?Authenticatable $user): bool
    {
        $roles = array_values(array_filter(
            $dashboardClass::roles(),
            static fn (mixed $role): bool => is_string($role) && trim($role) !== ''
        ));

        if ($roles === []) {
            return true;
        }

        if (! $user instanceof Model) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ((bool) $user->hasRole($role)) {
                    return true;
                }
            }

            return false;
        }

        return Role::query()
            ->whereIn('slug', $roles)
            ->whereHas('assignments', function ($query) use ($user): void {
                $query
                    ->where('model_type', $user->getMorphClass())
                    ->where('model_id', $user->getKey());
            })
            ->exists();
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     */
    private function passesPermissionConstraints(string $dashboardClass, ?Authenticatable $user): bool
    {
        $permissions = array_values(array_filter(
            $dashboardClass::permissions(),
            static fn (mixed $permission): bool => is_string($permission) && trim($permission) !== ''
        ));

        if ($permissions === []) {
            return true;
        }

        $checker = app(PermissionChecker::class);

        foreach ($permissions as $permission) {
            [$resource, $ability] = $this->normalizePermissionExpression($permission);
            if ($resource === null || $ability === null) {
                continue;
            }

            if ($checker->allows($user, $resource, $ability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0:string|null,1:string|null}
     */
    private function normalizePermissionExpression(string $permission): array
    {
        $expression = trim($permission);
        if ($expression === '') {
            return [null, null];
        }

        if (str_contains($expression, ':')) {
            [$resource, $ability] = array_pad(explode(':', $expression, 2), 2, null);

            if (! is_string($resource) || trim($resource) === '' || ! is_string($ability) || trim($ability) === '') {
                return [null, null];
            }

            $ability = trim($ability);

            return PermissionAbilities::isSupported($ability)
                ? [trim($resource), $ability]
                : [null, null];
        }

        if (preg_match('/^(.+)\.([A-Za-z][A-Za-z0-9_.-]*)$/', $expression, $matches) === 1) {
            $resource = trim((string) $matches[1]);
            $ability = trim((string) $matches[2]);

            if ($resource !== '' && PermissionAbilities::isSupported($ability)) {
                return [$resource, $ability];
            }
        }

        return [$expression, PermissionAbilities::VIEW_ANY];
    }
}
