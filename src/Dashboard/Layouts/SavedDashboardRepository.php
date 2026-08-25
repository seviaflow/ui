<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Layouts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class SavedDashboardRepository
{
    /**
     * @param  array<int, array<string,mixed>>  $layout
     */
    public function createPrivate(Model $user, string $dashboard, string $name, array $layout, bool $isDefault = false): SavedDashboard
    {
        if (! Schema::hasTable('seviaflow_saved_dashboards')) {
            throw new InvalidArgumentException('Saved dashboard layouts table is not available.');
        }

        $normalizedName = trim($name);
        if ($normalizedName === '') {
            throw new InvalidArgumentException('Saved dashboard name cannot be empty.');
        }

        $slug = $this->normalizeDashboardSlug($dashboard);

        if ($isDefault) {
            $this->clearDefault($slug, $user);
        }

        /** @var SavedDashboard $saved */
        $saved = SavedDashboard::query()->create([
            'dashboard' => $slug,
            'name' => $normalizedName,
            'user_type' => $user->getMorphClass(),
            'user_id' => (string) $user->getKey(),
            'visibility' => 'private',
            'layout' => array_values($layout),
            'is_default' => $isDefault,
        ]);

        return $saved;
    }

    /**
     * @return Collection<int, SavedDashboard>
     */
    public function forDashboardAndUser(string $dashboard, Model $user): Collection
    {
        if (! Schema::hasTable('seviaflow_saved_dashboards')) {
            return collect();
        }

        return SavedDashboard::query()
            ->where('dashboard', $this->normalizeDashboardSlug($dashboard))
            ->where('visibility', 'private')
            ->where('user_type', $user->getMorphClass())
            ->where('user_id', (string) $user->getKey())
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function findOwned(int $id, Model $user): ?SavedDashboard
    {
        if (! Schema::hasTable('seviaflow_saved_dashboards')) {
            return null;
        }

        return SavedDashboard::query()
            ->whereKey($id)
            ->where('visibility', 'private')
            ->where('user_type', $user->getMorphClass())
            ->where('user_id', (string) $user->getKey())
            ->first();
    }

    public function deleteOwned(SavedDashboard $savedDashboard, Model $user): bool
    {
        if (! $this->isOwned($savedDashboard, $user)) {
            return false;
        }

        return (bool) $savedDashboard->delete();
    }

    public function renameOwned(SavedDashboard $savedDashboard, Model $user, string $name): ?SavedDashboard
    {
        if (! $this->isOwned($savedDashboard, $user)) {
            return null;
        }

        $normalizedName = trim($name);
        if ($normalizedName === '') {
            throw new InvalidArgumentException('Saved dashboard name cannot be empty.');
        }

        $savedDashboard->name = $normalizedName;
        $savedDashboard->save();

        return $savedDashboard->refresh();
    }

    public function setDefaultOwned(SavedDashboard $savedDashboard, Model $user): ?SavedDashboard
    {
        if (! $this->isOwned($savedDashboard, $user)) {
            return null;
        }

        $this->clearDefault((string) $savedDashboard->dashboard, $user);
        $savedDashboard->is_default = true;
        $savedDashboard->save();

        return $savedDashboard->refresh();
    }

    public function defaultForDashboard(string $dashboard, Model $user): ?SavedDashboard
    {
        if (! Schema::hasTable('seviaflow_saved_dashboards')) {
            return null;
        }

        return SavedDashboard::query()
            ->where('dashboard', $this->normalizeDashboardSlug($dashboard))
            ->where('visibility', 'private')
            ->where('user_type', $user->getMorphClass())
            ->where('user_id', (string) $user->getKey())
            ->where('is_default', true)
            ->first();
    }

    private function normalizeDashboardSlug(string $dashboard): string
    {
        $slug = trim($dashboard);
        if ($slug === '') {
            throw new InvalidArgumentException('Dashboard slug cannot be empty.');
        }

        return $slug;
    }

    private function clearDefault(string $dashboard, Model $user): void
    {
        SavedDashboard::query()
            ->where('dashboard', $dashboard)
            ->where('visibility', 'private')
            ->where('user_type', $user->getMorphClass())
            ->where('user_id', (string) $user->getKey())
            ->update(['is_default' => false]);
    }

    private function isOwned(SavedDashboard $savedDashboard, Model $user): bool
    {
        return $savedDashboard->visibility === 'private'
            && $savedDashboard->user_type === $user->getMorphClass()
            && (string) $savedDashboard->user_id === (string) $user->getKey();
    }
}
