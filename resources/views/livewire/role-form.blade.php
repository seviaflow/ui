<div class="space-y-4">
    @if($statusMessage)
        @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'success', 'message' => $statusMessage])
    @endif

    <form wire:submit="save" class="space-y-4">
        <div class="sf-card">
            <div class="sf-card-header">
                <h2 class="sf-card-title">Role details</h2>
                <p class="sf-card-description mt-1">Define role identity and access boundaries for operators.</p>
            </div>
            <div class="sf-card-body">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sf-field">
                        <label for="role-name" class="sf-label">Role name</label>
                        <input
                            id="role-name"
                            type="text"
                            wire:model.live.debounce.300ms="name"
                            class="sf-input"
                            placeholder="Support"
                        >
                        @error('name')
                            <p class="sf-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sf-field">
                        <label for="role-slug" class="sf-label">Role key</label>
                        <input
                            id="role-slug"
                            type="text"
                            wire:model.live.debounce.300ms="slug"
                            class="sf-input"
                            placeholder="support"
                        >
                        @error('slug')
                            <p class="sf-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <section class="sf-card">
            <div class="sf-card-header">
                <h2 class="sf-card-title">Resource permissions</h2>
                <p class="sf-card-description mt-1">Select what operators with this role are allowed to do.</p>
                <p class="sf-help mt-2 text-amber-700">Role permissions are combined with Laravel policies. Both must allow an operation.</p>
            </div>

            <div class="sf-card-body space-y-3">
                <div class="sf-table-wrapper">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th class="sf-table-header">Resource</th>
                                @foreach($abilityLabels as $ability => $label)
                                    <th class="sf-table-header">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($permissionMatrix as $entry)
                                <tr>
                                    <td class="sf-table-cell">
                                        <p class="sf-item-title">{{ $entry['label'] }}</p>
                                        <p class="sf-item-meta">{{ $entry['slug'] }}</p>
                                    </td>
                                    @foreach($abilityLabels as $ability => $label)
                                        <td class="sf-table-cell">
                                            @if(($ability === 'restore' || $ability === 'forceDelete') && ! $supportsRestore($entry['slug']))
                                                <span class="sf-muted">—</span>
                                            @else
                                                <label class="sr-only" for="permission-{{ $entry['slug'] }}-{{ $ability }}">{{ $entry['label'] }} {{ $label }}</label>
                                                <input
                                                    id="permission-{{ $entry['slug'] }}-{{ $ability }}"
                                                    type="checkbox"
                                                    wire:model.live="permissions.{{ $entry['slug'] }}.{{ $ability }}"
                                                    class="sf-checkbox"
                                                >
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="sf-form-actions">
            <div class="sf-resource-actions">
                <button type="submit" class="sf-button sf-button-primary">
                    {{ $isEditMode ? 'Save changes' : 'Create role' }}
                </button>

                <a href="{{ $cancelUrl }}" class="sf-button sf-button-secondary">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>
