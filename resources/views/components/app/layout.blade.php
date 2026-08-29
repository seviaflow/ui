<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SeviaFlow' }}</title>
    @include('seviaflow-ui::partials.assets-head')
</head>
<body>
@php
    use SeviaFlow\Ui\Navigation\AdminNavigationBuilder;
    use Illuminate\Support\Str;

    $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');
    $currentRouteName = (string) optional(request()->route())->getName();
    $currentResourceSlug = request()->route('resource');
    $currentResourceSlug = is_string($currentResourceSlug) ? $currentResourceSlug : null;
    $navigationGroups = app(AdminNavigationBuilder::class)->build(
        actor: auth()->user(),
        currentRouteName: $currentRouteName,
        currentResourceSlug: $currentResourceSlug
    );
    $navigationByKey = [];
    foreach ($navigationGroups as $group) {
        $navigationByKey[$group->key] = $group;
    }
    $mainNav = $navigationByKey['main']?->items ?? [];
    $resourcesNav = $navigationByKey['resources']?->items ?? [];
    $settingsNav = $navigationByKey['settings']?->items ?? [];
    $developerNav = $navigationByKey['developer']?->items ?? [];
    $accountNav = $navigationByKey['account']?->items ?? [];
    $profileItem = collect($accountNav)->first(fn ($item): bool => $item->key === 'account.profile');
    $securityItem = collect($accountNav)->first(fn ($item): bool => $item->key === 'account.security');
    $logoutItem = collect($accountNav)->first(fn ($item): bool => $item->key === 'account.logout');
    $profileUrl = $profileItem?->url;
    $navigationSections = [
        ['label' => 'Main', 'items' => $mainNav, 'headingClass' => 'pt-2'],
        ['label' => 'Resources', 'items' => $resourcesNav, 'headingClass' => 'pt-4'],
        ['label' => 'Settings', 'items' => $settingsNav, 'headingClass' => 'pt-4'],
        ['label' => 'Developer', 'items' => $developerNav, 'headingClass' => 'pt-4'],
    ];
    $hasSidebarNavigation = count($mainNav) > 0
        || count($resourcesNav) > 0
        || count($settingsNav) > 0
        || count($developerNav) > 0;

    $dashboardRouteName = \Illuminate\Support\Facades\Route::has($routeNamePrefix.'dashboard')
        ? $routeNamePrefix.'dashboard'
        : $routeNamePrefix.'home';

    $defaultTitle = 'Admin';
    $defaultDescription = 'Operator workspace';
    $defaultBreadcrumbs = [['label' => 'Admin', 'url' => route($dashboardRouteName)]];

    if ($currentResourceSlug !== null) {
        $resourceLabel = Str::of($currentResourceSlug)->replace('-', ' ')->headline()->toString();
        $defaultTitle = $resourceLabel;
        $defaultDescription = 'Manage '.$resourceLabel.' records.';
        $defaultBreadcrumbs[] = ['label' => 'Resources', 'url' => null];
        $defaultBreadcrumbs[] = ['label' => $resourceLabel, 'url' => null];
    } elseif (str_starts_with($currentRouteName, $routeNamePrefix.'settings.roles.')) {
        $defaultTitle = 'Roles';
        $defaultDescription = 'Manage admin roles and permissions.';
        $defaultBreadcrumbs[] = ['label' => 'Settings', 'url' => null];
        $defaultBreadcrumbs[] = ['label' => 'Roles', 'url' => null];
    } elseif (str_starts_with($currentRouteName, $routeNamePrefix.'settings.admin-users.')) {
        $defaultTitle = 'Admin users';
        $defaultDescription = 'Manage role assignments for admin users.';
        $defaultBreadcrumbs[] = ['label' => 'Settings', 'url' => null];
        $defaultBreadcrumbs[] = ['label' => 'Admin users', 'url' => null];
    } elseif (str_starts_with($currentRouteName, $routeNamePrefix.'profile.')) {
        $defaultTitle = 'Account settings';
        $defaultDescription = 'Manage your admin account security and profile details.';
        $defaultBreadcrumbs[] = ['label' => 'Profile', 'url' => null];
    }

    $pageTitle = isset($pageTitle) ? (string) $pageTitle : $defaultTitle;
    $pageDescription = isset($pageDescription) ? (string) $pageDescription : $defaultDescription;
    $breadcrumbItems = isset($breadcrumbs) && is_array($breadcrumbs) ? $breadcrumbs : $defaultBreadcrumbs;
    $hasCustomBreadcrumbsSlot = isset($breadcrumbs) && ! is_array($breadcrumbs);
    $authUser = auth()->user();
    $authUserName = is_object($authUser) && isset($authUser->name) ? (string) $authUser->name : null;
    $authUserEmail = is_object($authUser) && isset($authUser->email) ? (string) $authUser->email : null;
