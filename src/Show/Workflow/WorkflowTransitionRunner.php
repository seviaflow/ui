<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Workflow;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Exceptions\WorkflowTransitionConflictException;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Workflows\Transition;
use SeviaFlow\Core\Resources\Workflows\Workflow;
use SeviaFlow\Core\Workflows\WorkflowTransitionExecutor;
use SeviaFlow\Core\Workflows\WorkflowTransitionResolver;
use Throwable;

final class WorkflowTransitionRunner
{
    public function __construct(
        private readonly WorkflowTransitionResolver $transitionResolver = new WorkflowTransitionResolver,
        private readonly WorkflowTransitionExecutor $transitionExecutor = new WorkflowTransitionExecutor
    ) {}

    /**
     * @param  class-string<resource>  $resourceClass
     * @return array{
     *   statusMessage: string|null,
     *   errorMessage: string|null,
     *   pendingTransitionName: string|null,
     *   clearComment: bool,
     *   refreshRecord: bool
     * }
     */
    public function run(
        string $resourceClass,
        ?Model $record,
        string $transitionName,
        bool $confirmed = false,
        ?string $comment = null,
        mixed $user = null
    ): array {
        if (! $record instanceof Model) {
            return $this->result(errorMessage: __('seviaflow-ui::panel.the_selected_record_could_not_be_found'));
        }

        $workflow = $this->workflowDefinition($resourceClass);
        if (! $workflow instanceof Workflow) {
            return $this->result(errorMessage: __('seviaflow-ui::panel.workflow_is_not_available_for_this_resource'));
        }

        $transition = $workflow->getTransition($transitionName);
        if (! $transition instanceof Transition) {
            return $this->result(errorMessage: __('seviaflow-ui::panel.the_selected_transition_is_not_available'));
        }

        if (! $this->transitionResolver->canRun($resourceClass, $record, $transitionName, $user)) {
            return $this->result(errorMessage: __('seviaflow-ui::panel.the_selected_transition_is_not_available'));
        }

        if ($transition->shouldRequireConfirmation() && ! $confirmed) {
            return $this->result(
                errorMessage: __('seviaflow-ui::panel.please_confirm_this_transition_before_continuing'),
                pendingTransitionName: $transitionName
            );
        }

        if ($transition->shouldRequireComment() && trim((string) $comment) === '') {
            return $this->result(errorMessage: __('seviaflow-ui::panel.this_transition_requires_a_comment'));
        }

        try {
            $this->transitionExecutor->execute(
                resourceClass: $resourceClass,
                record: $record,
                transitionName: $transitionName,
                user: $user,
                comment: $comment,
                confirmed: $confirmed
            );

            return $this->result(
                statusMessage: $transition->getSuccessMessage() ?? __('seviaflow-ui::panel.transition_completed_successfully'),
                clearComment: true,
                refreshRecord: true
            );
        } catch (WorkflowTransitionConflictException $exception) {
            return $this->result(errorMessage: $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->result(
                errorMessage: $transition->getErrorMessage() ?? __('seviaflow-ui::panel.the_transition_could_not_be_completed')
            );
        }
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function workflowDefinition(string $resourceClass): ?Workflow
    {
        $workflow = $resourceClass::workflow(new Workflow);

        if ($workflow->getStates() === [] || $workflow->getTransitions() === []) {
            return null;
        }

        return $workflow;
    }

    /**
     * @return array{
     *   statusMessage: string|null,
     *   errorMessage: string|null,
     *   pendingTransitionName: string|null,
     *   clearComment: bool,
     *   refreshRecord: bool
     * }
     */
    private function result(
        ?string $statusMessage = null,
        ?string $errorMessage = null,
        ?string $pendingTransitionName = null,
        bool $clearComment = false,
        bool $refreshRecord = false
    ): array {
        return [
            'statusMessage' => $statusMessage,
            'errorMessage' => $errorMessage,
            'pendingTransitionName' => $pendingTransitionName,
            'clearComment' => $clearComment,
            'refreshRecord' => $refreshRecord,
        ];
    }
}
