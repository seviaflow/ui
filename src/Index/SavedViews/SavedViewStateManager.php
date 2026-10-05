<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index\SavedViews;

final class SavedViewStateManager
{
    public const STATE_VERSION = 2;

    /**
     * @param  array<string, mixed>  $filterValues
     * @return array{
     *   state_version: int,
     *   search: string,
     *   filters: array<string, mixed>,
     *   sort: array{column: string, direction: string},
     *   per_page: int,
     *   columns: array<int, string>,
     *   trash: string
     * }
     */
    public function collectCurrentState(
        string $search,
        array $filterValues,
        string $sortColumn,
        string $sortDirection,
        int $perPage,
        array $columns = [],
        string $trashedScope = 'active'
    ): array {
        return [
            'state_version' => self::STATE_VERSION,
            'search' => $search,
            'filters' => $filterValues,
            'sort' => [
                'column' => $sortColumn,
                'direction' => $sortDirection,
            ],
            'per_page' => $perPage,
            'columns' => array_values(array_filter($columns, static fn (mixed $column): bool => is_string($column) && $column !== '')),
            'trash' => in_array($trashedScope, ['active', 'trashed', 'with_trashed'], true)
                ? $trashedScope
                : 'active',
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $currentFilterValues
     * @return array{
     *   search: string,
     *   filter_values: array<string, mixed>,
     *   sort_column: string,
     *   sort_direction: string,
     *   per_page: int|null,
     *   columns: array<int, string>,
     *   trashed_scope: string
     * }
     */
    public function applyState(
        array $state,
        array $currentFilterValues,
        array $allowedColumns = [],
        array $defaultColumns = [],
        array $sortableColumns = [],
        bool $supportsTrash = true
    ): array {
        $appliedFilterValues = $currentFilterValues;

        if (is_array($state['filters'] ?? null)) {
            foreach ($state['filters'] as $key => $value) {
                if (! is_string($key) || ! array_key_exists($key, $appliedFilterValues)) {
                    continue;
                }

                $appliedFilterValues[$key] = $value;
            }
        }

        $sort = is_array($state['sort'] ?? null) ? $state['sort'] : [];
        $sortColumn = is_string($sort['column'] ?? null) ? (string) $sort['column'] : '';
        $sortDirection = strtolower((string) ($sort['direction'] ?? 'asc'));

        if ($allowedColumns !== [] && ! in_array($sortColumn, $allowedColumns, true)) {
            $sortColumn = '';
        }

        if ($sortableColumns !== [] && $sortColumn !== '' && ! in_array($sortColumn, $sortableColumns, true)) {
            $sortColumn = '';
        }

        $perPage = $state['per_page'] ?? null;

        $stateVersion = is_numeric($state['state_version'] ?? null)
            ? (int) $state['state_version']
            : 1;
        $columns = $defaultColumns;

        if ($stateVersion === self::STATE_VERSION && is_array($state['columns'] ?? null)) {
            $columns = array_values(array_unique(array_filter(
                $state['columns'],
                static fn (mixed $column): bool => is_string($column)
                    && $column !== ''
                    && ($allowedColumns === [] || in_array($column, $allowedColumns, true))
            )));

            if ($columns === []) {
                $columns = $defaultColumns;
            }
        }

        $requestedTrashScope = $state['trash'] ?? 'active';
        $trashedScope = $supportsTrash && in_array(
            $requestedTrashScope,
            ['active', 'trashed', 'with_trashed'],
            true
        ) ? (string) $requestedTrashScope : 'active';

        return [
            'search' => is_string($state['search'] ?? null) ? (string) $state['search'] : '',
            'filter_values' => $appliedFilterValues,
            'sort_column' => $sortColumn,
            'sort_direction' => in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'asc',
            'per_page' => is_int($perPage) ? $perPage : null,
            'columns' => $columns,
            'trashed_scope' => $trashedScope,
        ];
    }

    /**
     * @param  array<string, mixed>  $currentState
     * @param  array<string, mixed>  $savedState
     * @param  array<string, mixed>  $defaultFilterValues
     * @param  array<int, string>  $allowedColumns
     * @param  array<int, string>  $defaultColumns
     * @param  array<int, string>  $sortableColumns
     */
    public function isModified(
        array $currentState,
        array $savedState,
        array $defaultFilterValues,
        array $allowedColumns,
        array $defaultColumns,
        array $sortableColumns,
        bool $supportsTrash
    ): bool {
        $current = $this->applyState(
            state: $currentState,
            currentFilterValues: $defaultFilterValues,
            allowedColumns: $allowedColumns,
            defaultColumns: $defaultColumns,
            sortableColumns: $sortableColumns,
            supportsTrash: $supportsTrash,
        );
        $saved = $this->applyState(
            state: $savedState,
            currentFilterValues: $defaultFilterValues,
            allowedColumns: $allowedColumns,
            defaultColumns: $defaultColumns,
            sortableColumns: $sortableColumns,
            supportsTrash: $supportsTrash,
        );

        return $current !== $saved;
    }
}
