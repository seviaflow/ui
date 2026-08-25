<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Livewire\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use SeviaFlow\Core\Auth\Permissions\PermissionChecker;

trait AuthorizesRoleManagement
{
    private function ensureCanManageRoles(): void
    {
        $this->ensureSettingsPermission(
            (string) config('seviaflow.permissions.role_management.resource', '__settings.roles'),
            (string) config('seviaflow.permissions.role_management.ability', 'update')
        );
    }

    private function ensureCanManageRoleAssignments(): void
    {
        $this->ensureSettingsPermission(
            (string) config('seviaflow.permissions.role_assignments.resource', '__settings.admin-users'),
            (string) config('seviaflow.permissions.role_assignments.ability', 'update')
        );
    }

    private function ensureSettingsPermission(string $resource, string $ability): void
    {
        if (! (bool) config('seviaflow.permissions.enabled', false)) {
            return;
        }

        $actor = Auth::user();

        if (! $actor instanceof Authenticatable || ! $actor instanceof Model) {
            abort(403);
        }

        if (! app(PermissionChecker::class)->allows($actor, $resource, $ability)) {
            abort(403);
        }
    }
}
