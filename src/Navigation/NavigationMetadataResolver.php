<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Navigation;

use Illuminate\Support\Str;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Ui\Dashboard\DashboardRegistry;

final class NavigationMetadataResolver
{
    /**
     * @param  iterable<int, NavigationContributor>  $contributors
     */
    public function __construct(
        private readonly ResourceRegistry $resourceRegistry,
        private readonly DashboardRegistry $dashboardRegistry,
        private readonly iterable $contributors = [],
    ) {}

    /**
     * @return array{
     *   groups: array<int, array{
     *     key:string,
     *     label:string,
     *     items: array<int, array<string,mixed>>
     *   }>
     * }
     */
    public function resolve(): array
    {
        $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');

        $resourceItems = [];
        foreach ($this->resourceRegistry->all() as $slug => $resourceClass) {
            if (! $resourceClass::shouldRegisterNavigation()) {
                continue;
            }

            $resourceItems[] = [
                'type' => 'resource',
                'resource_slug' => $slug,
                'resource_class' => $resourceClass,
                'key' => 'resource:'.$slug,
                'label' => $this->resourceLabel($slug, $resourceClass),
                'icon' => $this->resourceIcon($resourceClass),
                'group' => $this->resourceGroup($resourceClass),
                'order' => $this->resourceOrder($resourceClass),
                'method' => 'GET',
                'route_name' => $routeNamePrefix.'resources.index',
                'route_params' => ['resource' => $slug],
                'active_pattern' => 'resource',
                'active_route_prefix' => $routeNamePrefix.'resources.',
            ];
        }

        usort($resourceItems, static function (array $left, array $right): int {
            $leftOrder = is_int($left['order'] ?? null) ? $left['order'] : PHP_INT_MAX;
            $rightOrder = is_int($right['order'] ?? null) ? $right['order'] : PHP_INT_MAX;

            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcmp((string) $left['label'], (string) $right['label']);
        });

        $dashboardItems = [];
        foreach ($this->dashboardRegistry->all() as $dashboardSlug => $dashboardClass) {
            $routeName = $dashboardSlug === 'default'
                ? $routeNamePrefix.'dashboard'
                : $routeNamePrefix.'dashboards.show';

            $routeParams = $dashboardSlug === 'default'
                ? []
                : ['dashboard' => $dashboardSlug];

            $dashboardItems[] = [
                'type' => 'dashboard',
                'dashboard_slug' => $dashboardSlug,
                'dashboard_class' => $dashboardClass,
                'key' => 'dashboard:'.$dashboardSlug,
                'label' => $dashboardClass::label(),
                'icon' => 'squares-2x2',
                'method' => 'GET',
                'route_name' => $routeName,
                'route_params' => $routeParams,
                'active_pattern' => 'dashboard',
                'active_route_name' => $routeName,
                'active_route_prefix' => $routeNamePrefix.'dashboards.',
            ];
        }

        return [
            'groups' => [
                [
                    'key' => 'main',
                    'label' => __('seviaflow-ui::panel.main'),
                    'items' => $this->mergeContributorItems('main', $dashboardItems),
                ],
                [
                    'key' => 'resources',
                    'label' => __('seviaflow-ui::panel.resources'),
                    'items' => $this->mergeContributorItems('resources', $resourceItems),
                ],
                [
                    'key' => 'settings',
                    'label' => __('seviaflow-ui::panel.settings'),
                    'items' => $this->mergeContributorItems('settings', [
                        [
                            'type' => 'settings.roles',
                            'key' => 'settings.roles',
                            'label' => __('seviaflow-ui::panel.roles'),
                            'icon' => 'shield-check',
                            'method' => 'GET',
                            'route_name' => $routeNamePrefix.'settings.roles.index',
                            'route_params' => [],
                            'active_pattern' => 'route_prefix',
                            'active_route_prefix' => $routeNamePrefix.'settings.roles.',
                        ],
                        [
                            'type' => 'settings.admin-users',
                            'key' => 'settings.admin-users',
                            'label' => __('seviaflow-ui::panel.admin_users'),
                            'icon' => 'users',
                            'method' => 'GET',
                            'route_name' => $routeNamePrefix.'settings.admin-users.index',
                            'route_params' => [],
                            'active_pattern' => 'route_prefix',
                            'active_route_prefix' => $routeNamePrefix.'settings.admin-users.',
                        ],
                    ]),
                ],
                [
                    'key' => 'developer',
                    'label' => __('seviaflow-ui::panel.developer'),
                    'items' => $this->mergeContributorItems('developer', [
                        [
                            'type' => 'developer.api-docs',
                            'key' => 'developer.api-docs',
                            'label' => __('seviaflow-ui::panel.api_documentation'),
                            'icon' => 'code-bracket',
                            'method' => 'GET',
                            'route_name' => (string) config('seviaflow-ui.api_docs_navigation.route_name', 'seviaflow.api.docs'),
                            'route_params' => [],
                            'active_pattern' => 'exact',
                            'active_route_name' => (string) config('seviaflow-ui.api_docs_navigation.route_name', 'seviaflow.api.docs'),
                        ],
                    ]),
                ],
                [
                    'key' => 'account',
                    'label' => __('seviaflow-ui::panel.account'),
                    'items' => $this->mergeContributorItems('account', [
                        [
                            'type' => 'account.profile',
                            'key' => 'account.profile',
                            'label' => __('seviaflow-ui::panel.profile'),
                            'icon' => 'user-circle',
                            'method' => 'GET',
                            'route_name' => $routeNamePrefix.'profile.index',
                            'route_params' => [],
                            'active_pattern' => 'route_prefix',
                            'active_route_prefix' => $routeNamePrefix.'profile.',
                        ],
                        [
                            'type' => 'account.security',
                            'key' => 'account.security',
                            'label' => __('seviaflow-ui::panel.account_security'),
                            'icon' => 'lock-closed',
                            'method' => 'GET',
                            'route_name' => $routeNamePrefix.'profile.password',
                            'route_params' => [],
                            'active_pattern' => 'route_prefix',
                            'active_route_prefix' => $routeNamePrefix.'profile.password',
                        ],
                        [
                            'type' => 'account.logout',
                            'key' => 'account.logout',
                            'label' => __('seviaflow-ui::panel.sign_out'),
                            'icon' => 'arrow-right-on-rectangle',
                            'method' => 'POST',
                            'route_name' => $routeNamePrefix.'logout',
                            'route_params' => [],
                            'active_pattern' => 'none',
                        ],
                    ]),
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $items
     * @return array<int, array<string,mixed>>
     */
    private function mergeContributorItems(string $groupKey, array $items): array
    {
        foreach ($this->contributors as $contributor) {
            foreach ($contributor->groups() as $group) {
                if ($group['key'] !== $groupKey) {
                    continue;
                }

                $items = array_merge($items, $group['items']);
            }
        }

        return $items;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function resourceLabel(string $slug, string $resourceClass): string
    {
        $navigationLabel = $resourceClass::navigationLabel();
        if (is_string($navigationLabel) && trim($navigationLabel) !== '') {
            return $navigationLabel;
        }

        if (method_exists($resourceClass, 'pluralLabel')) {
            $label = $resourceClass::pluralLabel();
            if (is_string($label) && trim($label) !== '') {
                return $label;
            }
        }

        if (method_exists($resourceClass, 'label')) {
            $label = $resourceClass::label();
            if (is_string($label) && trim($label) !== '') {
                return $label;
            }
        }

        return Str::of($slug)->replace('-', ' ')->headline()->toString();
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function resourceGroup(string $resourceClass): ?string
    {
        $group = $resourceClass::navigationGroup();
        if (! is_string($group)) {
            return null;
        }

        $group = trim($group);

        return $group !== '' ? $group : null;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function resourceIcon(string $resourceClass): ?string
    {
        $icon = $resourceClass::navigationIcon();
        if (! is_string($icon)) {
            return null;
        }

        $icon = trim($icon);

        return $icon !== '' ? $icon : null;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function resourceOrder(string $resourceClass): ?int
    {
        $order = $resourceClass::navigationSort();

        return is_int($order) ? $order : null;
    }
}
