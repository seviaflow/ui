<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SeviaFlow' }}</title>
    @include('seviaflow-ui::partials.assets-head')
</head>
<body class="sf-app-body">
@php
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
    use SeviaFlow\Ui\Navigation\AdminNavigationBuilder;

    $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');
    $currentRouteName = (string) optional(request()->route())->getName();
    $currentResourceSlug = request()->route('resource');
    $currentResourceSlug = is_string($currentResourceSlug) ? $currentResourceSlug : null;
    $currentDashboardSlug = request()->route('dashboard');
    $currentDashboardSlug = is_string($currentDashboardSlug) ? $currentDashboardSlug : null;
    $navigationGroups = app(AdminNavigationBuilder::class)->build(
        actor: auth()->user(),
        currentRouteName: $currentRouteName,
        currentResourceSlug: $currentResourceSlug,
        currentDashboardSlug: $currentDashboardSlug
    );
    $navigationByKey = [];
    foreach ($navigationGroups as $group) {
        $navigationByKey[$group->key] = $group;
    }

    $workspaceNav = ($navigationByKey['main'] ?? null)?->items ?? [];
    $resourcesNav = ($navigationByKey['resources'] ?? null)?->items ?? [];
    $settingsNav = ($navigationByKey['settings'] ?? null)?->items ?? [];
    $developerNav = ($navigationByKey['developer'] ?? null)?->items ?? [];
    $accountNav = ($navigationByKey['account'] ?? null)?->items ?? [];
    $profileItem = collect($accountNav)->first(fn ($item): bool => $item->key === 'account.profile');
    $securityItem = collect($accountNav)->first(fn ($item): bool => $item->key === 'account.security');
    $logoutItem = collect($accountNav)->first(fn ($item): bool => $item->key === 'account.logout');

    $hasResourceGroups = collect($resourcesNav)->contains(fn ($item): bool => is_string($item->group) && trim($item->group) !== '');
    $resourceSections = [];
    if ($hasResourceGroups) {
        foreach ($resourcesNav as $item) {
            $groupLabel = is_string($item->group) && trim($item->group) !== ''
                ? trim($item->group)
                : __('seviaflow-ui::ui.navigation.other');
            $resourceSections[$groupLabel] ??= [];
            $resourceSections[$groupLabel][] = $item;
        }
    } else {
        $resourceSections[''] = $resourcesNav;
    }

    $hasSidebarNavigation = count($workspaceNav) > 0
        || count($resourcesNav) > 0
        || count($settingsNav) > 0
        || count($developerNav) > 0;

    $dashboardRouteName = Route::has($routeNamePrefix.'dashboard')
        ? $routeNamePrefix.'dashboard'
        : (Route::has($routeNamePrefix.'home') ? $routeNamePrefix.'home' : null);
    $brandHref = $dashboardRouteName !== null ? route($dashboardRouteName) : '#';

    $defaultTitle = 'Admin';
    $defaultEyebrow = __('seviaflow-ui::ui.navigation.workspace');
    $defaultDescription = 'Operator workspace';
    $defaultBreadcrumbs = [['label' => 'Admin', 'url' => $brandHref]];

    if ($currentResourceSlug !== null) {
        $resourceLabel = Str::of($currentResourceSlug)->replace('-', ' ')->headline()->toString();
        $defaultTitle = $resourceLabel;
        $defaultEyebrow = __('seviaflow-ui::ui.navigation.data');
        $defaultDescription = 'Manage '.$resourceLabel.' records.';
        $defaultBreadcrumbs[] = ['label' => 'Data', 'url' => null];
        $defaultBreadcrumbs[] = ['label' => $resourceLabel, 'url' => null];
    } elseif (str_starts_with($currentRouteName, $routeNamePrefix.'settings.roles.')) {
        $defaultTitle = 'Roles';
        $defaultEyebrow = __('seviaflow-ui::ui.navigation.settings');
        $defaultDescription = 'Manage admin roles and permissions.';
        $defaultBreadcrumbs[] = ['label' => 'Settings', 'url' => null];
        $defaultBreadcrumbs[] = ['label' => 'Roles', 'url' => null];
    } elseif (str_starts_with($currentRouteName, $routeNamePrefix.'settings.admin-users.')) {
        $defaultTitle = 'Admin users';
        $defaultEyebrow = __('seviaflow-ui::ui.navigation.settings');
        $defaultDescription = 'Manage role assignments for admin users.';
        $defaultBreadcrumbs[] = ['label' => 'Settings', 'url' => null];
        $defaultBreadcrumbs[] = ['label' => 'Admin users', 'url' => null];
    } elseif (str_starts_with($currentRouteName, $routeNamePrefix.'profile.')) {
        $defaultTitle = 'Account settings';
        $defaultEyebrow = __('seviaflow-ui::ui.navigation.account');
        $defaultDescription = 'Manage your admin account security and profile details.';
        $defaultBreadcrumbs[] = ['label' => 'Profile', 'url' => null];
    }

    $pageTitle = trim(isset($pageTitle) ? (string) $pageTitle : $defaultTitle);
    $pageEyebrow = trim(isset($pageEyebrow) ? (string) $pageEyebrow : $defaultEyebrow);
    $pageDescription = trim(isset($pageDescription) ? (string) $pageDescription : $defaultDescription);
    $breadcrumbItems = isset($breadcrumbs) && is_array($breadcrumbs) ? $breadcrumbs : $defaultBreadcrumbs;
    $hasCustomBreadcrumbsSlot = isset($breadcrumbs) && ! is_array($breadcrumbs);
    $requestedContentWidth = isset($contentWidth) ? (string) $contentWidth : 'wide';
    $contentWidthMode = in_array($requestedContentWidth, ['wide', 'standard', 'narrow'], true)
        ? $requestedContentWidth
        : 'wide';
    $authUser = auth()->user();
    $authUserName = is_object($authUser) && isset($authUser->name) ? (string) $authUser->name : null;
    $authUserEmail = is_object($authUser) && isset($authUser->email) ? (string) $authUser->email : null;
    $authUserLabel = $authUserName ?: ($authUserEmail ?: 'Admin user');
    $authInitials = Str::of($authUserLabel)->replaceMatches('/[^A-Za-z0-9 ]/', '')->explode(' ')
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
        ->implode('');
    $authInitials = $authInitials !== '' ? $authInitials : 'S';

    $commandPaletteItems = [];
    foreach ([
        'Workspace' => $workspaceNav,
        'Data' => $resourcesNav,
        'Settings' => $settingsNav,
        'Developer' => $developerNav,
        'Account' => $accountNav,
    ] as $areaLabel => $items) {
        foreach ($items as $item) {
            if ($item->method !== 'GET') {
                continue;
            }

            $description = $areaLabel;
            if ($areaLabel === 'Data' && is_string($item->group) && trim($item->group) !== '') {
                $description .= ' / '.trim($item->group);
            }

            $commandPaletteItems[] = [
                'label' => $item->label,
                'description' => $description,
                'url' => $item->url,
                'icon' => $item->icon,
                'active' => $item->active,
            ];
        }
    }
