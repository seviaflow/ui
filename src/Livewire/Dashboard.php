<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Livewire;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Component;
use SeviaFlow\Core\Auth\Permissions\PermissionChecker;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceAuthorization;
use SeviaFlow\Ui\Dashboard\Dashboard as DashboardDefinition;
use SeviaFlow\Ui\Dashboard\DashboardRegistry;
use SeviaFlow\Ui\Dashboard\DashboardSummaryCache;
use SeviaFlow\Ui\Dashboard\Layouts\DashboardLayoutStore;
use SeviaFlow\Ui\Dashboard\Layouts\SavedDashboardLayoutStore;
use SeviaFlow\Ui\Dashboard\RecentActivityService;
use SeviaFlow\Ui\Dashboard\Widgets\DashboardWidgetResolver;
use SeviaFlow\Ui\Dashboard\WorkflowOverviewService;

final class Dashboard extends Component
{
    public ?string $dashboard = null;

    public bool $layoutEditing = false;

    public string $layoutPayload = '';

    public string $savedLayoutName = '';

    public int|string|null $selectedSavedLayoutId = null;

    /**
     * @var class-string<DashboardDefinition>|null
     */
    private ?string $resolvedDashboardClass = null;

    public function mount(?string $dashboard = null): void
    {
        $this->dashboard = $dashboard;
    }

