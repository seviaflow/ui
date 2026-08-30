<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use SeviaFlow\Core\Auth\Roles\AdminUserModelResolver;
use SeviaFlow\Core\Auth\Roles\Role;
use SeviaFlow\Core\Auth\Roles\RoleAssignmentService;
use SeviaFlow\Ui\Livewire\Concerns\AuthorizesRoleManagement;

final class AdminUserRolesForm extends Component
{
    use AuthorizesRoleManagement;

    /**
     * @var class-string<Model>
     */
    public string $userModel;

    public ?Model $userRecord = null;

    /**
     * @var array<int, int>
     */
    public array $selectedRoleIds = [];

    public ?string $statusMessage = null;

    public function mount(int|string $user): void
    {
        $this->ensureCanManageRoleAssignments();
        $this->userModel = app(AdminUserModelResolver::class)->resolve();
        $modelClass = $this->userModel;
        $this->userRecord = $modelClass::query()->find($user);

        if (! $this->userRecord instanceof Model) {
            abort(404);
        }

        $this->selectedRoleIds = $this->assignmentService()->roleIdsFor($this->userRecord);
    }

    public function render(): View
    {
        return view('seviaflow-ui::livewire.admin-user-roles-form', [
            'roles' => Role::query()->orderBy('name')->get(),
            'backUrl' => route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'settings.admin-users.index'),
        ])->layout('seviaflow-ui::components.app.layout', [
            'contentWidth' => 'standard',
        ]);
    }

    public function save(): void
    {
        $this->ensureCanManageRoleAssignments();

        if (! $this->userRecord instanceof Model) {
            abort(404);
        }

        $roleIds = array_values(array_map(static fn (int|string $value): int => (int) $value, $this->selectedRoleIds));
        $this->assignmentService()->syncRoles($this->userRecord, $roleIds);
        $this->selectedRoleIds = $this->assignmentService()->roleIdsFor($this->userRecord);
        $this->statusMessage = 'Roles updated successfully.';
    }

    private function assignmentService(): RoleAssignmentService
    {
        return app(RoleAssignmentService::class);
    }
}
