<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index\Export;

final class ExportRequestValidator
{
    /**
     * @param  array<int, string>  $selectedExportFields
     * @param  array<int, string>  $allowedFieldNames
     * @param  array<int, string>  $allowedFormats
     * @param  array<int, string>  $selectedRecords
     * @return array{
     *   ok: bool,
     *   message: string|null,
     *   selected_fields: array<int, string>
     * }
     */
    public function validate(
        array $selectedExportFields,
        array $allowedFieldNames,
        array $allowedFormats,
        string $selectedExportFormat,
        string $exportScope,
        array $selectedRecords
    ): array {
        $allowedLookup = array_fill_keys($allowedFieldNames, true);
        $selected = [];

        foreach ($selectedExportFields as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized === '') {
                continue;
            }

            $selected[] = $normalized;
        }

        $selected = array_values(array_unique($selected));

        if ($selected === []) {
            return ['ok' => false, 'message' => 'Select at least one export field.', 'selected_fields' => []];
        }

        foreach ($selected as $fieldName) {
            if (! isset($allowedLookup[$fieldName])) {
                return ['ok' => false, 'message' => 'One or more selected export fields are not allowed.', 'selected_fields' => []];
            }
        }

        if (! in_array($selectedExportFormat, $allowedFormats, true)) {
            return ['ok' => false, 'message' => 'Selected export format is not allowed.', 'selected_fields' => []];
        }

        if (! in_array($exportScope, ['current_view', 'selected_rows'], true)) {
            return ['ok' => false, 'message' => 'Selected export scope is invalid.', 'selected_fields' => []];
        }

        if ($exportScope === 'selected_rows' && $selectedRecords === []) {
            return ['ok' => false, 'message' => 'Select at least one row to export selected rows.', 'selected_fields' => []];
        }

        return ['ok' => true, 'message' => null, 'selected_fields' => $selected];
    }
}