    public function render(): View
    {
        $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');
        $showResourceCounts = (bool) config('seviaflow-ui.dashboard.show_resource_counts', false);
        $showWorkflowAttentionCounts = (bool) config('seviaflow-ui.dashboard.show_workflow_attention_counts', false);
        $recentActivityLimit = (int) config('seviaflow-ui.dashboard.recent_activity_limit', 10);

        /** @var array<string, class-string<\SeviaFlow\Core\Resources\Resource>> $configuredResources */
        $configuredResources = (array) config('seviaflow.resources', []);
        $user = auth()->user();
        $actor = $user instanceof Authenticatable ? $user : null;
        $registry = app(DashboardRegistry::class);
        $visibleDashboards = $registry->visibleFor($actor);
        $dashboardOptions = array_map(
            static fn (string $dashboardClass): array => [
                'slug' => $dashboardClass::slug(),
                'label' => $dashboardClass::label(),
            ],
            array_values($visibleDashboards)
        );
        $dashboardClass = $this->resolveDashboardClass($actor);
        if ($dashboardClass === null) {
            return view('seviaflow-ui::livewire.dashboard', [
                'resourceItems' => [],
                'resourceCount' => 0,
                'showResourceCounts' => false,
                'workflowItems' => [],
                'showWorkflowAttentionCounts' => false,
                'recentActivities' => [],
                'quickActions' => [],
                'userName' => is_object($user) && isset($user->name) ? (string) $user->name : null,
                'userEmail' => is_object($user) && isset($user->email) ? (string) $user->email : null,
                'dashboardSlug' => null,
                'dashboardLabel' => 'Dashboard',
                'dashboardDescription' => 'No dashboards are currently visible for your account.',
                'widgets' => [],
                'hasWidgets' => false,
                'layoutEditing' => false,
                'layoutPayload' => '',
                'canCustomizeLayout' => false,
                'savedLayouts' => [],
                'selectedSavedLayoutId' => null,
                'savedLayoutName' => '',
                'dashboardOptions' => $dashboardOptions,
                'showDashboardSwitcher' => count($dashboardOptions) > 1,
                'noVisibleDashboards' => true,
            ])->layout('seviaflow-ui::components.app.layout', [
                'pageTitle' => 'Dashboard',
                'pageDescription' => 'No dashboards are currently visible for your account.',
                'contentWidth' => 'wide',
                'breadcrumbs' => [
                    ['label' => 'Admin', 'url' => route($routeNamePrefix.'dashboard')],
                    ['label' => 'Dashboard', 'url' => null],
                ],
            ]);
        }
        $dashboardSlug = $dashboardClass::slug();
        $dashboardLabel = $dashboardClass::label();
        $dashboardDescription = $dashboardClass::description();
        $widgets = app(DashboardWidgetResolver::class)->resolve($dashboardClass, $actor);
        $resolvedWidgets = array_values(array_map(
            fn ($widget): array => [
                'name' => $widget->name(),
                'label' => $widget->getLabel(),
                'description' => $widget->getDescription(),
                'columnSpan' => $widget->getColumnSpan(),
                'columnSpanClass' => $this->columnSpanClass($widget->getColumnSpan()),
                'cacheTtl' => $widget->getCacheTtl(),
                'view' => $widget->view(),
                'viewData' => $widget->viewData($actor, $dashboardSlug),
            ],
            $widgets
        ));
        $layout = $this->dashboardLayoutStore()->getLayout($dashboardClass, $actor);
        $resolvedWidgets = $this->applyLayoutToWidgets($resolvedWidgets, $layout);
        $savedLayouts = $actor instanceof Model
            ? $this->savedDashboardLayoutStore()->listForUser($dashboardClass, $actor)->map(
                static fn ($saved): array => [
                    'id' => (int) $saved->getKey(),
                    'name' => (string) $saved->name,
                    'is_default' => (bool) $saved->is_default,
                ]
            )->values()->all()
            : [];

        $dashboardSummaryCache = app(DashboardSummaryCache::class);
        $resourceItems = $dashboardSummaryCache->rememberForUser(
            section: 'resource_overview',
            user: $actor,
            context: [
                'dashboard' => $dashboardSlug,
                'route_name_prefix' => $routeNamePrefix,
                'show_resource_counts' => $showResourceCounts,
                'resources_hash' => md5(json_encode($configuredResources)),
            ],
            resolver: fn (): array => $this->resolveResourceItems($configuredResources, $actor, $routeNamePrefix, $showResourceCounts),
        );

        $workflowItems = $dashboardSummaryCache->rememberForUser(
            section: 'workflow_overview',
            user: $actor,
            context: [
                'dashboard' => $dashboardSlug,
                'route_name_prefix' => $routeNamePrefix,
                'show_workflow_attention_counts' => $showWorkflowAttentionCounts,
                'resources_hash' => md5(json_encode($configuredResources)),
            ],
            resolver: fn (): array => app(WorkflowOverviewService::class)->items(
                configuredResources: $configuredResources,
                user: $actor,
                routeNamePrefix: $routeNamePrefix,
                showAttentionCounts: $showWorkflowAttentionCounts,
            ),
        );
        $recentActivities = app(RecentActivityService::class)->items(
            configuredResources: $configuredResources,
            user: $actor,
            limit: $recentActivityLimit,
        );
        $quickActions = $this->quickActions($configuredResources, $actor, $routeNamePrefix);

        return view('seviaflow-ui::livewire.dashboard', [
            'resourceItems' => $resourceItems,
            'resourceCount' => count($resourceItems),
            'showResourceCounts' => $showResourceCounts,
            'workflowItems' => $workflowItems,
            'showWorkflowAttentionCounts' => $showWorkflowAttentionCounts,
            'recentActivities' => $recentActivities,
            'quickActions' => $quickActions,
            'userName' => is_object($user) && isset($user->name) ? (string) $user->name : null,
            'userEmail' => is_object($user) && isset($user->email) ? (string) $user->email : null,
            'dashboardSlug' => $dashboardSlug,
            'dashboardLabel' => $dashboardLabel,
            'dashboardDescription' => $dashboardDescription,
            'widgets' => $resolvedWidgets,
            'hasWidgets' => $resolvedWidgets !== [],
            'layoutEditing' => $this->layoutEditing,
            'layoutPayload' => $this->layoutPayload,
            'canCustomizeLayout' => $actor instanceof Model && $resolvedWidgets !== [],
            'savedLayouts' => $savedLayouts,
            'selectedSavedLayoutId' => $this->selectedSavedLayoutId,
            'savedLayoutName' => $this->savedLayoutName,
            'dashboardOptions' => $dashboardOptions,
            'showDashboardSwitcher' => count($dashboardOptions) > 1,
            'noVisibleDashboards' => false,
        ])->layout('seviaflow-ui::components.app.layout', [
            'pageTitle' => $dashboardLabel,
            'pageDescription' => $dashboardDescription ?? 'Welcome to your operator workspace.',
            'contentWidth' => 'wide',
            'breadcrumbs' => [
                ['label' => 'Admin', 'url' => route($routeNamePrefix.'dashboard')],
                ['label' => $dashboardLabel, 'url' => null],
            ],
        ]);
    }

