<div class="space-y-4">
    <div class="sf-panel">
        <p class="sf-card-kicker">Settings</p>
        <h1 class="sf-card-title">Admin users</h1>
        <p class="sf-card-description mt-1">Assign one or more admin roles to each user.</p>
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
                        <td class="sf-table-cell"><span class="sf-item-title">{{ $user->name ?? 'User #'.$user->getKey() }}</span></td>
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
