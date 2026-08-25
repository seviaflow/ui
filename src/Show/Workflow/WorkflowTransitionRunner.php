<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Workflow;

use Illuminate\Database\Eloquent\Model;
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
            return $this->result(errorMessage: 'The selected record could not be found.');
        }

        $workflow = $this->workflowDefinition($resourceClass);
        if (! $workflow instanceof Workflow) {
            return $this->result(errorMessage: 'Workflow is not available for this resource.');
        }

        $transition = $workflow->getTransition($transitionName);
        if (! $transition instanceof Transition) {
            return $this->result(errorMessage: 'The selected transition is not available.');
        }

        if (! $this->transitionResolver->canRun($resourceClass, $record, $transitionName, $user)) {
            return $this->result(errorMessage: 'The selected transition is not available.');
        }

        if ($transition->shouldRequireConfirmation() && ! $confirmed) {
            return $this->result(
                errorMessage: 'Please confirm this transition before continuing.',
                pendingTransitionName: $transitionName
            );
        }

        if ($transition->shouldRequireComment() && trim((string) $comment) === '') {
            return $this->result(errorMessage: 'This transition requires a comment.');
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
                statusMessage: $transition->getSuccessMessage() ?? 'Transition completed successfully.',
                clearComment: true,
                refreshRecord: true
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->result(
                errorMessage: $transition->getErrorMessage() ?? 'The transition could not be completed.'
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
