<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\SavedViews;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class SavedViewRepository
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function createPrivate(?Model $user, string $resource, string $name, array $state): SavedView
    {
        if (! $user instanceof Model) {
            throw new InvalidArgumentException('Private saved views require an authenticated user.');
        }

        $normalizedResource = $this->normalizeResourceSlug($resource);
        $normalizedName = trim($name);

        if ($normalizedName === '') {
            throw new InvalidArgumentException('Saved view name cannot be empty.');
        }

        /** @var SavedView $savedView */
        $savedView = SavedView::query()->create([
            'resource' => $normalizedResource,
            'name' => $normalizedName,
            'user_type' => $user->getMorphClass(),
            'user_id' => (string) $user->getKey(),
            'visibility' => 'private',
            'state' => $this->sanitizeState($state),
        ]);

        return $savedView;
    }

    /**
     * @return Collection<int, SavedView>
     */
    public function forResourceAndUser(string $resource, Model $user): Collection
    {
        return SavedView::query()
            ->where('resource', $this->normalizeResourceSlug($resource))
            ->where('visibility', 'private')
            ->where('user_type', $user->getMorphClass())
            ->where('user_id', (string) $user->getKey())
            ->orderBy('name')
            ->get();
    }

    public function deleteOwned(SavedView $savedView, Model $user): bool
    {
        if (
            $savedView->visibility !== 'private'
            || $savedView->user_type !== $user->getMorphClass()
            || (string) $savedView->user_id !== (string) $user->getKey()
        ) {
            return false;
        }

        return (bool) $savedView->delete();
    }

    private function normalizeResourceSlug(string $resource): string
    {
        $slug = trim($resource);

        if ($slug === '') {
            throw new InvalidArgumentException('Resource slug cannot be empty.');
        }

        if (str_contains($slug, '\\')) {
            throw new InvalidArgumentException('Resource must be a slug, not a class name.');
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{
     *   state_version: int,
     *   search: string|null,
     *   filters: array<string, mixed>,
     *   sort: array{column: string, direction: 'asc'|'desc'}|null,
     *   per_page: int|null,
     *   columns: array<int, string>,
     *   trash: string
     * }
     */
    private function sanitizeState(array $state): array
    {
        $stateVersion = is_numeric($state['state_version'] ?? null)
            ? (int) $state['state_version']
            : 1;

        $normalizedSearch = null;
        if (array_key_exists('search', $state) && is_string($state['search'])) {
            $trimmed = mb_substr(trim($state['search']), 0, 200);
            $normalizedSearch = $trimmed === '' ? null : $trimmed;
        }

        $normalizedFilters = [];
        if (array_key_exists('filters', $state) && is_array($state['filters'])) {
            foreach ($state['filters'] as $key => $value) {
                if (! is_string($key) || $key === '') {
                    continue;
                }

                $normalizedFilters[$key] = $this->sanitizeValue($value);
            }
        }

        $normalizedSort = null;
        if (array_key_exists('sort', $state) && is_array($state['sort'])) {
            $column = $state['sort']['column'] ?? null;
            $direction = $state['sort']['direction'] ?? null;

            if (is_string($column) && $column !== '' && is_string($direction)) {
                $direction = strtolower($direction);
                if (in_array($direction, ['asc', 'desc'], true)) {
                    $normalizedSort = [
                        'column' => $column,
                        'direction' => $direction,
                    ];
                }
            }
        }

        $normalizedPerPage = null;
        if (array_key_exists('per_page', $state) && is_int($state['per_page']) && $state['per_page'] > 0) {
            $normalizedPerPage = $state['per_page'];
        }

        $normalizedColumns = [];
        if (is_array($state['columns'] ?? null)) {
            foreach ($state['columns'] as $column) {
                if (! is_string($column) || trim($column) === '') {
                    continue;
                }

                $normalizedColumns[] = trim($column);
            }
        }

        $trash = is_string($state['trash'] ?? null) && in_array(
            $state['trash'],
            ['active', 'trashed', 'with_trashed'],
            true
        ) ? $state['trash'] : 'active';

        return [
            'state_version' => $stateVersion,
            'search' => $normalizedSearch,
            'filters' => $normalizedFilters,
            'sort' => $normalizedSort,
            'per_page' => $normalizedPerPage,
            'columns' => array_values(array_unique($normalizedColumns)),
            'trash' => $trash,
        ];
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $nestedValue) {
                $normalized[$key] = $this->sanitizeValue($nestedValue);
            }

            return $normalized;
        }

        return is_scalar($value) || $value === null ? $value : null;
    }
}
