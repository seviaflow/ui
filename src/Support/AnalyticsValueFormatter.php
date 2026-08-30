<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Support;

final class AnalyticsValueFormatter
{
    public function __construct(
        private readonly MoneyFormatter $moneyFormatter = new MoneyFormatter,
    ) {}

    /**
     * @param  array<string, mixed>  $measureMetadata
     */
    public function formatMeasure(array $measureMetadata, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $type = (string) ($measureMetadata['type'] ?? 'number');
        /** @var array<string, mixed> $format */
        $format = is_array($measureMetadata['format'] ?? null) ? $measureMetadata['format'] : [];

        if ($type === 'money') {
            return $this->moneyFormatter->formatAmount(
                currency: is_string($format['currency'] ?? null) ? $format['currency'] : 'USD',
                decimals: is_numeric($format['decimals'] ?? null) ? (int) $format['decimals'] : 2,
                value: $value,
            );
        }

        if (! is_numeric($value)) {
            return e((string) $value);
        }

        if ($type === 'integer') {
            return e(number_format((int) $value));
        }

        $decimals = is_numeric($format['decimals'] ?? null) ? (int) $format['decimals'] : 2;

        return e(number_format((float) $value, $decimals, '.', ','));
    }

    public function formatDimension(mixed $label): string
    {
        if ($label === null || $label === '') {
            return '—';
        }

        return e((string) $label);
    }
}
