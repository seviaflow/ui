<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Columns\BadgeColumn;
use SeviaFlow\Core\Resources\Columns\BelongsToColumn;
use SeviaFlow\Core\Resources\Columns\BooleanColumn;
use SeviaFlow\Core\Resources\Columns\Column;
use SeviaFlow\Core\Resources\Columns\MoneyColumn;
use SeviaFlow\Core\Resources\Columns\Support\BadgeRenderer;
use SeviaFlow\Core\Support\SensitiveDataPolicy;
use SeviaFlow\Ui\Support\MoneyFormatter;

final class TableValueFormatter
{
    public function format(Model $record, Column $column): string
    {
        if (SensitiveDataPolicy::isSensitive($column->name())) {
            return '—';
        }

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

        if (is_array($value)) {
            return $this->formatComplexValue($value);
        }

        if ($column instanceof BadgeColumn) {
            return BadgeRenderer::render($column, $value);
        }

        if ($column instanceof BooleanColumn) {
            return (bool) $value ? 'Yes' : 'No';
        }

        if ($column instanceof MoneyColumn) {
            return (new MoneyFormatter)->formatColumn($column, $value);
        }

        return e((string) $value);
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private function formatComplexValue(array $value): string
    {
        if ($value === []) {
            return '—';
        }

        if (array_is_list($value)) {
            $parts = array_map(fn (mixed $item): string => $this->complexItemSummary($item), $value);

            return e(implode(', ', $parts));
        }

        $parts = [];
        foreach ($value as $key => $item) {
            $parts[] = (string) $key.': '.$this->complexItemSummary($item);
        }

        return e(implode(', ', $parts));
    }

    private function complexItemSummary(mixed $value, int $depth = 0): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        if (! is_array($value) || $depth >= 2) {
            return '[complex]';
        }

        if (array_is_list($value)) {
            return implode(', ', array_map(
                fn (mixed $item): string => $this->complexItemSummary($item, $depth + 1),
                $value
            ));
        }

        $parts = [];
        foreach ($value as $key => $item) {
            $parts[] = (string) $key.': '.$this->complexItemSummary($item, $depth + 1);
        }

        return implode(', ', $parts);
    }
}
