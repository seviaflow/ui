<div class="space-y-4">
    <div class="sf-panel">
        <p class="sf-card-kicker">{{ __('seviaflow-ui::panel.settings') }}</p>
        <h1 class="sf-card-title">{{ __('seviaflow-ui::panel.admin_users') }}</h1>
        <p class="sf-card-description mt-1">{{ __('seviaflow-ui::panel.assign_one_or_more_admin_roles_to_each_user') }}</p>
    </div>

    <div class="sf-table-wrapper">
        <table class="sf-table">
            <thead>
                <tr>
                    <th class="sf-table-header">ID</th>
                    <th class="sf-table-header">{{ __('seviaflow-ui::panel.name') }}</th>
                    <th class="sf-table-header">{{ __('seviaflow-ui::panel.roles') }}</th>
                    <th class="sf-table-header">{{ __('seviaflow-ui::panel.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td class="sf-table-cell">{{ $user->getKey() }}</td>
                        <td class="sf-table-cell"><span class="sf-item-title">{{ $user->name ?? __('seviaflow-ui::panel.user_number', ['id' => $user->getKey()]) }}</span></td>
                        <td class="sf-table-cell">{{ $assignedRoleCount($user) }}</td>
                        <td class="sf-table-cell">
                            <a href="{{ $rolesUrl($user) }}" class="sf-button sf-button-secondary !px-3 !py-1.5">{{ __('seviaflow-ui::panel.manage_roles') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="sf-table-empty">{{ __('seviaflow-ui::panel.no_admin_users_found') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
