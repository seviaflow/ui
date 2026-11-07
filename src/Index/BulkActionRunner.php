<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SeviaFlow\Core\Resources\BulkAction;
use Throwable;

final class BulkActionRunner
{
    /**
     * @param  array<int, BulkAction>  $actions
     * @param  Collection<int, Model>  $records
     * @return array{
     *   statusMessage: ?string,
     *   errorMessage: ?string,
     *   pendingBulkActionName: ?string,
     *   shouldClearSelection: bool,
     *   shouldResetPage: bool
     * }
     */
    public function run(
        array $actions,
        string $actionName,
        Collection $records,
        array $selectedRecords,
        bool $confirmed,
        ?int $maxByConfig,
        callable $canExecuteBulkAction,
        callable $isBuiltInBulkDelete,
        callable $isBuiltInBulkRestore,
        callable $runBuiltInBulkDelete,
        callable $runBuiltInBulkRestore,
        ?string &$internalError = null,
    ): array {
        $action = $this->resolveAction($actions, $actionName);

        if (! $action instanceof BulkAction) {
            return $this->result(errorMessage: __('seviaflow-ui::panel.the_selected_bulk_action_is_not_available'));
        }

        if ($records->isEmpty()) {
            return $this->result(errorMessage: $selectedRecords === []
                ? __('seviaflow-ui::panel.select_at_least_one_record_to_run_this_bulk_action')
                : __('seviaflow-ui::panel.selected_records_are_no_longer_available_please_refresh_selection'));
        }

        $selectedCount = count(array_unique(array_map('strval', $selectedRecords)));
        if ($selectedCount !== $records->count()) {
            return $this->result(errorMessage: __('seviaflow-ui::panel.selected_records_are_no_longer_available_please_refresh_selection'));
        }

        if ($maxByConfig !== null && $records->count() > $maxByConfig) {
            return $this->result(errorMessage: __('seviaflow-ui::panel.bulk_max_records', ['max' => $maxByConfig]));
        }

        $maxByAction = $action->getMaxRecords();
        if ($maxByAction !== null && $records->count() > $maxByAction) {
            return $this->result(errorMessage: __('seviaflow-ui::panel.bulk_action_max_records', ['max' => $maxByAction]));
        }

        if ($action->shouldRequireConfirmation() && ! $confirmed) {
            return $this->result(
                errorMessage: __('seviaflow-ui::panel.bulk_confirm_records', ['count' => $records->count()]),
                pendingBulkActionName: $action->name()
            );
        }

        if (! $canExecuteBulkAction($action, $records)) {
            return $this->result(errorMessage: __('seviaflow-ui::panel.you_are_not_authorized_to_run_this_bulk_action_on_the_selected_records'));
        }

        try {
            if ($isBuiltInBulkDelete($action)) {
                $runBuiltInBulkDelete($records);
            } elseif ($isBuiltInBulkRestore($action)) {
                $runBuiltInBulkRestore($records);
            } else {
                $action->execute($records);
            }

            if ($internalError !== null) {
                return $this->result(errorMessage: $internalError);
            }

            return $this->result(
                statusMessage: $action->getSuccessMessage() ?? __('seviaflow-ui::panel.bulk_action_completed_successfully'),
                shouldClearSelection: true,
                shouldResetPage: true
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->result(errorMessage: $action->getErrorMessage() ?? __('seviaflow-ui::panel.the_bulk_action_could_not_be_completed'));
        }
    }

    /**
     * @param  array<int, BulkAction>  $actions
     */
    private function resolveAction(array $actions, string $name): ?BulkAction
    {
        foreach ($actions as $action) {
            if ($action->name() === $name) {
                return $action;
            }
        }

        return null;
    }

    private function result(
        ?string $statusMessage = null,
        ?string $errorMessage = null,
        ?string $pendingBulkActionName = null,
        bool $shouldClearSelection = false,
        bool $shouldResetPage = false,
    ): array {
        return [
            'statusMessage' => $statusMessage,
            'errorMessage' => $errorMessage,
            'pendingBulkActionName' => $pendingBulkActionName,
            'shouldClearSelection' => $shouldClearSelection,
            'shouldResetPage' => $shouldResetPage,
        ];
    }
}
