<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index\Import;

final class ImportModalState
{
    /**
     * @return array{
     *   show_import_modal: bool,
     *   import_file: mixed,
     *   import_preview_headers: array<int, string>,
     *   import_preview_rows: array<int, array{index:int, values:array<string, string>, errors:array<int, string>}>,
     *   import_preview_errors: array<int, string>
     * }
     */
    public function open(): array
    {
        return [
            'show_import_modal' => true,
            'import_file' => null,
            'import_preview_headers' => [],
            'import_preview_rows' => [],
            'import_preview_errors' => [],
        ];
    }

    /**
     * @return array{
     *   show_import_modal: bool,
     *   import_file: mixed,
     *   import_preview_headers: array<int, string>,
     *   import_preview_rows: array<int, array{index:int, values:array<string, string>, errors:array<int, string>}>,
     *   import_preview_errors: array<int, string>
     * }
     */
    public function close(): array
    {
        return [
            'show_import_modal' => false,
            'import_file' => null,
            'import_preview_headers' => [],
            'import_preview_rows' => [],
            'import_preview_errors' => [],
        ];
    }

    /**
     * @return array{
     *   import_preview_headers: array<int, string>,
     *   import_preview_rows: array<int, array{index:int, values:array<string, string>, errors:array<int, string>}>,
     *   import_preview_errors: array<int, string>
     * }
     */
    public function clearPreview(): array
    {
        return [
            'import_preview_headers' => [],
            'import_preview_rows' => [],
            'import_preview_errors' => [],
        ];
    }
}