    public function startLayoutEditing(): void
    {
        $actor = auth()->user();
        if (! $actor instanceof Model) {
            return;
        }

        $dashboardClass = $this->resolveDashboardClass($actor);
        if ($dashboardClass === null) {
            return;
        }
        $layout = $this->dashboardLayoutStore()->getLayout($dashboardClass, $actor);
        $this->layoutPayload = (string) json_encode($layout);
        $this->layoutEditing = true;
    }

    public function cancelLayoutEditing(): void
    {
        $this->layoutEditing = false;
        $this->layoutPayload = '';
    }

    public function saveLayout(): void
    {
        $actor = auth()->user();
        if (! $actor instanceof Model) {
            return;
        }

        $dashboardClass = $this->resolveDashboardClass($actor);
        if ($dashboardClass === null) {
            return;
        }
        $layout = $this->decodeLayoutPayload();
        $normalized = $this->dashboardLayoutStore()->saveLayout($dashboardClass, $actor, $layout);

        $this->layoutPayload = (string) json_encode($normalized);
        $this->layoutEditing = false;
    }

    public function resetLayout(): void
    {
        $actor = auth()->user();
        if (! $actor instanceof Model) {
            return;
        }

        $dashboardClass = $this->resolveDashboardClass($actor);
        if ($dashboardClass === null) {
            return;
        }
        $layout = $this->dashboardLayoutStore()->resetLayout($dashboardClass, $actor);
        $this->layoutPayload = (string) json_encode($layout);
        $this->layoutEditing = false;
        $this->selectedSavedLayoutId = null;
    }

    public function saveCurrentAsLayout(bool $asDefault = false): void
    {
        $actor = auth()->user();
        if (! $actor instanceof Model) {
            return;
        }

        $dashboardClass = $this->resolveDashboardClass($actor);
        if ($dashboardClass === null) {
            return;
        }
        $layout = $this->decodeLayoutPayload();
        if ($layout === []) {
            $layout = $this->dashboardLayoutStore()->getLayout($dashboardClass, $actor);
        }

        $saved = $this->savedDashboardLayoutStore()->saveAs(
            dashboardClass: $dashboardClass,
            user: $actor,
            name: $this->savedLayoutName,
            layout: $layout,
            isDefault: $asDefault,
        );

        $this->selectedSavedLayoutId = (int) $saved->getKey();
        $this->savedLayoutName = '';
    }

    public function applySavedLayout(): void
    {
        $actor = auth()->user();
        $savedDashboardId = $this->selectedSavedLayoutId();
        if (! $actor instanceof Model || $savedDashboardId === null) {
            return;
        }

        $dashboardClass = $this->resolveDashboardClass($actor);
        if ($dashboardClass === null) {
            return;
        }
        $layout = $this->savedDashboardLayoutStore()->applySavedLayout(
            dashboardClass: $dashboardClass,
            user: $actor,
            savedDashboardId: $savedDashboardId,
        );

        $this->layoutPayload = (string) json_encode($layout);
        $this->layoutEditing = false;
    }

    public function deleteSavedLayout(): void
    {
        $actor = auth()->user();
        $savedDashboardId = $this->selectedSavedLayoutId();
        if (! $actor instanceof Model || $savedDashboardId === null) {
            return;
        }

        $dashboardClass = $this->resolveDashboardClass($actor);
        if ($dashboardClass === null) {
            return;
        }
        $this->savedDashboardLayoutStore()->deleteSavedLayout(
            dashboardClass: $dashboardClass,
            user: $actor,
            savedDashboardId: $savedDashboardId,
        );

        $this->selectedSavedLayoutId = null;
    }

