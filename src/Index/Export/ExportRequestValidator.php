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
            return ['ok' => false, 'message' => __('seviaflow-ui::panel.select_at_least_one_export_field'), 'selected_fields' => []];
        }

        foreach ($selected as $fieldName) {
            if (! isset($allowedLookup[$fieldName])) {
                return ['ok' => false, 'message' => __('seviaflow-ui::panel.one_or_more_selected_export_fields_are_not_allowed'), 'selected_fields' => []];
            }
        }

        if (! in_array($selectedExportFormat, $allowedFormats, true)) {
            return ['ok' => false, 'message' => __('seviaflow-ui::panel.selected_export_format_is_not_allowed'), 'selected_fields' => []];
        }

        if (! in_array($exportScope, ['current_view', 'selected_rows'], true)) {
            return ['ok' => false, 'message' => __('seviaflow-ui::panel.selected_export_scope_is_invalid'), 'selected_fields' => []];
        }

        if ($exportScope === 'selected_rows' && $selectedRecords === []) {
            return ['ok' => false, 'message' => __('seviaflow-ui::panel.select_at_least_one_row_to_export_selected_rows'), 'selected_fields' => []];
        }

        return ['ok' => true, 'message' => null, 'selected_fields' => $selected];
    }
}
