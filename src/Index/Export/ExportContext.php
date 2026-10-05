<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index\Export;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SeviaFlow\Core\Resources\ExportField;
use SeviaFlow\Core\Resources\ExportResource;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Support\SensitiveDataPolicy;
use SeviaFlow\Core\Tables\ResourceTableQuery;

final class ExportContext
{
    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function canExport(string $resourceClass, bool $canViewAny): bool
    {
        $export = $this->definition($resourceClass);

        return $export->isEnabled()
            && $this->fields($resourceClass) !== []
            && $canViewAny;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @return array<int, ExportField>
     */
    public function fields(string $resourceClass): array
    {
        return array_values(array_filter(
            $this->definition($resourceClass)->getAllowedFields(),
            static fn (ExportField $field): bool => ! SensitiveDataPolicy::isSensitive($field->name())
        ));
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @return array<int, string>
     */
    public function formats(string $resourceClass): array
    {
        return $this->definition($resourceClass)->getFormats();
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<int, string>  $selectedFieldNames
     * @return array<int, string>
     */
    public function headings(string $resourceClass, array $selectedFieldNames): array
    {
        $fields = [];

        foreach ($this->fields($resourceClass) as $field) {
            $fields[$field->name()] = $field;
        }

        $labels = [];

        foreach ($selectedFieldNames as $fieldName) {
            if (! isset($fields[$fieldName])) {
                continue;
            }

            $labels[] = $fields[$fieldName]->getLabel();
        }

        return $labels;
    }

    /**
     * @param  array<int, string>  $selectedFieldNames
     * @param  Collection<int, Model>  $selectedRecordModels
     * @param  Collection<int, Model>  $currentViewRecords
     * @return array<int, array<int, mixed>>
     */
    public function rows(
        array $selectedFieldNames,
        string $exportScope,
        Collection $selectedRecordModels,
        Collection $currentViewRecords
    ): array {
        $records = $exportScope === 'selected_rows'
            ? $selectedRecordModels
            : $currentViewRecords;

        $rows = [];
        foreach ($records as $record) {
            $row = [];
            foreach ($selectedFieldNames as $fieldName) {
                if (SensitiveDataPolicy::isSensitive($fieldName)) {
                    continue;
                }

                $row[] = data_get($record, $fieldName);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return Collection<int, Model>
     */
    public function currentViewRecords(
        ResourceTableQuery $tableQuery,
        string $search,
        string $sortColumn,
        string $sortDirection,
        array $filterValues,
        string $trashedScope,
        int $maxRows
    ): Collection {
        /** @var Collection<int, Model> $records */
        $records = $tableQuery
            ->search($search)
            ->sort($sortColumn, $sortDirection)
            ->filters($filterValues)
            ->trashedScope($trashedScope)
            ->query()
            ->limit($maxRows + 1)
            ->get();

        return $records;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function definition(string $resourceClass): ExportResource
    {
        return $resourceClass::export(new ExportResource);
    }
}
