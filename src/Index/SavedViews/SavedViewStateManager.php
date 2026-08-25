<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index\SavedViews;

final class SavedViewStateManager
{
    /**
     * @param  array<string, mixed>  $filterValues
     * @return array{
     *   search: string,
     *   filters: array<string, mixed>,
     *   sort: array{column: string, direction: string},
     *   per_page: int
     * }
     */
    public function collectCurrentState(
        string $search,
        array $filterValues,
        string $sortColumn,
        string $sortDirection,
        int $perPage
    ): array {
        return [
            'search' => $search,
            'filters' => $filterValues,
            'sort' => [
                'column' => $sortColumn,
                'direction' => $sortDirection,
            ],
            'per_page' => $perPage,
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
     *   per_page: int|null
     * }
     */
    public function applyState(array $state, array $currentFilterValues): array
    {
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

        $perPage = $state['per_page'] ?? null;

        return [
            'search' => is_string($state['search'] ?? null) ? (string) $state['search'] : '',
            'filter_values' => $appliedFilterValues,
            'sort_column' => $sortColumn,
            'sort_direction' => in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'asc',
            'per_page' => is_int($perPage) ? $perPage : null,
        ];
    }
}
