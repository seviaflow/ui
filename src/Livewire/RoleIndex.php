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
            $this->errorMessage = 'The selected role could not be found.';

            return;
        }

        if ($role->assignments_count > 0) {
            $this->errorMessage = 'This role is assigned to users and cannot be deleted.';

            return;
        }

        $connection = $role->getConnection();

        $connection->transaction(function () use ($role): void {
            $role->permissions()->delete();
            $role->delete();
        });
        app(CacheInvalidator::class)->permissionsChanged(connection: $connection);
        $this->statusMessage = 'Role deleted successfully.';
    }
}
