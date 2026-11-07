<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use SeviaFlow\Core\Auth\Roles\Role;
use SeviaFlow\Ui\Livewire\Concerns\AuthorizesRoleManagement;
use SeviaFlow\Ui\Support\Cache\CacheInvalidator;

final class RoleIndex extends Component
{
    use AuthorizesRoleManagement;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->ensureCanManageRoles();
    }

    public function render(): View
    {
        return view('seviaflow-ui::livewire.role-index', [
            'roles' => Role::query()->withCount('assignments')->orderBy('name')->get(),
            'createUrl' => route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'settings.roles.create'),
            'editUrl' => fn (Role $role): string => route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'settings.roles.edit', ['role' => $role->getKey()]),
            'adminUsersUrl' => route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'settings.admin-users.index'),
        ])->layout('seviaflow-ui::components.app.layout', [
            'contentWidth' => 'standard',
        ]);
    }

    public function deleteRole(int|string $roleId): void
    {
        $this->ensureCanManageRoles();
        $this->statusMessage = null;
        $this->errorMessage = null;

        $role = Role::query()->withCount('assignments')->find($roleId);

        if (! $role instanceof Role) {
            $this->errorMessage = __('seviaflow-ui::panel.the_selected_role_could_not_be_found');

            return;
        }

        if ($role->assignments_count > 0) {
            $this->errorMessage = __('seviaflow-ui::panel.this_role_is_assigned_to_users_and_cannot_be_deleted');

            return;
        }

        $connection = $role->getConnection();

        $connection->transaction(function () use ($role): void {
            $role->permissions()->delete();
            $role->delete();
        });
        app(CacheInvalidator::class)->permissionsChanged(connection: $connection);
        $this->statusMessage = __('seviaflow-ui::panel.role_deleted_successfully');
    }
}
