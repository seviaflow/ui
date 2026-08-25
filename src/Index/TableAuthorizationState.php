<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Action;
use SeviaFlow\Core\Resources\ResourceAuthorization;

final class TableAuthorizationState
{
    public function __construct(
        private readonly ResourceAuthorization $authorization,
        private readonly bool $supportsSoftDeletes,
    ) {}

    public function canCreate(): bool
    {
        return $this->authorization->canCreate();
    }

    public function canView(Model $record): bool
    {
        return $this->authorization->canView(null, $record);
    }

    public function canUpdate(Model $record): bool
    {
        return $this->authorization->canUpdate(null, $record);
    }

    public function canDelete(Model $record): bool
    {
        return $this->authorization->canDelete(null, $record);
    }

    public function canExecuteAction(Action $action, Model $record): bool
    {
        return $this->authorization->canExecuteAction(null, $action, $record);
    }

    public function canRestore(Model $record): bool
    {
        if (! $this->supportsSoftDeletes) {
            return false;
        }

        if (! method_exists($record, 'trashed') || ! $record->trashed()) {
            return false;
        }

        return $this->authorization->canRestore(null, $record);
    }
}