    public function setDefaultSavedLayout(): void
    {
        $actor = auth()->user();
        $savedDashboardId = $this->selectedSavedLayoutId();
        if (! $actor instanceof Model || $savedDashboardId === null) {
            return;
        }

        $dashboardClass = $this->resolveDashboardClass($actor);
        if ($dashboardClass === null) {
            return;
        }
        $this->savedDashboardLayoutStore()->setDefaultSavedLayout(
            dashboardClass: $dashboardClass,
            user: $actor,
            savedDashboardId: $savedDashboardId,
        );
    }

    public function renameSavedLayout(): void
    {
        $actor = auth()->user();
        $savedDashboardId = $this->selectedSavedLayoutId();
        if (! $actor instanceof Model || $savedDashboardId === null || trim($this->savedLayoutName) === '') {
            return;
        }

        $dashboardClass = $this->resolveDashboardClass($actor);
        if ($dashboardClass === null) {
            return;
        }
        $this->savedDashboardLayoutStore()->renameSavedLayout(
            dashboardClass: $dashboardClass,
            user: $actor,
            savedDashboardId: $savedDashboardId,
            name: $this->savedLayoutName,
        );

        $this->savedLayoutName = '';
    }

    private function columnSpanClass(int|string $span): string
    {
        if (is_int($span)) {
            return match (true) {
                $span <= 1 => 'lg:col-span-1',
                $span === 2 => 'lg:col-span-2',
                default => 'lg:col-span-3',
            };
        }

        return $span;
    }

    /**
     * @return class-string<DashboardDefinition>|null
     */
    private function resolveDashboardClass(?Authenticatable $actor): ?string
    {
        if ($this->resolvedDashboardClass !== null) {
            return $this->resolvedDashboardClass;
        }

        $registry = app(DashboardRegistry::class);
        $resolved = $this->dashboard !== null && trim($this->dashboard) !== ''
            ? $registry->resolve($this->dashboard, $actor)
            : $registry->resolveDefault($actor);

        if ($resolved === null) {
            return null;
        }

        $this->resolvedDashboardClass = $resolved;

        return $resolved;
    }

