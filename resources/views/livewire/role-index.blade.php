<div class="space-y-4">
    @if($statusMessage)
        @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'success', 'message' => $statusMessage])
    @endif
    @if($errorMessage)
        @include('seviaflow-ui::livewire.partials.feedback-alert', ['type' => 'danger', 'message' => $errorMessage])
    @endif

    <div class="sf-panel">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="sf-card-kicker">{{ __('seviaflow-ui::panel.settings') }}</p>
                    <h1 class="sf-card-title">{{ __('seviaflow-ui::panel.roles') }}</h1>
                    <p class="sf-card-description mt-1">{{ __('seviaflow-ui::panel.manage_admin_roles_and_resource_permissions') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ $adminUsersUrl }}" class="sf-button sf-button-secondary">{{ __('seviaflow-ui::panel.admin_users') }}</a>
                    <a href="{{ $createUrl }}" class="sf-button sf-button-primary">{{ __('seviaflow-ui::panel.create_role') }}</a>
                </div>
            </div>
    </div>

    <div class="sf-table-wrapper">
        <table class="sf-table">
            <thead>
                <tr>
                    <th class="sf-table-header">{{ __('seviaflow-ui::panel.role') }}</th>
                    <th class="sf-table-header">{{ __('seviaflow-ui::panel.key') }}</th>
                    <th class="sf-table-header">{{ __('seviaflow-ui::panel.assignments') }}</th>
                    <th class="sf-table-header">{{ __('seviaflow-ui::panel.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                    <tr>
                        <td class="sf-table-cell"><span class="sf-item-title">{{ $role->name }}</span></td>
                        <td class="sf-table-cell">{{ $role->slug }}</td>
                        <td class="sf-table-cell">{{ $role->assignments_count }}</td>
                        <td class="sf-table-cell">
                            <div class="flex items-center gap-2">
                                <a href="{{ $editUrl($role) }}" class="sf-button sf-button-secondary !px-3 !py-1.5">{{ __('seviaflow-ui::panel.edit') }}</a>
                                <button
                                    type="button"
                                    wire:click="deleteRole('{{ $role->getKey() }}')"
                                    wire:confirm="Are you sure you want to delete this role?"
                                    class="sf-button sf-button-danger !px-3 !py-1.5"
                                >
                                    {{ __('seviaflow-ui::panel.delete') }}
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="sf-table-empty">{{ __('seviaflow-ui::panel.no_roles_created_yet') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