@endphp
<main class="sf-shell">
    <div class="sf-shell-grid">
        <aside class="sf-sidebar">
            <div class="sf-sidebar-brand">
                <div class="sf-auth-logo !mb-0 !h-10 !w-10 !text-sm">SF</div>
                <div>
                    <p class="text-sm font-semibold text-white">SeviaFlow</p>
                    <p class="text-xs text-slate-400">by SeviaFlow</p>
                </div>
            </div>

            <nav class="sf-sidebar-nav" aria-label="Primary navigation">
                @foreach($navigationSections as $section)
                    @if(count($section['items']) > 0)
                        <p class="px-3 {{ $section['headingClass'] }} text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $section['label'] }}</p>
                        @foreach($section['items'] as $item)
                            <x-seviaflow-ui::sidebar-link :item="$item" />
                        @endforeach
                    @endif
                @endforeach

                @if(! $hasSidebarNavigation)
                    <p class="px-3 pt-2 text-xs text-slate-400">No navigation items available.</p>
                @endif
            </nav>

            @if($profileUrl !== null)
                <div class="border-t border-slate-800 p-3">
                    <x-seviaflow-ui::sidebar-link :item="$profileItem" />
                </div>
            @endif
        </aside>

        <div class="sf-main">
            <header class="sf-topbar">
                <div class="sf-topbar-inner">
                    <div>
                        <div class="text-sm text-slate-600">Operator console</div>
                        @if($authUserName || $authUserEmail)
                            <div class="text-xs text-slate-500">
                                {{ $authUserName ?: 'Admin user' }}
                                @if($authUserEmail)
                                    <span class="text-slate-400">({{ $authUserEmail }})</span>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if($securityItem)
                            <a href="{{ $securityItem->url }}" class="sf-button sf-button-secondary !py-2">
                                <x-seviaflow-ui::icon :name="$securityItem->icon" class="sf-topbar-action-icon" />
                                <span>Account settings</span>
                            </a>
                        @endif
                        @if($logoutItem)
                            <form method="POST" action="{{ $logoutItem->url }}">
                                @csrf
                                <button type="submit" class="sf-button sf-button-ghost !py-2">
                                    <x-seviaflow-ui::icon :name="$logoutItem->icon" class="sf-topbar-action-icon" />
                                    <span>{{ $logoutItem->label }}</span>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </header>

            <div class="sf-content">
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                    <nav aria-label="Breadcrumb">
                        @if($hasCustomBreadcrumbsSlot)
                            <div class="flex flex-wrap items-center gap-2">
                                {{ $breadcrumbs }}
                            </div>
                        @else
                            <ol class="flex flex-wrap items-center gap-2">
                                @foreach($breadcrumbItems as $index => $crumb)
                                    <li class="inline-flex items-center gap-2">
                                        @if($index > 0)
                                            <span class="text-slate-400">/</span>
                                        @endif
                                        @if(isset($crumb['url']) && is_string($crumb['url']) && $crumb['url'] !== '')
                                            <a href="{{ $crumb['url'] }}" class="sf-link !text-sm">{{ $crumb['label'] }}</a>
                                        @else
                                            <span class="font-medium text-slate-900">{{ $crumb['label'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </nav>
                </div>

                <section class="sf-page-header">
                    <div>
                        <h1 class="sf-page-title">{{ $pageTitle }}</h1>
                        <p class="sf-page-subtitle">{{ $pageDescription }}</p>
                    </div>
                    @isset($actions)
                        <div>{{ $actions }}</div>
                    @endisset
                </section>

                <section>
                    {{ $slot }}
                </section>
            </div>
        </div>
    </div>
</main>
@include('seviaflow-ui::partials.assets-foot')
</body>
</html>
