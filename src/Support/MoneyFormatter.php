<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Support;

use SeviaFlow\Core\Resources\Columns\MoneyColumn;
use SeviaFlow\Core\Resources\Fields\MoneyField;

final class MoneyFormatter
{
    public function formatColumn(MoneyColumn $column, mixed $value): string
    {
        return $this->format($column->getCurrency(), $column->getDecimals(), $value);
    }

    public function formatField(MoneyField $field, mixed $value): string
    {
        return $this->format($field->getCurrency(), 2, $value);
    }

    public function formatAmount(string $currency, int $decimals, mixed $value): string
    {
        return $this->format($currency, $decimals, $value);
    }

    private function format(string $currency, int $decimals, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (! is_numeric($value)) {
            return e((string) $value);
        }

        return e(sprintf(
            '%s %s',
            strtoupper($currency),
            number_format((float) $value, $decimals, '.', ',')
        ));
    }
}
