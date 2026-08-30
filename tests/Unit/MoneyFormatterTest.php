<?php

declare(strict_types=1);

use SeviaFlow\Core\Resources\Columns\MoneyColumn;
use SeviaFlow\Core\Resources\Fields\MoneyField;
use SeviaFlow\Ui\Support\MoneyFormatter;

it('formats money columns and fields consistently', function (): void {
    $formatter = new MoneyFormatter;

    expect($formatter->formatColumn(MoneyColumn::make('total')->currency('EUR'), '1234.5'))
        ->toBe('EUR 1,234.50')
        ->and($formatter->formatField(MoneyField::make('credit_limit')->currency('EUR'), '240000.00'))
        ->toBe('EUR 240,000.00')
        ->and($formatter->formatColumn(MoneyColumn::make('total')->currency('EUR'), null))
        ->toBe('—');
});
