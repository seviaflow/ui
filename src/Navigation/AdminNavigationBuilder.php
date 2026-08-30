<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Navigation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use SeviaFlow\Core\Auth\Permissions\PermissionChecker;
use SeviaFlow\Core\Resources\ResourceAuthorization;
use SeviaFlow\Ui\Dashboard\DashboardRegistry;

final class AdminNavigationBuilder
{
    public function __construct(
        private readonly NavigationMetadataCache $navigationMetadataCache,
        private readonly PermissionChecker $permissionChecker,
        private readonly DashboardRegistry $dashboardRegistry,
    ) {}

    /**
     * @return array<int, NavigationGroup>
     */
    public function build(
        ?Authenticatable $actor = null,
        ?string $currentRouteName = null,
        ?string $currentResourceSlug = null,
        ?string $currentDashboardSlug = null,
    ): array {
        $actor ??= Auth::user();
        $currentRouteName ??= (string) optional(request()->route())->getName();
        $currentResourceSlug ??= is_string(request()->route('resource')) ? request()->route('resource') : null;
        $currentDashboardSlug ??= is_string(request()->route('dashboard')) ? request()->route('dashboard') : null;

        $groups = [];
        $metadata = $this->navigationMetadataCache->resolve();

        foreach ($metadata['groups'] as $groupMeta) {
            $items = [];

            foreach ($groupMeta['items'] as $itemMeta) {
                if (! $this->canIncludeItem($itemMeta, $actor)) {
                    continue;
                }

                $routeName = (string) ($itemMeta['route_name'] ?? '');
                if ($routeName === '' || ! Route::has($routeName)) {
                    continue;
                }

                $items[] = new NavigationItem(
                    key: (string) $itemMeta['key'],
                    label: (string) $itemMeta['label'],
                    url: route($routeName, is_array($itemMeta['route_params'] ?? null) ? $itemMeta['route_params'] : []),
                    active: $this->isActive($itemMeta, $currentRouteName, $currentResourceSlug, $currentDashboardSlug),
                    icon: isset($itemMeta['icon']) && is_string($itemMeta['icon']) ? $itemMeta['icon'] : null,
                    group: isset($itemMeta['group']) && is_string($itemMeta['group']) ? $itemMeta['group'] : null,
                    order: isset($itemMeta['order']) && is_int($itemMeta['order']) ? $itemMeta['order'] : null,
                    method: isset($itemMeta['method']) && is_string($itemMeta['method']) ? $itemMeta['method'] : 'GET',
                );
            }

            $groups[] = new NavigationGroup(
                key: (string) $groupMeta['key'],
                label: (string) $groupMeta['label'],
                items: $items
            );
        }

        return $groups;
    }

    /**
     * @param  array<string,mixed>  $itemMeta
     */
    private function canIncludeItem(array $itemMeta, ?Authenticatable $actor): bool
    {
        $type = (string) ($itemMeta['type'] ?? '');

        if ($type === 'resource') {
            $resourceClass = $itemMeta['resource_class'] ?? null;

            return is_string($resourceClass)
                && ResourceAuthorization::for($resourceClass)->canViewAny($actor);
        }

        if ($type === 'dashboard') {
            $dashboardSlug = isset($itemMeta['dashboard_slug']) && is_string($itemMeta['dashboard_slug'])
                ? $itemMeta['dashboard_slug']
                : null;

            return $dashboardSlug !== null
                && $this->dashboardRegistry->resolve($dashboardSlug, $actor) !== null;
        }

        if ($type === 'settings.roles') {
            return $this->canAccessSettingsRoles($actor);
        }

        if ($type === 'settings.admin-users') {
            return $this->canAccessSettingsAdminUsers($actor);
        }

        if ($type === 'developer.api-docs') {
            return (bool) config('seviaflow-ui.api_docs_navigation.show_in_navigation', true)
                && $this->canAccessApiDocs($actor);
        }

        if (str_starts_with($type, 'account.')) {
            return $actor instanceof Authenticatable;
        }

        if ($type === 'extension') {
            return $this->canAccessExtensionItem($itemMeta, $actor);
        }

        return true;
    }

