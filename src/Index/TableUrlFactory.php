<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index;

use Illuminate\Database\Eloquent\Model;

final class TableUrlFactory
{
    public function createUrl(string $resourceSlug): string
    {
        return route($this->routePrefix().'resources.create', [
            'resource' => $resourceSlug,
        ]);
    }

    public function editUrl(string $resourceSlug, Model $record): string
    {
        return route($this->routePrefix().'resources.edit', [
            'resource' => $resourceSlug,
            'record' => $record->getKey(),
        ]);
    }

    public function showUrl(string $resourceSlug, Model $record): string
    {
        return route($this->routePrefix().'resources.show', [
            'resource' => $resourceSlug,
            'record' => $record->getKey(),
        ]);
    }

    private function routePrefix(): string
    {
        return config('seviaflow-ui.route_name_prefix', 'seviaflow.');
    }
}