    /**
     * @param  array<string, class-string<\SeviaFlow\Core\Resources\Resource>>  $configuredResources
     * @return array<int, array{slug:string,label:string,url:string,count:int|null}>
     */
    private function resolveResourceItems(array $configuredResources, ?Authenticatable $actor, string $routeNamePrefix, bool $showResourceCounts): array
    {
        $resourceItems = [];
        if (! $actor instanceof Authenticatable) {
            return [];
        }

        foreach ($configuredResources as $slug => $resourceClass) {
            if (! is_string($slug) || $slug === '' || ! is_string($resourceClass) || ! is_a($resourceClass, Resource::class, true)) {
                continue;
            }

            if (! ResourceAuthorization::for($resourceClass)->canViewAny($actor)) {
                continue;
            }

            $resourceItems[] = [
                'slug' => $slug,
                'label' => $this->resolveResourceLabel($slug, $resourceClass),
                'url' => route($routeNamePrefix.'resources.index', ['resource' => $slug]),
                'count' => $showResourceCounts ? $this->resolveResourceCount($resourceClass) : null,
            ];
        }

        usort($resourceItems, static fn (array $left, array $right): int => strcmp($left['label'], $right['label']));

        return $resourceItems;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function resolveResourceLabel(string $slug, string $resourceClass): string
    {
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
    private function resolveResourceCount(string $resourceClass): int
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $resourceClass::model();

        $query = $resourceClass::query($modelClass::query());

        return (int) $query->count();
    }

    /**
     * @param  array<string, class-string<resource>>  $configuredResources
     * @return array<int, array{label: string, url: string}>
     */
    private function quickActions(array $configuredResources, ?Authenticatable $actor, string $routeNamePrefix): array
    {
        if (! $actor instanceof Authenticatable) {
            return [];
        }

        $actions = [];

        foreach ($configuredResources as $slug => $resourceClass) {
            if (! is_string($slug) || $slug === '' || ! is_string($resourceClass) || ! is_a($resourceClass, Resource::class, true)) {
                continue;
            }

            if (! ResourceAuthorization::for($resourceClass)->canCreate($actor)) {
                continue;
            }

            $actions[] = [
                'label' => 'Create '.Str::of($this->resolveResourceLabel($slug, $resourceClass))->lower()->toString(),
                'url' => route($routeNamePrefix.'resources.create', ['resource' => $slug]),
            ];
        }

        if (Route::has($routeNamePrefix.'settings.roles.index') && $this->canManageRoles($actor)) {
            $actions[] = [
                'label' => 'Manage roles',
                'url' => route($routeNamePrefix.'settings.roles.index'),
            ];
        }

        if (Route::has($routeNamePrefix.'profile.index')) {
            $actions[] = [
                'label' => 'View profile',
                'url' => route($routeNamePrefix.'profile.index'),
            ];
        }

        if (Route::has($routeNamePrefix.'profile.password')) {
            $actions[] = [
                'label' => 'Account security',
                'url' => route($routeNamePrefix.'profile.password'),
            ];
        }

        usort($actions, static fn (array $left, array $right): int => strcmp($left['label'], $right['label']));

        return $actions;
    }

    private function canManageRoles(?Authenticatable $actor): bool
    {
        if (! (bool) config('seviaflow.permissions.enabled', false)) {
            return true;
        }

        if (! $actor instanceof Model) {
            return false;
        }

        return app(PermissionChecker::class)->allows(
            $actor,
            (string) config('seviaflow.permissions.role_management.resource', '__settings.roles'),
            (string) config('seviaflow.permissions.role_management.ability', 'update')
        );
    }

    private function dashboardLayoutStore(): DashboardLayoutStore
    {
        return app(DashboardLayoutStore::class);
    }

    private function savedDashboardLayoutStore(): SavedDashboardLayoutStore
    {
        return app(SavedDashboardLayoutStore::class);
    }

    /**
     * @param  array<int, array{
     *   name:string,
     *   label:string,
     *   description:?string,
     *   columnSpan:int|string,
     *   columnSpanClass:string,
     *   cacheTtl:?int,
     *   view:string,
     *   viewData:array<string,mixed>
     * }>  $widgets
     * @param  array<int, array{widget:string,x:int,y:int,w:int,h:int,visible:bool}>  $layout
     * @return array<int, array{
     *   name:string,
     *   label:string,
     *   description:?string,
     *   columnSpan:int|string,
     *   columnSpanClass:string,
     *   cacheTtl:?int,
     *   view:string,
     *   viewData:array<string,mixed>
     * }>
     */
    private function applyLayoutToWidgets(array $widgets, array $layout): array
    {
        $byName = [];
        foreach ($widgets as $widget) {
            $byName[$widget['name']] = $widget;
        }

        $ordered = [];
        foreach ($layout as $entry) {
            $widgetName = $entry['widget'];
            if (! is_string($widgetName) || ! isset($byName[$widgetName])) {
                continue;
            }

            if ($entry['visible'] === false) {
                continue;
            }

            $widget = $byName[$widgetName];
            $width = $entry['w'];
            if (is_int($width) && $width >= 1) {
                $widget['columnSpan'] = min($width, 3);
                $widget['columnSpanClass'] = $this->columnSpanClass($widget['columnSpan']);
            }

            $ordered[] = $widget;
            unset($byName[$widgetName]);
        }

        foreach ($widgets as $widget) {
            if (isset($byName[$widget['name']])) {
                $ordered[] = $widget;
                unset($byName[$widget['name']]);
            }
        }

        return $ordered;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function decodeLayoutPayload(): array
    {
        if (trim($this->layoutPayload) === '') {
            return [];
        }

        $decoded = json_decode($this->layoutPayload, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function selectedSavedLayoutId(): ?int
    {
        if (is_int($this->selectedSavedLayoutId)) {
            return $this->selectedSavedLayoutId > 0 ? $this->selectedSavedLayoutId : null;
        }

        if (is_string($this->selectedSavedLayoutId) && ctype_digit($this->selectedSavedLayoutId)) {
            $id = (int) $this->selectedSavedLayoutId;

            return $id > 0 ? $id : null;
        }

        return null;
    }
}