    /**
     * @param  array<string,mixed>  $itemMeta
     */
    private function isActive(array $itemMeta, string $currentRouteName, ?string $currentResourceSlug, ?string $currentDashboardSlug): bool
    {
        $pattern = (string) ($itemMeta['active_pattern'] ?? 'none');

        if ($pattern === 'exact') {
            return $currentRouteName === (string) ($itemMeta['active_route_name'] ?? '');
        }

        if ($pattern === 'route_prefix') {
            $prefix = (string) ($itemMeta['active_route_prefix'] ?? '');

            return $prefix !== '' && str_starts_with($currentRouteName, $prefix);
        }

        if ($pattern === 'dashboard') {
            $slug = isset($itemMeta['dashboard_slug']) && is_string($itemMeta['dashboard_slug'])
                ? $itemMeta['dashboard_slug']
                : null;

            if ($slug === null) {
                return false;
            }

            $routeName = (string) ($itemMeta['active_route_name'] ?? '');
            if ($currentRouteName === $routeName) {
                return $slug === 'default' || $currentDashboardSlug === $slug;
            }

            $prefix = (string) ($itemMeta['active_route_prefix'] ?? '');

            return $prefix !== ''
                && str_starts_with($currentRouteName, $prefix)
                && $currentDashboardSlug !== null
                && $slug === $currentDashboardSlug;
        }

        if ($pattern === 'resource') {
            $prefix = (string) ($itemMeta['active_route_prefix'] ?? '');
            $slug = isset($itemMeta['resource_slug']) && is_string($itemMeta['resource_slug'])
                ? $itemMeta['resource_slug']
                : null;

            return $prefix !== ''
                && str_starts_with($currentRouteName, $prefix)
                && $currentResourceSlug !== null
                && $slug === $currentResourceSlug;
        }

        return false;
    }

    private function canAccessSettingsRoles(?Authenticatable $actor): bool
    {
        if (! (bool) config('seviaflow.permissions.enabled', false)) {
            return true;
        }

        if (! $actor instanceof Model) {
            return false;
        }

        return $this->permissionChecker->allows(
            $actor,
            (string) config('seviaflow.permissions.role_management.resource', '__settings.roles'),
            (string) config('seviaflow.permissions.role_management.ability', 'update')
        );
    }

    private function canAccessSettingsAdminUsers(?Authenticatable $actor): bool
    {
        if (! (bool) config('seviaflow.permissions.enabled', false)) {
            return true;
        }

        if (! $actor instanceof Model) {
            return false;
        }

        return $this->permissionChecker->allows(
            $actor,
            (string) config('seviaflow.permissions.role_assignments.resource', '__settings.admin-users'),
            (string) config('seviaflow.permissions.role_assignments.ability', 'update')
        );
    }

    private function canAccessApiDocs(?Authenticatable $actor): bool
    {
        if (! (bool) config('seviaflow.permissions.enabled', false)) {
            return $actor instanceof Authenticatable;
        }

        if (! $actor instanceof Model) {
            return false;
        }

        return $this->permissionChecker->allows(
            $actor,
            (string) config('seviaflow-ui.api_docs_navigation.permission.resource', '__settings.api-docs'),
            (string) config('seviaflow-ui.api_docs_navigation.permission.ability', 'apiDocs.view')
        );
    }

    /**
     * @param  array<string,mixed>  $itemMeta
     */
    private function canAccessExtensionItem(array $itemMeta, ?Authenticatable $actor): bool
    {
        if ((bool) ($itemMeta['requires_auth'] ?? true) && ! $actor instanceof Authenticatable) {
            return false;
        }

        if (! (bool) config('seviaflow.permissions.enabled', false)) {
            return true;
        }

        $permission = $itemMeta['permission'] ?? null;
        if (! is_array($permission)) {
            return true;
        }

        $resource = $permission['resource'] ?? null;
        $ability = $permission['ability'] ?? null;

        if (! is_string($resource) || ! is_string($ability)) {
            return false;
        }

        if (! $actor instanceof Model) {
            return false;
        }

        return $this->permissionChecker->allows($actor, $resource, $ability);
    }
}
