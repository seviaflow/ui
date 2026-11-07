<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index\Import;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use LogicException;
use SeviaFlow\Core\Import\CsvImportExecutor;
use SeviaFlow\Core\Resources\ImportResource;
use SeviaFlow\Core\Resources\Resource;
use Throwable;

final class ImportExecutionRunner
{
    public function __construct(
        private readonly CsvImportExecutor $csvImportExecutor = new CsvImportExecutor
    ) {}

    /**
     * @param  class-string<resource>  $resourceClass
     * @return array{
     *   ok: bool,
     *   message: string,
     *   result: array{
     *     imported_count:int,
     *     failed_count:int,
     *     row_errors:array<int, string>,
     *     global_errors:array<int, string>
     *   }|null
     * }
     */
    public function run(
        mixed $importFile,
        string $resourceClass,
        ImportResource $import,
        int $maxRows,
        mixed $user = null,
        ?Request $request = null,
    ): array {
        if (! $importFile instanceof UploadedFile) {
            return ['ok' => false, 'message' => __('seviaflow-ui::panel.a_valid_csv_file_is_required'), 'result' => null];
        }

        try {
            $result = $this->csvImportExecutor->execute(
                resourceClass: $resourceClass,
                import: $import,
                path: $importFile->getRealPath() ?: '',
                maxRows: $maxRows,
                user: $user,
                request: $request,
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
                'result' => null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'message' => __('seviaflow-ui::panel.import_failed_please_try_again'),
                'result' => null,
            ];
        }

        if ($result['global_errors'] !== [] || $result['row_errors'] !== []) {
            return [
                'ok' => false,
                'message' => __('seviaflow-ui::panel.import_failed_counts', ['imported' => $result['imported_count'], 'failed' => $result['failed_count']]),
                'result' => $result,
            ];
        }

        return [
            'ok' => true,
            'message' => __('seviaflow-ui::panel.import_completed_counts', ['imported' => $result['imported_count'], 'failed' => $result['failed_count']]),
            'result' => $result,
        ];
    }
}
