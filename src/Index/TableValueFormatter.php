<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Columns\BadgeColumn;
use SeviaFlow\Core\Resources\Columns\BelongsToColumn;
use SeviaFlow\Core\Resources\Columns\BooleanColumn;
use SeviaFlow\Core\Resources\Columns\Column;
use SeviaFlow\Core\Resources\Columns\Support\BadgeRenderer;

final class TableValueFormatter
{
    public function format(Model $record, Column $column): string
    {
        if ($column instanceof BelongsToColumn) {
            $related = $record->getRelationValue($column->getRelationshipName());

            if (! $related instanceof Model) {
                return '—';
            }

            $value = $related->getAttribute($column->getTitleAttribute());

            return ($value === null || $value === '') ? '—' : e((string) $value);
        }

        $value = $record->getAttribute($column->name());

        if ($value === null || $value === '') {
            return '—';
        }

        if ($column instanceof BadgeColumn) {
            return BadgeRenderer::render($column, $value);
        }

        if ($column instanceof BooleanColumn) {
            return (bool) $value ? 'Yes' : 'No';
        }

        return e((string) $value);
    }
}
