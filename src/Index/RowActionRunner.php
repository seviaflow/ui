<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Action;
use Throwable;

final class RowActionRunner
{
    /**
     * @param  array<int, Action>  $actions
     * @return array{
     *   statusMessage: ?string,
     *   errorMessage: ?string,
     *   pendingActionName: ?string,
     *   pendingActionRecordId: int|string|null,
     *   shouldAbortForbidden: bool
     * }
     */
    public function run(
        array $actions,
        ?Model $record,
        int|string $recordId,
        string $actionName,
        bool $confirmed,
        bool $canExecute,
    ): array {
        $action = $this->resolveAction($actions, $actionName);

        if (! $action instanceof Action) {
            return $this->result(errorMessage: 'The selected action is not available.');
        }

        if (! $record instanceof Model) {
            return $this->result(errorMessage: 'The selected record could not be found.');
        }

        if (! $canExecute) {
            return $this->result(shouldAbortForbidden: true);
        }

        if ($action->shouldRequireConfirmation() && ! $confirmed) {
            return $this->result(
                errorMessage: 'Please confirm this action before continuing.',
                pendingActionName: $action->name(),
                pendingActionRecordId: $recordId
            );
        }

        try {
            $action->execute($record);

            return $this->result(
                statusMessage: $action->getSuccessMessage() ?? 'Action completed successfully.'
            );
        } catch (Throwable) {
            return $this->result(errorMessage: $action->getErrorMessage() ?? 'The action could not be completed.');
        }
    }

    /**
     * @param  array<int, Action>  $actions
     */
    private function resolveAction(array $actions, string $name): ?Action
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
        ?string $pendingActionName = null,
        int|string|null $pendingActionRecordId = null,
        bool $shouldAbortForbidden = false,
    ): array {
        return [
            'statusMessage' => $statusMessage,
            'errorMessage' => $errorMessage,
            'pendingActionName' => $pendingActionName,
            'pendingActionRecordId' => $pendingActionRecordId,
            'shouldAbortForbidden' => $shouldAbortForbidden,
        ];
    }
}
