<div class="space-y-4">
    @if($statusMessage)
        @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'success', 'message' => $statusMessage])
    @endif
    @if($errorMessage)
        @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'danger', 'message' => $errorMessage])
    @endif

    <div class="sf-card">
        <div class="sf-card-body">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-slate-900">Record details</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ $title }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
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
    </div>

    <div class="sf-card">
        <div class="sf-card-body">
            @if(count($sections) > 0)
                <div class="space-y-4">
                    @foreach($sections as $section)
                        <section class="sf-card">
                            <div class="sf-card-header">
                                <h2 class="text-sm font-semibold text-slate-900">{{ $section->label() }}</h2>
                                @if($section->getDescription())
                                    <p class="mt-1 text-xs text-slate-500">{{ $section->getDescription() }}</p>
                                @endif
                            </div>
                            <div class="sf-card-body">
                                <dl class="space-y-3">
                                    @foreach($section->getFields() as $field)
                                        <div class="grid gap-1 sm:grid-cols-3 sm:gap-3">
                                            <dt class="text-sm font-medium text-slate-600">{{ $field->getLabel() }}</dt>
                                            <dd class="text-sm text-slate-900 sm:col-span-2">{{ $formatValue($field) }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        </section>
                    @endforeach
                </div>
            @else
                <dl class="space-y-3">
                    @foreach($fields as $field)
                        <div class="grid gap-1 sm:grid-cols-3 sm:gap-3">
                            <dt class="text-sm font-medium text-slate-600">{{ $field->getLabel() }}</dt>
                            <dd class="text-sm text-slate-900 sm:col-span-2">{{ $formatValue($field) }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </div>

    @if($workflowPanel['visible'])
        <div class="sf-workflow-panel">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Workflow</h2>
                    <p class="mt-1 text-xs text-slate-500">Current process state and available transitions.</p>
                </div>
                <span class="sf-badge {{ $workflowStateColorClasses($workflowPanel['currentStateColor']) }}">
                    {{ $workflowPanel['currentStateLabel'] ?? 'Unknown' }}
                </span>
            </div>

            @if(count($workflowPanel['availableTransitions']) > 0)
                <div class="space-y-3">
                    @foreach($workflowPanel['availableTransitions'] as $transition)
                        <div class="rounded-xl border border-slate-200 bg-white p-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-medium text-slate-900">{{ $transition->getLabel() }}</p>
                                    <p class="text-xs text-slate-500">To: {{ ucfirst(str_replace('_', ' ', $transition->getToState())) }}</p>
                                </div>
                                <div class="flex items-center gap-2">
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
                <p class="text-sm text-slate-500">No transitions are currently available.</p>
            @endif

            <div class="space-y-2">
                <h3 class="text-sm font-semibold text-slate-900">Activity timeline</h3>
                <div class="sf-timeline">
                    @forelse($workflowPanel['activities'] as $activity)
                        <div class="sf-timeline-item">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm font-medium text-slate-900">
                                    {{ ucfirst(str_replace('_', ' ', $activity->transition)) }}
                                    <span class="font-normal text-slate-500">({{ $activity->from_state ?? '—' }} → {{ $activity->to_state }})</span>
                                </p>
                                <p class="text-xs text-slate-500">{{ $activity->created_at?->format('Y-m-d H:i') }}</p>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">By: {{ $workflowPanel['userLabel']($activity) }}</p>
                            @if($activity->comment)
                                <p class="mt-1 text-xs text-slate-700">Comment: {{ $activity->comment }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No workflow activity yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    @foreach($relationManagers as $manager)
        <div class="sf-card">
            <div class="sf-card-header">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-sm font-semibold text-slate-900">{{ $manager['label'] }}</h2>
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
                        <div class="flex items-center gap-2">
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
                                        <div class="flex flex-wrap items-center gap-2">
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
                    <div class="mt-3 sf-pagination">
                        {{ $manager['records']->links() }}
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    @if($relationModalState['visible'] ?? false)
        <div class="sf-card">
            <div class="sf-card-header">
                <h2 class="text-sm font-semibold text-slate-900">{{ $relationModalState['title'] }}</h2>
            </div>
            <div class="sf-card-body">
                <div class="space-y-3">
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
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="saveRelationModal" class="sf-button sf-button-primary">Save</button>
                    <button type="button" wire:click="cancelRelationModal" class="sf-button sf-button-secondary">Cancel</button>
                </div>
            </div>
        </div>
    @endif

    @if($pivotModalState['visible'] ?? false)
        <div class="sf-card">
            <div class="sf-card-header">
                <h2 class="text-sm font-semibold text-slate-900">{{ $pivotModalMode === 'edit' ? 'Edit pivot values' : 'Attach with details' }}</h2>
            </div>
            <div class="sf-card-body">
                <div class="space-y-3">
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
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="savePivotModal" class="sf-button sf-button-primary">Save</button>
                    <button type="button" wire:click="cancelPivotModal" class="sf-button sf-button-secondary">Cancel</button>
                </div>
            </div>
        </div>
    @endif
</div>
