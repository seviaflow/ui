<div class="space-y-4">
    @if($statusMessage)
        @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'success', 'message' => $statusMessage])
    @endif

    <div class="sf-card">
        <div class="sf-card-body">
            <h1 class="text-lg font-semibold text-slate-900">Assign roles</h1>
            <p class="mt-1 text-sm text-slate-500">User: {{ $userRecord?->name ?? 'User #'.$userRecord?->getKey() }}</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-4">
        <div class="sf-card">
            <div class="sf-card-body">
                <div class="space-y-2">
                    @forelse($roles as $role)
                        <label for="user-role-{{ $role->getKey() }}" class="flex items-start gap-3 rounded-xl border border-slate-200 px-3 py-2 hover:bg-slate-50">
                            <input
                                id="user-role-{{ $role->getKey() }}"
                                type="checkbox"
                                wire:model.live="selectedRoleIds"
                                value="{{ $role->getKey() }}"
                                class="sf-checkbox mt-1"
                            >
                            <span class="block">
                                <span class="block text-sm font-medium text-slate-900">{{ $role->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $role->slug }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="sf-table-empty">No roles available. Create a role first.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="sf-card">
            <div class="sf-card-body">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="sf-button sf-button-primary">Save roles</button>
                    <a href="{{ $backUrl }}" class="sf-button sf-button-secondary">Back</a>
                </div>
            </div>
        </div>
    </form>
</div>
