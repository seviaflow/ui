<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Layouts;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class DashboardLayoutRepository
{
    /**
     * @param  array<int, array<string, mixed>>  $layout
     */
    public function saveForUser(?Model $user, string $dashboard, array $layout): DashboardLayout
    {
        if (! $user instanceof Model) {
            throw new InvalidArgumentException('Dashboard layouts require an authenticated user.');
        }

        $slug = $this->normalizeDashboardSlug($dashboard);

        /** @var DashboardLayout $saved */
        $saved = DashboardLayout::query()->updateOrCreate(
            [
                'dashboard' => $slug,
                'user_type' => $user->getMorphClass(),
                'user_id' => (string) $user->getKey(),
            ],
            [
                'layout' => array_values($layout),
            ]
        );

        return $saved;
    }

    public function loadForUser(string $dashboard, Model $user): ?DashboardLayout
    {
        return DashboardLayout::query()
            ->where('dashboard', $this->normalizeDashboardSlug($dashboard))
            ->where('user_type', $user->getMorphClass())
            ->where('user_id', (string) $user->getKey())
            ->first();
    }

    public function resetForUser(string $dashboard, Model $user): bool
    {
        return (bool) DashboardLayout::query()
            ->where('dashboard', $this->normalizeDashboardSlug($dashboard))
            ->where('user_type', $user->getMorphClass())
            ->where('user_id', (string) $user->getKey())
            ->delete();
    }

    private function normalizeDashboardSlug(string $dashboard): string
    {
        $slug = trim($dashboard);

        if ($slug === '') {
            throw new InvalidArgumentException('Dashboard slug cannot be empty.');
        }

        return $slug;
    }
}
