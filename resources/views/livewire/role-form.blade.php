<div class="space-y-4">
    @if($statusMessage)
        @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'success', 'message' => $statusMessage])
    @endif

    <form wire:submit="save" class="space-y-4">
        <div class="sf-card">
            <div class="sf-card-header">
                <h2 class="sf-card-title">{{ __('seviaflow-ui::panel.role_details') }}</h2>
                <p class="sf-card-description mt-1">{{ __('seviaflow-ui::panel.define_role_identity_and_access_boundaries_for_operators') }}</p>
            </div>
            <div class="sf-card-body">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sf-field">
                        <label for="role-name" class="sf-label">{{ __('seviaflow-ui::panel.role_name') }}</label>
                        <input
                            id="role-name"
                            type="text"
                            wire:model.live.debounce.300ms="name"
                            class="sf-input"
                            placeholder="{{ __('seviaflow-ui::panel.support') }}"
                        >
                        @error('name')
                            <p class="sf-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sf-field">
                        <label for="role-slug" class="sf-label">{{ __('seviaflow-ui::panel.role_key') }}</label>
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
                <h2 class="sf-card-title">{{ __('seviaflow-ui::panel.resource_permissions') }}</h2>
                <p class="sf-card-description mt-1">{{ __('seviaflow-ui::panel.select_what_operators_with_this_role_are_allowed_to_do') }}</p>
                <p class="sf-help mt-2 text-amber-700">{{ __('seviaflow-ui::panel.role_permissions_are_combined_with_laravel_policies_both_must_allow_an_operation') }}</p>
            </div>

            <div class="sf-card-body space-y-3">
                <div class="sf-table-wrapper">
                    <table class="sf-table">
                        <thead>
                            <tr>
                                <th class="sf-table-header">{{ __('seviaflow-ui::panel.resource') }}</th>
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
                    {{ $isEditMode ? __('seviaflow-ui::panel.save_changes') : __('seviaflow-ui::panel.create_role') }}
                </button>

                <a href="{{ $cancelUrl }}" class="sf-button sf-button-secondary">
                    {{ __('seviaflow-ui::ui.actions.cancel') }}
                </a>
            </div>
        </div>
    </form>
</div>
