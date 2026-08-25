<div class="space-y-4">
    <div class="sf-card">
        <div class="sf-card-body">
            <h1 class="text-lg font-semibold text-slate-900">Admin users</h1>
            <p class="mt-1 text-sm text-slate-500">Assign one or more admin roles to each user.</p>
        </div>
    </div>

    <div class="sf-table-wrapper">
        <table class="sf-table">
            <thead>
                <tr>
                    <th class="sf-table-header">ID</th>
                    <th class="sf-table-header">Name</th>
                    <th class="sf-table-header">Roles</th>
                    <th class="sf-table-header">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td class="sf-table-cell">{{ $user->getKey() }}</td>
                        <td class="sf-table-cell font-medium text-slate-900">{{ $user->name ?? 'User #'.$user->getKey() }}</td>
                        <td class="sf-table-cell">{{ $assignedRoleCount($user) }}</td>
                        <td class="sf-table-cell">
                            <a href="{{ $rolesUrl($user) }}" class="sf-button sf-button-secondary !px-3 !py-1.5">Manage roles</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="sf-table-empty">No admin users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
