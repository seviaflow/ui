<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard\Layouts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use SeviaFlow\Ui\Dashboard\Dashboard;

final class SavedDashboardLayoutStore
{
    public function __construct(
        private readonly SavedDashboardRepository $repository = new SavedDashboardRepository,
        private readonly DashboardLayoutStore $layoutStore = new DashboardLayoutStore,
    ) {}

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     * @return Collection<int, SavedDashboard>
     */
    public function listForUser(string $dashboardClass, Model $user): Collection
    {
        $this->assertDashboardClass($dashboardClass);

        return $this->repository->forDashboardAndUser($dashboardClass::slug(), $user);
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     * @param  array<int, array<string,mixed>>  $layout
     */
    public function saveAs(string $dashboardClass, Model $user, string $name, array $layout, bool $isDefault = false): SavedDashboard
    {
        $this->assertDashboardClass($dashboardClass);

        $normalizedLayout = $this->layoutStore->normalizeLayout($dashboardClass, $user, $layout);

        return $this->repository->createPrivate($user, $dashboardClass::slug(), $name, $normalizedLayout, $isDefault);
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     * @return array<int, array{widget:string,x:int,y:int,w:int,h:int,visible:bool}>
     */
    public function applySavedLayout(string $dashboardClass, Model $user, int $savedDashboardId): array
    {
        $this->assertDashboardClass($dashboardClass);

        $saved = $this->repository->findOwned($savedDashboardId, $user);
        if (! $saved instanceof SavedDashboard || $saved->dashboard !== $dashboardClass::slug()) {
            throw new InvalidArgumentException('Saved dashboard layout was not found.');
        }

        $normalized = $this->layoutStore->normalizeLayout($dashboardClass, $user, $saved->layout ?? []);
        $this->layoutStore->saveLayout($dashboardClass, $user, $normalized);

        return $normalized;
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     */
    public function deleteSavedLayout(string $dashboardClass, Model $user, int $savedDashboardId): bool
    {
        $this->assertDashboardClass($dashboardClass);

        $saved = $this->repository->findOwned($savedDashboardId, $user);
        if (! $saved instanceof SavedDashboard || $saved->dashboard !== $dashboardClass::slug()) {
            return false;
        }

        return $this->repository->deleteOwned($saved, $user);
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     */
    public function renameSavedLayout(string $dashboardClass, Model $user, int $savedDashboardId, string $name): ?SavedDashboard
    {
        $this->assertDashboardClass($dashboardClass);

        $saved = $this->repository->findOwned($savedDashboardId, $user);
        if (! $saved instanceof SavedDashboard || $saved->dashboard !== $dashboardClass::slug()) {
            return null;
        }

        return $this->repository->renameOwned($saved, $user, $name);
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     */
    public function setDefaultSavedLayout(string $dashboardClass, Model $user, int $savedDashboardId): ?SavedDashboard
    {
        $this->assertDashboardClass($dashboardClass);

        $saved = $this->repository->findOwned($savedDashboardId, $user);
        if (! $saved instanceof SavedDashboard || $saved->dashboard !== $dashboardClass::slug()) {
            return null;
        }

        return $this->repository->setDefaultOwned($saved, $user);
    }

    /**
     * @param  class-string<Dashboard>  $dashboardClass
     */
    public function defaultSavedLayout(string $dashboardClass, Model $user): ?SavedDashboard
    {
        $this->assertDashboardClass($dashboardClass);

        return $this->repository->defaultForDashboard($dashboardClass::slug(), $user);
    }

    /**
     * @param  class-string  $dashboardClass
     */
    private function assertDashboardClass(string $dashboardClass): void
    {
        if (! is_a($dashboardClass, Dashboard::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Saved dashboard layout class [%s] must extend %s.',
                $dashboardClass,
                Dashboard::class
            ));
        }
    }
}
