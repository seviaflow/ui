<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Action;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceAuthorization;

final class ShowActionResolver
{
    /**
     * @param  class-string<resource>  $resourceClass
     * @return array<int, Action>
     */
    public function actions(string $resourceClass): array
    {
        return $resourceClass::actions();
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @return array<int, Action>
     */
    public function authorizedActions(string $resourceClass, ?Model $record, mixed $user = null): array
    {
        if (! $record instanceof Model) {
            return [];
        }

        $authorization = ResourceAuthorization::for($resourceClass);

        return array_values(array_filter(
            $this->actions($resourceClass),
            fn (Action $action): bool => $authorization->canExecuteAction($this->toAuthenticatable($user), $action, $record)
        ));
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function resolve(string $resourceClass, string $name): ?Action
    {
        foreach ($this->actions($resourceClass) as $action) {
            if ($action->name() === $name) {
                return $action;
            }
        }

        return null;
    }

    private function toAuthenticatable(mixed $user): ?Authenticatable
    {
        return $user instanceof Authenticatable ? $user : null;
    }
}
