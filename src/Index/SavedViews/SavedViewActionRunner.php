<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index\SavedViews;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use SeviaFlow\Ui\SavedViews\SavedView;
use SeviaFlow\Ui\SavedViews\SavedViewRepository;

final class SavedViewActionRunner
{
    public function __construct(
        private readonly SavedViewRepository $savedViewRepository = new SavedViewRepository
    ) {}

    /**
     * @param  array<string, mixed>  $state
     * @return array{
     *   ok: bool,
     *   message: string,
     *   saved_view: SavedView|null
     * }
     */
    public function createPrivate(?Model $user, string $resourceSlug, string $name, array $state): array
    {
        if (! $user instanceof Model) {
            return ['ok' => false, 'message' => 'You must be signed in to save views.', 'saved_view' => null];
        }

        try {
            $savedView = $this->savedViewRepository->createPrivate(
                user: $user,
                resource: $resourceSlug,
                name: $name,
                state: $state,
            );
        } catch (InvalidArgumentException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage(), 'saved_view' => null];
        }

        return ['ok' => true, 'message' => 'Saved view created successfully.', 'saved_view' => $savedView];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function deleteOwned(?SavedView $savedView, ?Model $user): array
    {
        if (! $savedView instanceof SavedView || ! $user instanceof Model) {
            return ['ok' => false, 'message' => 'Saved view not found.'];
        }

        if (! $this->savedViewRepository->deleteOwned($savedView, $user)) {
            return ['ok' => false, 'message' => 'Saved view could not be deleted.'];
        }

        return ['ok' => true, 'message' => 'Saved view deleted.'];
    }
}
