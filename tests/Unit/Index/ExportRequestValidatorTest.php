<?php

declare(strict_types=1);

use SeviaFlow\Ui\Index\Export\ExportRequestValidator;

it('validates export request and normalizes selected fields', function (): void {
    $result = (new ExportRequestValidator)->validate(
        selectedExportFields: [' id ', 'name', 'name'],
        allowedFieldNames: ['id', 'name', 'email'],
        allowedFormats: ['csv'],
        selectedExportFormat: 'csv',
        exportScope: 'current_view',
        selectedRecords: [],
    );

    expect($result['ok'])->toBeTrue()
        ->and($result['selected_fields'])->toBe(['id', 'name']);
});

it('rejects disallowed export field selections', function (): void {
    $result = (new ExportRequestValidator)->validate(
        selectedExportFields: ['id', 'status'],
        allowedFieldNames: ['id', 'name'],
        allowedFormats: ['csv'],
        selectedExportFormat: 'csv',
        exportScope: 'current_view',
        selectedRecords: [],
    );

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toBe('One or more selected export fields are not allowed.');
});

it('rejects selected rows export without records', function (): void {
    $result = (new ExportRequestValidator)->validate(
        selectedExportFields: ['id'],
        allowedFieldNames: ['id', 'name'],
        allowedFormats: ['csv'],
        selectedExportFormat: 'csv',
        exportScope: 'selected_rows',
        selectedRecords: [],
    );

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toBe('Select at least one row to export selected rows.');
});
