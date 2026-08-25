<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use SeviaFlow\Core\Auth\Roles\AdminUserModelResolver;
use SeviaFlow\Core\Auth\Roles\RoleAssignment;
use SeviaFlow\Ui\Livewire\Concerns\AuthorizesRoleManagement;

final class AdminUserIndex extends Component
{
    use AuthorizesRoleManagement;

    /**
     * @var class-string<Model>
     */
    public string $userModel;

    public function mount(): void
    {
        $this->ensureCanManageRoleAssignments();
        $this->userModel = app(AdminUserModelResolver::class)->resolve();
    }

    public function render(): View
    {
        $modelClass = $this->userModel;
        $users = $modelClass::query()->orderBy('id')->limit(200)->get();

        return view('seviaflow-ui::livewire.admin-user-index', [
            'users' => $users,
            'rolesUrl' => fn (Model $user): string => route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'settings.admin-users.roles', ['user' => $user->getKey()]),
            'assignedRoleCount' => fn (Model $user): int => $this->assignedRoleCount($user),
        ])->layout('seviaflow-ui::components.app.layout');
    }

    private function assignedRoleCount(Model $user): int
    {
        return RoleAssignment::query()
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->getKey())
            ->count();
    }
}
