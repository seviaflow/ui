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
            return $this->result(errorMessage: 'The selected bulk action is not available.');
        }

        if ($records->isEmpty()) {
            return $this->result(errorMessage: $selectedRecords === []
                ? 'Select at least one record to run this bulk action.'
                : 'Selected records are no longer available. Please refresh selection.');
        }

        $selectedCount = count(array_unique(array_map('strval', $selectedRecords)));
        if ($selectedCount !== $records->count()) {
            return $this->result(errorMessage: 'Selected records are no longer available. Please refresh selection.');
        }

        if ($maxByConfig !== null && $records->count() > $maxByConfig) {
            return $this->result(errorMessage: sprintf('You can select up to %d records for bulk actions.', $maxByConfig));
        }

        $maxByAction = $action->getMaxRecords();
        if ($maxByAction !== null && $records->count() > $maxByAction) {
            return $this->result(errorMessage: sprintf('This bulk action allows up to %d selected records.', $maxByAction));
        }

        if ($action->shouldRequireConfirmation() && ! $confirmed) {
            return $this->result(
                errorMessage: sprintf(
                    'You are about to run this action on %d records. Please confirm before continuing.',
                    $records->count()
                ),
                pendingBulkActionName: $action->name()
            );
        }

        if (! $canExecuteBulkAction($action, $records)) {
            return $this->result(errorMessage: 'You are not authorized to run this bulk action on the selected records.');
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
                statusMessage: $action->getSuccessMessage() ?? 'Bulk action completed successfully.',
                shouldClearSelection: true,
                shouldResetPage: true
            );
        } catch (Throwable) {
            return $this->result(errorMessage: $action->getErrorMessage() ?? 'The bulk action could not be completed.');
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
