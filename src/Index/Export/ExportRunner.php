<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index\Export;

use SeviaFlow\Core\Export\CsvExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportRunner
{
    public function __construct(
        private readonly CsvExporter $csvExporter = new CsvExporter
    ) {}

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{response: StreamedResponse|null, error: string|null}
     */
    public function run(string $resourceSlug, array $headings, array $rows, int $maxRows): array
    {
        if (count($rows) > $maxRows) {
            return [
                'response' => null,
                'error' => sprintf(
                    'Export exceeds the maximum allowed rows (%d). Please narrow your filters or selection.',
                    $maxRows
                ),
            ];
        }

        return [
            'response' => $this->csvExporter->download(
                resourceSlug: $resourceSlug,
                headings: $headings,
                rows: $rows
            ),
            'error' => null,
        ];
    }
}
