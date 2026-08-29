<div class="space-y-4">
    @if($statusMessage)
        @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'success', 'message' => $statusMessage])
    @endif

    <div class="sf-panel">
        <p class="sf-card-kicker">Settings</p>
        <h1 class="sf-card-title">Assign roles</h1>
        <p class="sf-card-description mt-1">User: {{ $userRecord?->name ?? 'User #'.$userRecord?->getKey() }}</p>
    </div>

    <form wire:submit="save" class="space-y-4">
        <div class="sf-card">
            <div class="sf-card-body">
                <div class="space-y-2">
                    @forelse($roles as $role)
                        <label for="user-role-{{ $role->getKey() }}" class="sf-profile-option flex items-start gap-3">
                            <input
                                id="user-role-{{ $role->getKey() }}"
                                type="checkbox"
                                wire:model.live="selectedRoleIds"
                                value="{{ $role->getKey() }}"
                                class="sf-checkbox mt-1"
                            >
                            <span class="block">
                                <span class="block sf-item-title">{{ $role->name }}</span>
                                <span class="block sf-item-meta">{{ $role->slug }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="sf-table-empty">No roles available. Create a role first.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="sf-form-actions">
            <div class="sf-resource-actions">
                <button type="submit" class="sf-button sf-button-primary">Save roles</button>
                <a href="{{ $backUrl }}" class="sf-button sf-button-secondary">Back</a>
            </div>
        </div>
    </form>
</div>
