<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use SeviaFlow\Core\Auth\Permissions\PermissionAbilities;
use SeviaFlow\Core\Auth\Roles\Permission;
use SeviaFlow\Core\Auth\Roles\Role;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Ui\Livewire\Concerns\AuthorizesRoleManagement;

final class RoleForm extends Component
{
    use AuthorizesRoleManagement;

    public ?Role $editingRole = null;

    public string $name = '';

    public string $slug = '';

    /**
     * @var array<string, array<string, bool>>
     */
    public array $permissions = [];

    public ?string $statusMessage = null;

    public function mount(int|string|null $role = null): void
    {
        $this->ensureCanManageRoles();

        if ($role !== null) {
            $this->editingRole = Role::query()->with('permissions')->find($role);

            if (! $this->editingRole instanceof Role) {
                abort(404);
            }

            $this->name = $this->editingRole->name;
            $this->slug = $this->editingRole->slug;
            $this->permissions = $this->emptyPermissionState();

            /** @var Collection<int, Permission> $permissions */
            $permissions = $this->editingRole->permissions()->get();

            foreach ($permissions as $permission) {
                if ($permission->allowed && isset($this->permissions[$permission->resource][$permission->ability])) {
                    $this->permissions[$permission->resource][$permission->ability] = true;
                }
            }

            return;
        }

        $this->permissions = $this->emptyPermissionState();
    }

    public function render(): View
    {
        return view('seviaflow-ui::livewire.role-form', [
            'isEditMode' => $this->editingRole instanceof Role,
            'permissionMatrix' => $this->permissionMatrix(),
            'abilityLabels' => $this->abilityLabels(),
            'supportsRestore' => fn (string $resource): bool => $this->supportsSoftDeletes($resource),
            'cancelUrl' => route(config('seviaflow-ui.route_name_prefix', 'seviaflow.').'settings.roles.index'),
        ])->layout('seviaflow-ui::components.app.layout');
    }

    public function updatedName(string $value): void
    {
        if ($this->slug === '' || $this->slug === Str::slug($this->editingRole?->name ?? '')) {
            $this->slug = Str::slug($value);
        }
    }

    public function save(): void
    {
        $this->ensureCanManageRoles();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('seviaflow_roles', 'slug')
                    ->ignore($this->editingRole?->getKey()),
            ],
        ]);

        $role = $this->editingRole ?? new Role;
        $role->fill([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
        ]);
        $role->save();

        $role->permissions()->delete();

        foreach ($this->permissions as $resource => $abilities) {
            foreach ($abilities as $ability => $allowed) {
                if (! $allowed) {
                    continue;
                }

                $role->permissions()->create([
                    'resource' => $resource,
                    'ability' => $ability,
                    'allowed' => true,
                ]);
            }
        }

        $this->editingRole = $role->fresh('permissions');
        $this->statusMessage = 'Role saved successfully.';
    }

    /**
     * @return array<string, string>
     */
    private function abilityLabels(): array
    {
        return [
            PermissionAbilities::VIEW_ANY => 'View list',
            PermissionAbilities::VIEW => 'View details',
            PermissionAbilities::CREATE => 'Create',
            PermissionAbilities::UPDATE => 'Edit',
            PermissionAbilities::DELETE => 'Delete',
            PermissionAbilities::RESTORE => 'Restore',
            PermissionAbilities::FORCE_DELETE => 'Permanently delete',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function configuredResources(): array
    {
        /** @var array<string, class-string<resource>> $resources */
        $resources = (array) config('seviaflow.resources', []);

        $labels = [];

        foreach ($resources as $slug => $resourceClass) {
            $labels[$slug] = Str::of($slug)->replace('-', ' ')->headline()->toString();
        }

        return $labels;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function emptyPermissionState(): array
    {
        $state = [];

        foreach (array_keys($this->configuredResources()) as $resource) {
            $state[$resource] = [
                PermissionAbilities::VIEW_ANY => false,
                PermissionAbilities::VIEW => false,
                PermissionAbilities::CREATE => false,
                PermissionAbilities::UPDATE => false,
                PermissionAbilities::DELETE => false,
                PermissionAbilities::RESTORE => false,
                PermissionAbilities::FORCE_DELETE => false,
            ];
        }

        return $state;
    }

    /**
     * @return array<int, array{slug: string, label: string}>
     */
    private function permissionMatrix(): array
    {
        $matrix = [];

        foreach ($this->configuredResources() as $slug => $label) {
            $matrix[] = [
                'slug' => $slug,
                'label' => $label,
            ];
        }

        return $matrix;
    }

    private function supportsSoftDeletes(string $resource): bool
    {
        /** @var array<string, class-string<resource>> $resources */
        $resources = (array) config('seviaflow.resources', []);
        $resourceClass = $resources[$resource] ?? null;

        if (! is_string($resourceClass) || ! is_subclass_of($resourceClass, Resource::class)) {
            return false;
        }

        $modelClass = $resourceClass::model();
        $uses = class_uses_recursive($modelClass);

        return in_array(SoftDeletes::class, $uses, true);
    }
}
