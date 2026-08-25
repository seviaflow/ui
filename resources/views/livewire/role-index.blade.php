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
                    <h1 class="text-lg font-semibold text-slate-900">Roles</h1>
                    <p class="mt-1 text-sm text-slate-500">Manage admin roles and resource permissions.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ $adminUsersUrl }}" class="sf-button sf-button-secondary">Admin users</a>
                    <a href="{{ $createUrl }}" class="sf-button sf-button-primary">Create role</a>
                </div>
            </div>
        </div>
    </div>

    <div class="sf-table-wrapper">
        <table class="sf-table">
            <thead>
                <tr>
                    <th class="sf-table-header">Role</th>
                    <th class="sf-table-header">Key</th>
                    <th class="sf-table-header">Assignments</th>
                    <th class="sf-table-header">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                    <tr>
                        <td class="sf-table-cell font-medium text-slate-900">{{ $role->name }}</td>
                        <td class="sf-table-cell">{{ $role->slug }}</td>
                        <td class="sf-table-cell">{{ $role->assignments_count }}</td>
                        <td class="sf-table-cell">
                            <div class="flex items-center gap-2">
                                <a href="{{ $editUrl($role) }}" class="sf-button sf-button-secondary !px-3 !py-1.5">Edit</a>
                                <button
                                    type="button"
                                    wire:click="deleteRole('{{ $role->getKey() }}')"
                                    wire:confirm="Are you sure you want to delete this role?"
                                    class="sf-button sf-button-danger !px-3 !py-1.5"
                                >
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="sf-table-empty">No roles created yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