@endphp
<main class="sf-shell" data-sf-shell>
    <div class="sf-shell-grid">
        <aside class="sf-sidebar" id="sf-sidebar" aria-label="{{ __('seviaflow-ui::ui.navigation.primary') }}" data-sf-sidebar>
            <div class="sf-sidebar-brand">
                <x-seviaflow-ui::brand :href="$brandHref" surface="dark" />
                <button
                    type="button"
                    class="sf-sidebar-toggle"
                    aria-label="{{ __('seviaflow-ui::ui.navigation.collapse') }}"
                    aria-controls="sf-sidebar"
                    aria-expanded="true"
                    data-sf-label-expanded="{{ __('seviaflow-ui::ui.navigation.collapse') }}"
                    data-sf-label-collapsed="{{ __('seviaflow-ui::ui.navigation.expand') }}"
                    data-sf-sidebar-toggle
                >
                    <x-seviaflow-ui::icon name="bars-3" class="sf-sidebar-toggle-icon" />
                </button>
            </div>

            <div class="sf-sidebar-scroll">
                @if(count($workspaceNav) > 0)
                    <section class="sf-nav-section">
                        <div class="sf-nav-section-title">{{ __('seviaflow-ui::ui.navigation.workspace') }}</div>
                        <div class="sf-nav-list">
                            @foreach($workspaceNav as $item)
                                <x-seviaflow-ui::sidebar-link :item="$item" />
                            @endforeach
                        </div>
                    </section>
                @endif

                @if(count($resourcesNav) > 0)
                    <section class="sf-nav-section">
                        <div class="sf-nav-section-title">
                            <span>{{ __('seviaflow-ui::ui.navigation.data') }}</span>
                            <span class="sf-nav-section-count">{{ trans_choice('seviaflow-ui::ui.navigation.resource_count', count($resourcesNav), ['count' => count($resourcesNav)]) }}</span>
                        </div>
                        @foreach($resourceSections as $resourceGroupLabel => $resourceItems)
                            @if($hasResourceGroups)
                                <div class="sf-nav-subsection">{{ $resourceGroupLabel }}</div>
                            @endif
                            <div class="sf-nav-list">
                                @foreach($resourceItems as $item)
                                    <x-seviaflow-ui::sidebar-link :item="$item" />
                                @endforeach
                            </div>
                        @endforeach
                    </section>
                @endif

                @if(count($settingsNav) > 0)
                    <section class="sf-nav-section">
                        <div class="sf-nav-section-title">{{ __('seviaflow-ui::ui.navigation.settings') }}</div>
                        <div class="sf-nav-list">
                            @foreach($settingsNav as $item)
                                <x-seviaflow-ui::sidebar-link :item="$item" />
                            @endforeach
                        </div>
                    </section>
                @endif

                @if(count($developerNav) > 0)
                    <section class="sf-nav-section">
                        <div class="sf-nav-section-title">{{ __('seviaflow-ui::ui.navigation.developer') }}</div>
                        <div class="sf-nav-list">
                            @foreach($developerNav as $item)
                                <x-seviaflow-ui::sidebar-link :item="$item" />
                            @endforeach
                        </div>
                    </section>
                @endif

                @if(! $hasSidebarNavigation)
                    <p class="sf-nav-empty">{{ __('seviaflow-ui::ui.navigation.no_items') }}</p>
                @endif
            </div>

            <footer class="sf-sidebar-footer">
                @if($profileItem)
                    <a
                        href="{{ $profileItem->url }}"
                        @class(['sf-user-card', 'sf-user-card-active' => $profileItem->active])
                        @if($profileItem->active) aria-current="page" @endif
                        data-sf-nav-link
                    >
                        <span class="sf-user-avatar" aria-hidden="true">{{ $authInitials }}</span>
                        <span class="sf-user-copy">
                            <span class="sf-user-name">{{ $authUserLabel }}</span>
                            @if($authUserEmail)
                                <span class="sf-user-email">{{ $authUserEmail }}</span>
                            @else
                                <span class="sf-user-email">{{ __('seviaflow-ui::ui.account.settings') }}</span>
                            @endif
                        </span>
                    </a>
                @endif

                <div class="sf-account-actions">
                    @if($securityItem)
                        <a
                            href="{{ $securityItem->url }}"
                            @class(['sf-account-action', 'sf-account-action-active' => $securityItem->active])
                            @if($securityItem->active) aria-current="page" @endif
                            title="{{ $securityItem->label }}"
                            data-sf-nav-link
                        >
                            <x-seviaflow-ui::icon :name="$securityItem->icon" class="sf-account-action-icon" />
                            <span>{{ $securityItem->label }}</span>
                        </a>
                    @endif
                    @if($logoutItem)
                        <form method="POST" action="{{ $logoutItem->url }}">
                            @csrf
                            <button type="submit" class="sf-account-action" title="{{ $logoutItem->label }}">
                                <x-seviaflow-ui::icon :name="$logoutItem->icon" class="sf-account-action-icon" />
                                <span>{{ $logoutItem->label }}</span>
                            </button>
                        </form>
                    @endif
                </div>
            </footer>
        </aside>

        <div class="sf-main sf-main-{{ $contentWidthMode }}" data-sf-content-width="{{ $contentWidthMode }}">
            <header class="sf-topbar">
                <div class="sf-topbar-inner">
                    <button
                        type="button"
                        class="sf-mobile-menu"
                        aria-label="{{ __('seviaflow-ui::ui.navigation.open') }}"
                        aria-controls="sf-sidebar"
                        aria-expanded="false"
                        data-sf-label-expanded="{{ __('seviaflow-ui::ui.navigation.close') }}"
                        data-sf-label-collapsed="{{ __('seviaflow-ui::ui.navigation.open') }}"
                        data-sf-mobile-menu
                    >
                        <x-seviaflow-ui::icon name="bars-3" class="sf-topbar-icon" />
                    </button>

                    <div class="sf-topbar-context">
                        <span class="sf-topbar-context-icon" aria-hidden="true">
                            <x-seviaflow-ui::icon name="clipboard-document-check" class="sf-topbar-context-icon-svg" />
                        </span>
                        <span class="sf-topbar-context-copy">
                            <span>{{ __('seviaflow-ui::ui.navigation.workspace_context') }}</span>
                            <strong>{{ $pageTitle }}</strong>
                        </span>
                    </div>

                    <button
                        type="button"
                        class="sf-command-trigger"
                        aria-haspopup="dialog"
                        aria-controls="sf-command-palette"
                        aria-expanded="false"
                        data-sf-command-open
                    >
                        <x-seviaflow-ui::icon name="magnifying-glass" class="sf-command-trigger-icon" />
                        <span>{{ __('seviaflow-ui::ui.navigation.search') }}</span>
                        <kbd>Ctrl K</kbd>
                    </button>
                </div>
            </header>

            <div class="sf-content sf-content-{{ $contentWidthMode }}">
                <x-seviaflow-ui::page-heading
                    :eyebrow="$pageEyebrow"
                    :title="$pageTitle"
                    :description="$pageDescription"
                >
                    <x-slot:breadcrumbs>
                        @if($hasCustomBreadcrumbsSlot)
                            {{ $breadcrumbs }}
                        @else
                            <ol>
                                @foreach($breadcrumbItems as $index => $crumb)
                                    <li>
                                        @if($index > 0)
                                            <span aria-hidden="true">/</span>
                                        @endif
                                        @if(isset($crumb['url']) && is_string($crumb['url']) && $crumb['url'] !== '')
                                            <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                                        @else
                                            <span>{{ $crumb['label'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </x-slot:breadcrumbs>

                    @isset($actions)
                        <x-slot:actions>
                            {{ $actions }}
                        </x-slot:actions>
                    @endisset
                </x-seviaflow-ui::page-heading>

                <section class="sf-page-body">
                    {{ $slot }}
                </section>
            </div>
        </div>
    </div>

    <div class="sf-backdrop" hidden data-sf-overlay></div>

    <section
        id="sf-command-palette"
        class="sf-command-palette"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('seviaflow-ui::ui.navigation.search') }}"
        hidden
        data-sf-command-palette
    >
        <div class="sf-command-input">
            <x-seviaflow-ui::icon name="magnifying-glass" class="sf-command-input-icon" />
            <label for="sf-command-search" class="sr-only">{{ __('seviaflow-ui::ui.navigation.search_label') }}</label>
            <input id="sf-command-search" type="search" placeholder="{{ __('seviaflow-ui::ui.navigation.search_placeholder') }}" autocomplete="off" data-sf-command-input>
            <kbd>Esc</kbd>
        </div>
        <div class="sf-command-results" role="listbox" aria-label="{{ __('seviaflow-ui::ui.navigation.destinations') }}" data-sf-command-results></div>
        <p class="sf-command-empty" hidden data-sf-command-empty>{{ __('seviaflow-ui::ui.navigation.no_matches') }}</p>
        <script type="application/json" data-sf-command-items>@json($commandPaletteItems)</script>
    </section>
</main>
@include('seviaflow-ui::partials.assets-foot')
</body>
</html>
