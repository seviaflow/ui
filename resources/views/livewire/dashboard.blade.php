<div class="sf-dashboard-grid">
    <section class="sf-panel">
        <div class="sf-dashboard-toolbar">
            <div>
                <p class="sf-card-kicker">Dashboard</p>
                <h2 class="sf-page-title">{{ $dashboardLabel }}</h2>
                <p class="sf-card-description mt-2">
                    {{ $dashboardDescription ?: 'Use this dashboard to navigate your admin resources and manage daily operations.' }}
                </p>
                @if($userName || $userEmail)
                    <p class="sf-muted mt-3">
                        Signed in as
                        <span class="sf-item-title">{{ $userName ?: 'Admin user' }}</span>
                        @if($userEmail)
                            <span>({{ $userEmail }})</span>
                        @endif
                    </p>
                @endif
            </div>

            <div class="sf-resource-actions">
                @if($showDashboardSwitcher)
                    <div class="sf-dashboard-switcher">
                        <label class="sf-label" for="sf-dashboard-switcher">Dashboard</label>
                        <select id="sf-dashboard-switcher" class="sf-select sm:w-52" onchange="if (this.value) window.location.href=this.value;">
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
                    <div class="sf-resource-actions">
                        <div class="sf-resource-actions">
                            <label class="sf-label" for="sf-saved-layout-select">Saved layouts</label>
                            <select id="sf-saved-layout-select" wire:model="selectedSavedLayoutId" class="sf-select sm:w-48">
                                <option value="">Current layout</option>
                                @foreach($savedLayouts as $savedLayout)
                                    <option value="{{ $savedLayout['id'] }}">
                                        {{ $savedLayout['name'] }}@if($savedLayout['is_default']) (Default) @endif
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="applySavedLayout" class="sf-button sf-button-secondary !px-3 !py-1.5">
                                Apply
                            </button>
                            <button type="button" wire:click="setDefaultSavedLayout" class="sf-button sf-button-secondary !px-3 !py-1.5">
                                Set default
                            </button>
                            <button type="button" wire:click="deleteSavedLayout" class="sf-button sf-button-secondary !px-3 !py-1.5">
                                Delete
                            </button>
                        </div>
                        <div class="sf-resource-actions">
                            <input type="text" wire:model.defer="savedLayoutName" placeholder="Layout name" class="sf-input sm:w-48">
                            <button type="button" wire:click="saveCurrentAsLayout" class="sf-button sf-button-secondary !px-3 !py-1.5">
                                Save as new
                            </button>
                            <button type="button" wire:click="renameSavedLayout" class="sf-button sf-button-secondary !px-3 !py-1.5">
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
        </div>
    </section>

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
                            <div class="sf-inset-panel flex flex-wrap items-center justify-between gap-2 !py-2 text-xs">
                                <span class="sf-dashboard-drag-handle" data-sf-drag-handle="1" title="Drag to reorder widgets">Drag to reorder</span>
                                <div class="sf-resource-actions">
                                    <label class="sf-checkbox-label">
                                        Width
                                        <select class="sf-select !min-h-8 !w-16 !px-2 !py-1 text-xs" data-widget-width="1">
                                            @for($width = 1; $width <= 3; $width++)
                                                <option value="{{ $width }}" @selected((is_int($widget['columnSpan']) ? $widget['columnSpan'] : 1) === $width)>{{ $width }}</option>
                                            @endfor
                                        </select>
                                    </label>
                                    <label class="sf-checkbox-label">
                                        <input type="checkbox" data-widget-visible="1" checked class="sf-checkbox">
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
                    <p class="sf-card-kicker">Available resources</p>
                    <p class="sf-dashboard-metric">{{ $resourceCount }}</p>
                    <p class="sf-card-description mt-2">Resources you can access right now.</p>
                </div>
            </article>

            <article class="sf-card sf-dashboard-section">
                <div class="sf-card-body">
                    <h3 class="sf-card-title">Resource overview</h3>
                    @if($resourceItems === [])
                        <div class="sf-dashboard-empty mt-3">
                            No resources are available yet. Register resources in `config/seviaflow.php` and ensure
                            your role can view them.
                        </div>
                    @else
                        <ul class="sf-widget-list mt-3 sm:grid-cols-2">
                            @foreach($resourceItems as $item)
                                <li class="sf-widget-list-item">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="sf-item-title">{{ $item['label'] }}</p>
                                            <p class="sf-item-meta mt-1">Slug: {{ $item['slug'] }}</p>
                                            @if($showResourceCounts && $item['count'] !== null)
                                                <p class="sf-item-meta mt-2">Records: {{ $item['count'] }}</p>
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
                <h3 class="sf-card-title">Workflow overview</h3>
                <p class="sf-card-description mt-1">Workflow-enabled resources and current process configuration.</p>

                @if($workflowItems === [])
                    <div class="sf-dashboard-empty mt-3">
                        No workflow-enabled resources are currently available.
                    </div>
                @else
                    <ul class="sf-widget-list mt-3 lg:grid-cols-2">
                        @foreach($workflowItems as $item)
                            <li class="sf-widget-list-item">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="sf-item-title">{{ $item['label'] }}</p>
                                        <p class="sf-item-meta mt-1">State field: {{ $item['stateField'] }}</p>
                                        <p class="sf-item-meta mt-1">States: {{ implode(', ', $item['states']) }}</p>
                                        @if($showWorkflowAttentionCounts && $item['attentionCount'] !== null)
                                            <p class="sf-item-meta mt-2">Needs attention: {{ $item['attentionCount'] }}</p>
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
                <h3 class="sf-card-title">Recent activity</h3>
                <p class="sf-card-description mt-1">Latest workflow transitions across resources you can access.</p>

                @if($recentActivities === [])
                    <div class="sf-dashboard-empty mt-3">
                        No recent workflow activity found.
                    </div>
                @else
                    <ul class="sf-widget-list mt-3">
                        @foreach($recentActivities as $item)
                            <li class="sf-widget-list-item">
                                <p class="sf-item-title">{{ $item['resourceLabel'] }}</p>
                                <p class="sf-item-body mt-1">
                                    {{ $item['transition'] }}:
                                    {{ $item['fromState'] ?? 'None' }}
                                    <span aria-hidden="true">→</span>
                                    {{ $item['toState'] }}
                                </p>
                                <p class="sf-item-meta mt-1">
                                    {{ $item['recordIdentifier'] }} · by {{ $item['userLabel'] }}
                                    @if($item['happenedAt'])
                                        · {{ $item['happenedAt'] }}
                                    @endif
                                </p>
                                @if(is_string($item['comment']) && trim($item['comment']) !== '')
                                    <p class="sf-item-meta mt-2">Comment: {{ $item['comment'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </article>

        <article class="sf-card sf-dashboard-section">
            <div class="sf-card-body">
                <h3 class="sf-card-title">Quick actions</h3>
                <p class="sf-card-description mt-1">Shortcuts to common admin tasks.</p>

                @if($quickActions === [])
                    <div class="sf-dashboard-empty mt-3">
                        No quick actions are currently available.
                    </div>
                @else
                    <div class="sf-resource-actions mt-3">
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
