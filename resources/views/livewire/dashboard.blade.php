<div class="sf-dashboard-grid">
    <div class="sf-card sf-dashboard-section">
        <div class="sf-card-body">
            <div class="sf-dashboard-toolbar">
                <h2 class="sf-page-title">{{ $dashboardLabel }}</h2>
                @if($showDashboardSwitcher)
                    <div class="sf-dashboard-switcher">
                        <label class="text-xs text-slate-500" for="sf-dashboard-switcher">Dashboard</label>
                        <select id="sf-dashboard-switcher" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs" onchange="if (this.value) window.location.href=this.value;">
                            @foreach($dashboardOptions as $dashboardOption)
                                <option
                                    value="{{ route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'dashboards.show', ['dashboard' => $dashboardOption['slug']]) }}"
                                    @selected($dashboardSlug === $dashboardOption['slug'])
                                >
                                    {{ $dashboardOption['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if($canCustomizeLayout)
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="text-xs text-slate-500" for="sf-saved-layout-select">Saved layouts</label>
                            <select id="sf-saved-layout-select" wire:model="selectedSavedLayoutId" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs">
                                <option value="">Current layout</option>
                                @foreach($savedLayouts as $savedLayout)
                                    <option value="{{ $savedLayout['id'] }}">
                                        {{ $savedLayout['name'] }}@if($savedLayout['is_default']) (Default) @endif
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="applySavedLayout" class="sf-button sf-button-secondary !px-3 !py-1.5 text-xs">
                                Apply
                            </button>
                            <button type="button" wire:click="setDefaultSavedLayout" class="sf-button sf-button-secondary !px-3 !py-1.5 text-xs">
                                Set default
                            </button>
                            <button type="button" wire:click="deleteSavedLayout" class="sf-button sf-button-secondary !px-3 !py-1.5 text-xs">
                                Delete
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="text" wire:model.defer="savedLayoutName" placeholder="Layout name" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs">
                            <button type="button" wire:click="saveCurrentAsLayout" class="sf-button sf-button-secondary !px-3 !py-1.5 text-xs">
                                Save as new
                            </button>
                            <button type="button" wire:click="renameSavedLayout" class="sf-button sf-button-secondary !px-3 !py-1.5 text-xs">
                                Rename
                            </button>
                        </div>
                        @if(!$layoutEditing)
                            <button type="button" wire:click="startLayoutEditing" class="sf-button sf-button-secondary">
                                Customize
                            </button>
                        @else
                            <button type="button" wire:click="saveLayout" class="sf-button sf-button-primary">
                                Save layout
                            </button>
                            <button type="button" wire:click="cancelLayoutEditing" class="sf-button sf-button-secondary">
                                Cancel
                            </button>
                            <button type="button" wire:click="resetLayout" class="sf-button sf-button-secondary">
                                Reset to default
                            </button>
                        @endif
                    </div>
                @endif
            </div>
            <p class="mt-2 text-sm text-slate-600">
                {{ $dashboardDescription ?: 'Use this dashboard to navigate your admin resources and manage daily operations.' }}
            </p>
            @if($userName || $userEmail)
                <p class="mt-4 text-sm text-slate-500">
                    Signed in as
                    <span class="font-medium text-slate-900">{{ $userName ?: 'Admin user' }}</span>
                    @if($userEmail)
                        <span class="text-slate-400">({{ $userEmail }})</span>
                    @endif
                </p>
            @endif
        </div>
    </div>

    @if($noVisibleDashboards)
        <article class="sf-card sf-dashboard-section">
            <div class="sf-card-body">
                <div class="sf-dashboard-empty">
                    No dashboards are currently visible for your account.
                </div>
            </div>
        </article>
    @endif

    @if($hasWidgets)
        <input type="hidden" wire:model="layoutPayload" data-sf-dashboard-layout-input>
        <section
            class="sf-dashboard-widget-grid {{ $layoutEditing ? 'sf-dashboard-widget-grid-editing' : '' }}"
            aria-label="Dashboard widgets"
            data-sf-dashboard-layout-editor="1"
            data-layout-editing="{{ $layoutEditing ? '1' : '0' }}"
        >
            @foreach($widgets as $widget)
                <article
                    class="sf-card sf-dashboard-widget {{ $widget['columnSpanClass'] }}"
                    data-widget="{{ $widget['name'] }}"
                    data-widget-span="{{ is_int($widget['columnSpan']) ? $widget['columnSpan'] : 1 }}"
                    @if($layoutEditing) draggable="true" @endif
                >
                    @if($layoutEditing)
                        <div class="px-4 pt-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                <span class="sf-dashboard-drag-handle" data-sf-drag-handle="1" title="Drag to reorder widgets">Drag to reorder</span>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-slate-500">
                                        Width
                                        <select class="ml-1 rounded border border-slate-300 bg-white px-1 py-0.5 text-xs" data-widget-width="1">
                                            @for($width = 1; $width <= 3; $width++)
                                                <option value="{{ $width }}" @selected((is_int($widget['columnSpan']) ? $widget['columnSpan'] : 1) === $width)>{{ $width }}</option>
                                            @endfor
                                        </select>
                                    </label>
                                    <label class="flex items-center gap-1 text-xs text-slate-500">
                                        <input type="checkbox" data-widget-visible="1" checked>
                                        Visible
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif
                    @include($widget['view'], array_merge($widget['viewData'], ['widget' => $widget]))
                </article>
            @endforeach
        </section>
    @elseif(!$noVisibleDashboards)
    <div class="sf-dashboard-grid-panels">
        <article class="sf-card sf-dashboard-card">
            <div class="sf-card-body">
                <p class="text-sm font-medium text-slate-500">Available resources</p>
                <p class="sf-dashboard-metric">{{ $resourceCount }}</p>
                <p class="mt-2 text-sm text-slate-500">Resources you can access right now.</p>
            </div>
        </article>

        <article class="sf-card sf-dashboard-section lg:col-span-2">
            <div class="sf-card-body">
                <h3 class="text-base font-semibold text-slate-900">Resource overview</h3>
                @if($resourceItems === [])
                    <div class="sf-dashboard-empty">
                        No resources are available yet. Register resources in `config/seviaflow.php` and ensure
                        your role can view them.
                    </div>
                @else
                    <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach($resourceItems as $item)
                            <li class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ $item['label'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Slug: {{ $item['slug'] }}</p>
                                        @if($showResourceCounts && $item['count'] !== null)
                                            <p class="mt-2 text-xs text-slate-600">Records: {{ $item['count'] }}</p>
                                        @endif
                                    </div>
                                    <a href="{{ $item['url'] }}" class="sf-button sf-button-secondary !px-3 !py-1.5">
                                        View
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </article>
    </div>

    <article class="sf-card sf-dashboard-section">
        <div class="sf-card-body">
            <h3 class="text-base font-semibold text-slate-900">Workflow overview</h3>
            <p class="mt-1 text-sm text-slate-500">Workflow-enabled resources and current process configuration.</p>

            @if($workflowItems === [])
                <div class="sf-dashboard-empty">
                    No workflow-enabled resources are currently available.
                </div>
            @else
                <ul class="mt-3 grid gap-3 lg:grid-cols-2">
                    @foreach($workflowItems as $item)
                        <li class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $item['label'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">State field: {{ $item['stateField'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">States: {{ implode(', ', $item['states']) }}</p>
                                    @if($showWorkflowAttentionCounts && $item['attentionCount'] !== null)
                                        <p class="mt-2 text-xs text-slate-600">Needs attention: {{ $item['attentionCount'] }}</p>
                                    @endif
                                </div>
                                <a href="{{ $item['url'] }}" class="sf-button sf-button-secondary !px-3 !py-1.5">
                                    View
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </article>

    <article class="sf-card sf-dashboard-section">
        <div class="sf-card-body">
            <h3 class="text-base font-semibold text-slate-900">Recent activity</h3>
            <p class="mt-1 text-sm text-slate-500">Latest workflow transitions across resources you can access.</p>

            @if($recentActivities === [])
                <div class="sf-dashboard-empty">
                    No recent workflow activity found.
                </div>
            @else
                <ul class="mt-3 space-y-3">
                    @foreach($recentActivities as $item)
                        <li class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                            <p class="text-sm font-semibold text-slate-900">{{ $item['resourceLabel'] }}</p>
                            <p class="mt-1 text-sm text-slate-700">
                                {{ $item['transition'] }}:
                                {{ $item['fromState'] ?? 'None' }}
                                <span aria-hidden="true">→</span>
                                {{ $item['toState'] }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $item['recordIdentifier'] }} · by {{ $item['userLabel'] }}
                                @if($item['happenedAt'])
                                    · {{ $item['happenedAt'] }}
                                @endif
                            </p>
                            @if(is_string($item['comment']) && trim($item['comment']) !== '')
                                <p class="mt-2 text-xs text-slate-600">Comment: {{ $item['comment'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </article>

    <article class="sf-card sf-dashboard-section">
        <div class="sf-card-body">
            <h3 class="text-base font-semibold text-slate-900">Quick actions</h3>
            <p class="mt-1 text-sm text-slate-500">Shortcuts to common admin tasks.</p>

            @if($quickActions === [])
                <div class="sf-dashboard-empty">
                    No quick actions are currently available.
                </div>
            @else
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($quickActions as $action)
                        <a href="{{ $action['url'] }}" class="sf-button sf-button-secondary">
                            {{ $action['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </article>
    @endif
</div>
