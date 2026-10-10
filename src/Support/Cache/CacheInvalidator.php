<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Support\Cache;

use Illuminate\Database\Connection;
use SeviaFlow\Core\Events\Auth\RoleAssignmentsChanged;
use SeviaFlow\Core\Events\Resources\ResourceLifecycleEvent;

final class CacheInvalidator
{
    public function __construct(
        private readonly CacheContext $context,
        private readonly CacheVersionStore $versions,
    ) {}

    public function resourceChanged(ResourceLifecycleEvent $event): void
    {
        $eventContext = $event->context;
        $context = $eventContext->metadata();
        if ($eventContext->connection() !== null) {
            $context['connection'] = $eventContext->connection();
        }

        $this->dataChanged($context);
    }

    public function roleAssignmentsChanged(RoleAssignmentsChanged $event): void
    {
        $this->versions->bumpPermission(
            $event->user,
            $this->context->scope($event->context, $event->context['connection'] ?? null),
        );
    }

    /**
     * Invalidates all permission-sensitive cache entries in one scope.
     * Call this after a role's permission matrix is committed.
     *
     * @param  array<string, mixed>  $context
     */
    public function permissionsChanged(array $context = [], ?Connection $connection = null): void
    {
        $invalidate = function () use ($context): void {
            $this->versions->bumpPermissionScope($this->context->scope(
                $context,
                isset($context['connection']) && is_scalar($context['connection'])
                    ? (string) $context['connection']
                    : null,
            ));
        };

        if ($connection === null || $connection->transactionLevel() === 0) {
            $invalidate();

            return;
        }

        $connection->afterCommit($invalidate);
    }

    /**
     * Public integration hook for writes that do not use a SeviaFlow resource operation.
     * The application must pass the same explicit tenant/connection scope as dashboard reads.
     *
     * @param  array<string, mixed>  $context
     */
    public function dataChanged(array $context = []): void
    {
        $this->versions->bumpData($this->context->scope(
            $context,
            isset($context['connection']) && is_scalar($context['connection'])
                ? (string) $context['connection']
                : null,
        ));
    }
}
