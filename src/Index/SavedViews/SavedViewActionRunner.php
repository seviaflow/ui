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
            return ['ok' => false, 'message' => __('seviaflow-ui::panel.you_must_be_signed_in_to_save_views'), 'saved_view' => null];
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

        return ['ok' => true, 'message' => __('seviaflow-ui::panel.saved_view_created_successfully'), 'saved_view' => $savedView];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function deleteOwned(?SavedView $savedView, ?Model $user): array
    {
        if (! $savedView instanceof SavedView || ! $user instanceof Model) {
            return ['ok' => false, 'message' => __('seviaflow-ui::panel.saved_view_not_found')];
        }

        if (! $this->savedViewRepository->deleteOwned($savedView, $user)) {
            return ['ok' => false, 'message' => __('seviaflow-ui::panel.saved_view_could_not_be_deleted')];
        }

        return ['ok' => true, 'message' => __('seviaflow-ui::panel.saved_view_deleted')];
    }
}
