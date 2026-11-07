<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Action;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceAuthorization;
use Throwable;

final class ShowActionRunner
{
    public function __construct(
        private readonly ShowActionResolver $resolver = new ShowActionResolver
    ) {}

    /**
     * @param  class-string<resource>  $resourceClass
     * @return array{
     *   statusMessage: string|null,
     *   errorMessage: string|null,
     *   pendingActionName: string|null,
     *   refreshRecord: bool
     * }
     */
    public function run(
        string $resourceClass,
        ?Model $record,
        string $actionName,
        bool $confirmed = false,
        ?Authenticatable $user = null
    ): array {
        $action = $this->resolver->resolve($resourceClass, $actionName);

        if (! $action instanceof Action) {
            return $this->result(errorMessage: __('seviaflow-ui::panel.the_selected_action_is_not_available'));
        }

        if (! $record instanceof Model) {
            return $this->result(errorMessage: __('seviaflow-ui::panel.the_selected_record_could_not_be_found'));
        }

        if (! ResourceAuthorization::for($resourceClass)->canExecuteAction($user, $action, $record)) {
            return $this->result(errorMessage: 'forbidden');
        }

        if ($action->shouldRequireConfirmation() && ! $confirmed) {
            return $this->result(
                errorMessage: __('seviaflow-ui::panel.please_confirm_this_action_before_continuing'),
                pendingActionName: $action->name()
            );
        }

        try {
            $action->execute($record);

            return $this->result(
                statusMessage: $action->getSuccessMessage() ?? __('seviaflow-ui::panel.action_completed_successfully'),
                pendingActionName: null,
                refreshRecord: true
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->result(errorMessage: $action->getErrorMessage() ?? __('seviaflow-ui::panel.the_action_could_not_be_completed'));
        }
    }

    /**
     * @return array{
     *   statusMessage: string|null,
     *   errorMessage: string|null,
     *   pendingActionName: string|null,
     *   refreshRecord: bool
     * }
     */
    private function result(
        ?string $statusMessage = null,
        ?string $errorMessage = null,
        ?string $pendingActionName = null,
        bool $refreshRecord = false
    ): array {
        return [
            'statusMessage' => $statusMessage,
            'errorMessage' => $errorMessage,
            'pendingActionName' => $pendingActionName,
            'refreshRecord' => $refreshRecord,
        ];
    }
}
