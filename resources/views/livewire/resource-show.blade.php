@php
    $hasRelations = count($relationManagers) > 0;
    $hasSideContext = $workflowPanel['visible'] || $hasRelations;
    $relationAnchors = [];
    foreach ($relationManagers as $manager) {
        $relationAnchors[$manager['relationshipName']] = 'sf-relation-'.\Illuminate\Support\Str::slug((string) $manager['relationshipName']);
    }
@endphp

<div class="grid gap-4">
    @if($statusMessage)
        @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'success', 'message' => $statusMessage])
    @endif
    @if($errorMessage)
        @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'danger', 'message' => $errorMessage])
    @endif

    <section class="sf-card">
        <div class="sf-card-body">
            <div class="sf-record-header">
                <div>
                    <p class="sf-record-kicker">Record details</p>
                    <h1 class="sf-record-title">{{ $title }}</h1>
                    @if($workflowPanel['visible'])
                        <div class="sf-page-meta">
                            <span class="sf-badge {{ $workflowStateColorClasses($workflowPanel['currentStateColor']) }}">
                                {{ $workflowPanel['currentStateLabel'] ?? 'Unknown' }}
                            </span>
                        </div>
                    @endif
                </div>
                <div class="sf-resource-actions">
                    @foreach($actions as $action)
                        @if($pendingActionName === $action->name())
                            @component('seviaflow-ui::livewire.partials.action-confirmation', [
                                'variant' => $action->isDanger() ? 'danger' : 'safe',
                                'title' => $action->isDanger() ? 'Confirm dangerous action' : 'Confirm action',
                                'message' => 'Review this action before continuing.',
                                'consequences' => $action->isDanger()
                                    ? ['This action may not be reversible.', 'Record state can change immediately.']
                                    : [],
                            ])
                                <button
                                    type="button"
                                    wire:click="executeAction('{{ $action->name() }}', true)"
                                    class="sf-button {{ $action->isDanger() ? 'sf-button-danger' : 'sf-button-primary' }}"
                                >
                                    {{ $action->getConfirmButton() ?? 'Confirm' }}
                                </button>
                                <button
                                    type="button"
                                    wire:click="$set('pendingActionName', null);"
                                    class="sf-button sf-button-secondary"
                                >
                                    {{ $action->getCancelButton() ?? 'Cancel' }}
                                </button>
                            @endcomponent
                        @else
                            <button
                                type="button"
                                wire:click="executeAction('{{ $action->name() }}')"
                                @class([
                                    'sf-button',
                                    'sf-button-danger' => $action->isDanger(),
                                    'sf-button-secondary' => ! $action->isDanger(),
                                ])
                            >
                                {{ $action->getLabel() }}
                            </button>
                        @endif
                    @endforeach
                    <a href="{{ $backUrl }}" class="sf-button sf-button-secondary">Back</a>
                    @if($canEdit)
                        <a href="{{ $editUrl }}" class="sf-button sf-button-primary">Edit</a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="sf-record-layout {{ $hasSideContext ? '' : 'sf-record-layout-no-side' }}">
        <div class="sf-record-main">
            @if(count($sections) > 0)
                @foreach($sections as $section)
                    <section class="sf-card">
                        <div class="sf-card-header">
                            <h2 class="sf-section-title">{{ $section->label() }}</h2>
                            @if($section->getDescription())
                                <p class="sf-section-description">{{ $section->getDescription() }}</p>
                            @endif
                        </div>
                        <div class="sf-card-body">
                            <dl class="sf-description-list">
                                @foreach($section->getFields() as $field)
                                    <div class="sf-description-row">
                                        <dt class="sf-description-label">{{ $field->getLabel() }}</dt>
                                        <dd class="sf-description-value">{{ $formatValue($field) }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </section>
                @endforeach
            @else
                <section class="sf-card">
                    <div class="sf-card-header">
                        <h2 class="sf-section-title">Fields</h2>
                    </div>
                    <div class="sf-card-body">
                        <dl class="sf-description-list">
                            @foreach($fields as $field)
                                <div class="sf-description-row">
                                    <dt class="sf-description-label">{{ $field->getLabel() }}</dt>
                                    <dd class="sf-description-value">{{ $formatValue($field) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </section>
            @endif
        </div>

        @if($hasSideContext)
            <aside class="sf-record-side" aria-label="Record context">
                @if($hasRelations)
                    <section class="sf-card">
                        <div class="sf-card-header">
                            <h2 class="sf-card-title">Connections</h2>
                            <p class="sf-card-description">Related data available for this record.</p>
                        </div>
                        <div class="sf-card-body">
                            <div class="sf-connection-list">
                                @foreach($relationManagers as $manager)
                                    @php
                                        $records = $manager['records'];
                                        $safeCount = method_exists($records, 'total')
                                            ? $records->total()
                                            : (is_countable($records) ? count($records) : null);
                                    @endphp
                                    <a href="#{{ $relationAnchors[$manager['relationshipName']] }}" class="sf-connection-item">
                                        <span>
                                            <span class="block sf-item-title">{{ $manager['label'] }}</span>
                                            <span class="block sf-item-meta">{{ \Illuminate\Support\Str::headline($manager['type']) }}</span>
                                        </span>
                                        @if($safeCount !== null)
                                            <span class="sf-summary-pill">{{ $safeCount }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                @if($workflowPanel['visible'])
                    <section class="sf-workflow-panel">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="sf-card-title">Workflow</h2>
                                <p class="sf-card-description">Current process state and available transitions.</p>
                            </div>
                            <span class="sf-badge {{ $workflowStateColorClasses($workflowPanel['currentStateColor']) }}">
                                {{ $workflowPanel['currentStateLabel'] ?? 'Unknown' }}
                            </span>
                        </div>

                        @if(count($workflowPanel['availableTransitions']) > 0)
                            <div class="sf-flow-list mt-4">
                                @foreach($workflowPanel['availableTransitions'] as $transition)
                                    <div class="sf-flow-item">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p class="sf-item-title">{{ $transition->getLabel() }}</p>
                                                <p class="sf-item-meta">To: {{ ucfirst(str_replace('_', ' ', $transition->getToState())) }}</p>
                                            </div>
                                            <div class="sf-resource-actions">
                                                @if($pendingWorkflowTransitionName === $transition->name() && $transition->requiresConfirmation())
                                                    @component('seviaflow-ui::livewire.partials.action-confirmation', [
                                                        'variant' => $transition->isDanger() ? 'danger' : 'safe',
                                                        'title' => $transition->isDanger() ? 'Confirm dangerous transition' : 'Confirm transition',
                                                        'message' => 'This workflow transition will update the current state.',
                                                        'consequences' => $transition->isDanger() ? ['The record may enter a final or restricted state.'] : [],
                                                    ])
                                                        <button
                                                            type="button"
                                                            wire:click="executeWorkflowTransition('{{ $transition->name() }}', true)"
                                                            class="sf-button {{ $transition->isDanger() ? 'sf-button-danger' : 'sf-button-primary' }} !px-3 !py-1.5"
                                                        >
                                                            Confirm
                                                        </button>
                                                        <button
                                                            type="button"
                                                            wire:click="$set('pendingWorkflowTransitionName', null)"
                                                            class="sf-button sf-button-secondary !px-3 !py-1.5"
                                                        >
                                                            Cancel
                                                        </button>
                                                    @endcomponent
                                                @else
                                                    <button
                                                        type="button"
                                                        wire:click="executeWorkflowTransition('{{ $transition->name() }}')"
                                                        @class([
                                                            'sf-button !px-3 !py-1.5',
                                                            'sf-button-danger' => $transition->isDanger(),
                                                            'sf-button-secondary' => ! $transition->isDanger(),
                                                        ])
                                                    >
                                                        {{ $transition->getLabel() }}
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                        @if($transition->requiresComment())
                                            <div class="mt-3">
                                                <label for="workflow-comment-{{ $transition->name() }}" class="sf-label">Comment</label>
                                                <textarea
                                                    id="workflow-comment-{{ $transition->name() }}"
                                                    wire:model.defer="workflowTransitionComment"
                                                    rows="2"
                                                    class="sf-textarea"
                                                    placeholder="Add reason for this transition"
                                                ></textarea>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="sf-muted mt-4">No transitions are currently available.</p>
                        @endif

                        <div class="mt-5">
                            <h3 class="sf-card-title">Activity timeline</h3>
                            <div class="sf-timeline mt-3">
                                @forelse($workflowPanel['activities'] as $activity)
                                    <div class="sf-timeline-item">
                                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                            <p class="sf-item-title">
                                                {{ ucfirst(str_replace('_', ' ', $activity->transition)) }}
                                                <span class="sf-item-meta font-normal">({{ $activity->from_state ?? '—' }} → {{ $activity->to_state }})</span>
                                            </p>
                                            <p class="sf-item-meta">{{ $activity->created_at?->format('Y-m-d H:i') }}</p>
                                        </div>
                                        <p class="sf-item-meta mt-1">By: {{ $workflowPanel['userLabel']($activity) }}</p>
                                        @if($activity->comment)
                                            <p class="sf-item-body mt-1">Comment: {{ $activity->comment }}</p>
                                        @endif
                                    </div>
                                @empty
                                    <p class="sf-muted">No workflow activity yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </section>
                @endif
            </aside>
        @endif
    </div>

    @foreach($relationManagers as $manager)
        <section id="{{ $relationAnchors[$manager['relationshipName']] }}" class="sf-card">
            <div class="sf-card-header">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="sf-card-title">{{ $manager['label'] }}</h2>
                        <p class="sf-card-description">{{ \Illuminate\Support\Str::headline($manager['type']) }} relation</p>
                    </div>
                    @if($manager['type'] === 'hasMany' && $manager['canCreate'])
                        <button
                            type="button"
                            wire:click="openHasManyCreateModal('{{ $manager['relationshipName'] }}')"
                            class="sf-button sf-button-primary !px-3 !py-2"
                        >
                            {{ $manager['createLabel'] }}
                        </button>
                    @endif
                    @if($manager['type'] === 'hasOne' && $manager['canCreate'])
                        <button
                            type="button"
                            wire:click="openHasOneCreateModal('{{ $manager['relationshipName'] }}')"
                            class="sf-button sf-button-primary !px-3 !py-2"
                        >
                            {{ $manager['createLabel'] }}
                        </button>
                    @endif
                    @if($manager['type'] === 'belongsToMany' && $manager['canAttach'])
                        <div class="sf-resource-actions">
                            <label for="attach-{{ $manager['relationshipName'] }}" class="sr-only">Attach related record</label>
                            <select
                                id="attach-{{ $manager['relationshipName'] }}"
                                wire:model="attachSelection.{{ $manager['relationshipName'] }}"
                                class="sf-select"
                            >
                                <option value="">Select record to attach</option>
                                @foreach($manager['attachOptions'] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @if(count($manager['pivotFields']) > 0)
                                <button
                                    type="button"
                                    wire:click="openBelongsToManyAttachModal('{{ $manager['relationshipName'] }}')"
                                    class="sf-button sf-button-primary !px-3 !py-2"
                                >
                                    Attach
                                </button>
                            @else
                                <button
                                    type="button"
                                    wire:click="attachBelongsToMany('{{ $manager['relationshipName'] }}')"
                                    class="sf-button sf-button-primary !px-3 !py-2"
                                >
                                    Attach
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="sf-card-body">
                <div class="sf-table-wrapper">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                @foreach($manager['columns'] as $column)
                                    <th scope="col" class="sf-table-header">{{ $column->getLabel() }}</th>
                                @endforeach
                                @foreach($manager['pivotColumns'] as $pivotColumn)
                                    <th scope="col" class="sf-table-header">{{ $pivotColumn->getLabel() }}</th>
                                @endforeach
                                <th scope="col" class="sf-table-header">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($manager['records'] as $relationRecord)
                                <tr>
                                    @foreach($manager['columns'] as $column)
                                        <td class="sf-table-cell">{!! $formatColumnValue($relationRecord, $column) !!}</td>
                                    @endforeach
                                    @foreach($manager['pivotColumns'] as $pivotColumn)
                                        <td class="sf-table-cell">{!! $formatPivotColumnValue($relationRecord, $pivotColumn) !!}</td>
                                    @endforeach
                                    <td class="sf-table-cell">
                                        <div class="sf-row-actions">
                                            @if($manager['showUrl']($relationRecord))
                                                <a href="{{ $manager['showUrl']($relationRecord) }}" class="sf-button sf-button-secondary !px-3 !py-1.5">View</a>
                                            @endif
                                            @if($manager['editUrl']($relationRecord))
                                                <a href="{{ $manager['editUrl']($relationRecord) }}" class="sf-button sf-button-secondary !px-3 !py-1.5">Edit</a>
                                            @endif
                                            @if($manager['type'] === 'belongsToMany' && $manager['canEditPivot'] && $manager['canEditPivotRecord']($relationRecord))
                                                <button
                                                    type="button"
                                                    wire:click="openBelongsToManyEditPivotModal('{{ $manager['relationshipName'] }}', '{{ $relationRecord->getKey() }}')"
                                                    class="sf-button sf-button-secondary !px-3 !py-1.5"
                                                >
                                                    Edit pivot
                                                </button>
                                            @endif
                                            @if($manager['type'] === 'hasMany' && $manager['canEdit'] && $manager['canEditRecord']($relationRecord))
                                                <button
                                                    type="button"
                                                    wire:click="openHasManyEditModal('{{ $manager['relationshipName'] }}', '{{ $relationRecord->getKey() }}')"
                                                    class="sf-button sf-button-secondary !px-3 !py-1.5"
                                                >
                                                    {{ $manager['editLabel'] }}
                                                </button>
                                            @endif
                                            @if($manager['type'] === 'hasOne' && $manager['canEdit'] && $manager['canEditRecord']($relationRecord))
                                                <button
                                                    type="button"
                                                    wire:click="openHasOneEditModal('{{ $manager['relationshipName'] }}', '{{ $relationRecord->getKey() }}')"
                                                    class="sf-button sf-button-secondary !px-3 !py-1.5"
                                                >
                                                    {{ $manager['editLabel'] }}
                                                </button>
                                            @endif
                                            @if($manager['type'] === 'hasMany' && $manager['canDelete'] && $manager['canDeleteRecord']($relationRecord))
                                                @if($pendingHasManyDeleteRelation === $manager['relationshipName'] && (string) $pendingHasManyDeleteRecordId === (string) $relationRecord->getKey())
                                                    <button
                                                        type="button"
                                                        wire:click="deleteHasManyRecord('{{ $manager['relationshipName'] }}', '{{ $relationRecord->getKey() }}', true)"
                                                        class="sf-button sf-button-danger !px-3 !py-1.5"
                                                    >
                                                        Confirm move to trash
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click="$set('pendingHasManyDeleteRelation', null); $set('pendingHasManyDeleteRecordId', null);"
                                                        class="sf-button sf-button-secondary !px-3 !py-1.5"
                                                    >
                                                        Cancel
                                                    </button>
                                                @else
                                                    <button
                                                        type="button"
                                                        wire:click="deleteHasManyRecord('{{ $manager['relationshipName'] }}', '{{ $relationRecord->getKey() }}')"
                                                        class="sf-button sf-button-danger !px-3 !py-1.5"
                                                    >
                                                        Move to trash
                                                    </button>
                                                @endif
                                            @endif
                                            @if($manager['type'] === 'hasOne' && $manager['canDelete'] && $manager['canDeleteRecord']($relationRecord))
                                                @if($pendingHasOneDeleteRelation === $manager['relationshipName'])
                                                    <button
                                                        type="button"
                                                        wire:click="deleteHasOneRecord('{{ $manager['relationshipName'] }}', '{{ $relationRecord->getKey() }}', true)"
                                                        class="sf-button sf-button-danger !px-3 !py-1.5"
                                                    >
                                                        Confirm delete
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click="$set('pendingHasOneDeleteRelation', null);"
                                                        class="sf-button sf-button-secondary !px-3 !py-1.5"
                                                    >
                                                        Cancel
                                                    </button>
                                                @else
                                                    <button
                                                        type="button"
                                                        wire:click="deleteHasOneRecord('{{ $manager['relationshipName'] }}', '{{ $relationRecord->getKey() }}')"
                                                        class="sf-button sf-button-danger !px-3 !py-1.5"
                                                    >
                                                        Delete
                                                    </button>
                                                @endif
                                            @endif
                                            @if($manager['type'] === 'hasMany' && $manager['canRestore'] && $manager['canRestoreRecord']($relationRecord))
                                                <button
                                                    type="button"
                                                    wire:click="restoreHasManyRecord('{{ $manager['relationshipName'] }}', '{{ $relationRecord->getKey() }}', true)"
                                                    class="sf-button sf-button-secondary !px-3 !py-1.5"
                                                >
                                                    Restore
                                                </button>
                                            @endif
                                            @if($manager['type'] === 'belongsToMany' && $manager['canDetach'])
                                                @if($pendingDetachRelation === $manager['relationshipName'] && (string) $pendingDetachRecordId === (string) $relationRecord->getKey())
                                                    <button
                                                        type="button"
                                                        wire:click="detachBelongsToMany('{{ $manager['relationshipName'] }}', '{{ $relationRecord->getKey() }}', true)"
                                                        class="sf-button sf-button-danger !px-3 !py-1.5"
                                                    >
                                                        Confirm detach
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click="$set('pendingDetachRelation', null); $set('pendingDetachRecordId', null);"
                                                        class="sf-button sf-button-secondary !px-3 !py-1.5"
                                                    >
                                                        Cancel
                                                    </button>
                                                @else
                                                    <button
                                                        type="button"
                                                        wire:click="detachBelongsToMany('{{ $manager['relationshipName'] }}', '{{ $relationRecord->getKey() }}')"
                                                        class="sf-button sf-button-danger !px-3 !py-1.5"
                                                    >
                                                        Detach
                                                    </button>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ max(count($manager['columns']) + count($manager['pivotColumns']) + 1, 1) }}" class="sf-table-empty">
                                        No related records to display.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(method_exists($manager['records'], 'links'))
                    <div class="sf-pagination mt-3">
                        {{ $manager['records']->links() }}
                    </div>
                @endif
            </div>
        </section>
    @endforeach

    @if($relationModalState['visible'] ?? false)
        <section class="sf-card">
            <div class="sf-card-header">
                <h2 class="sf-card-title">{{ $relationModalState['title'] }}</h2>
            </div>
            <div class="sf-card-body">
                <div class="sf-form-grid">
                    @foreach($relationModalState['fields'] as $field)
                        @include('seviaflow-ui::livewire.partials.form-field', [
                            'field' => $field,
                            'context' => $relationModalMode === 'edit' ? \SeviaFlow\Core\Resources\ResourceContext::Edit : \SeviaFlow\Core\Resources\ResourceContext::Create,
                            'belongsToOptions' => $relationModalState['belongsToOptions'],
                            'belongsToPlaceholder' => [],
                            'inlineCreateAllowed' => [],
                            'modelPath' => 'relationModalValues',
                        ])
                    @endforeach
                </div>
                <div class="sf-resource-actions mt-4">
                    <button type="button" wire:click="saveRelationModal" class="sf-button sf-button-primary">Save</button>
                    <button type="button" wire:click="cancelRelationModal" class="sf-button sf-button-secondary">Cancel</button>
                </div>
            </div>
        </section>
    @endif

    @if($pivotModalState['visible'] ?? false)
        <section class="sf-card">
            <div class="sf-card-header">
                <h2 class="sf-card-title">{{ $pivotModalMode === 'edit' ? 'Edit pivot values' : 'Attach with details' }}</h2>
            </div>
            <div class="sf-card-body">
                <div class="sf-form-grid">
                    @foreach($pivotModalState['pivotFields'] as $field)
                        @include('seviaflow-ui::livewire.partials.form-field', [
                            'field' => $field,
                            'context' => \SeviaFlow\Core\Resources\ResourceContext::Create,
                            'belongsToOptions' => [],
                            'belongsToPlaceholder' => [],
                            'inlineCreateAllowed' => [],
                            'modelPath' => 'pivotModalValues',
                        ])
                    @endforeach
                </div>
                <div class="sf-resource-actions mt-4">
                    <button type="button" wire:click="savePivotModal" class="sf-button sf-button-primary">Save</button>
                    <button type="button" wire:click="cancelPivotModal" class="sf-button sf-button-secondary">Cancel</button>
                </div>
            </div>
        </section>
    @endif
</div>
