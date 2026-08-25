<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index\Import;

use Illuminate\Http\UploadedFile;
use SeviaFlow\Core\Import\CsvImportPreviewer;

final class ImportPreviewRunner
{
    public function __construct(
        private readonly CsvImportPreviewer $csvImportPreviewer = new CsvImportPreviewer
    ) {}

    /**
     * @param  array<int, string>  $allowedFields
     * @param  array<int, string>  $requiredFields
     * @return array{
     *   ok: bool,
     *   message: string|null,
     *   preview: array{
     *     headers: array<int, string>,
     *     rows: array<int, array{index:int, values:array<string, string>, errors:array<int, string>}>,
     *     errors: array<int, string>
     *   }|null
     * }
     */
    public function run(mixed $importFile, array $allowedFields, array $requiredFields, int $rowLimit = 20): array
    {
        if (! $importFile instanceof UploadedFile) {
            return ['ok' => false, 'message' => 'A valid CSV file is required.', 'preview' => null];
        }

        $preview = $this->csvImportPreviewer->preview(
            path: $importFile->getRealPath() ?: '',
            allowedFields: $allowedFields,
            requiredFields: $requiredFields,
            rowLimit: $rowLimit
        );

        return ['ok' => true, 'message' => null, 'preview' => $preview];
    }
}
