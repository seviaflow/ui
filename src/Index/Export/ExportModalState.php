<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index\Export;

use SeviaFlow\Core\Resources\ExportField;

final class ExportModalState
{
    /**
     * @param  array<int, ExportField>  $fields
     * @param  array<int, string>  $formats
     * @return array{
     *   show_export_modal: bool,
     *   selected_export_fields: array<int, string>,
     *   selected_export_format: string,
     *   export_scope: string
     * }
     */
    public function open(array $fields, array $formats): array
    {
        return [
            'show_export_modal' => true,
            'selected_export_fields' => array_map(
                static fn (ExportField $field): string => $field->name(),
                $fields
            ),
            'selected_export_format' => $formats[0] ?? 'csv',
            'export_scope' => 'current_view',
        ];
    }

    /**
     * @return array{show_export_modal: bool}
     */
    public function close(): array
    {
        return ['show_export_modal' => false];
    }

    /**
     * @param  array<int, ExportField>  $fields
     * @return array<int, string>
     */
    public function selectAllFields(array $fields): array
    {
        return array_map(
            static fn (ExportField $field): string => $field->name(),
            $fields
        );
    }
}
