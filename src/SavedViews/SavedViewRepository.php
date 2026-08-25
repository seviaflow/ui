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
     *   search: string|null,
     *   filters: array<string, mixed>,
     *   sort: array{column: string, direction: 'asc'|'desc'}|null,
     *   per_page: int|null
     * }
     */
    private function sanitizeState(array $state): array
    {
        $normalizedSearch = null;
        if (array_key_exists('search', $state) && is_string($state['search'])) {
            $trimmed = trim($state['search']);
            $normalizedSearch = $trimmed === '' ? null : $trimmed;
        }

        $normalizedFilters = [];
        if (array_key_exists('filters', $state) && is_array($state['filters'])) {
            foreach ($state['filters'] as $key => $value) {
                if (! is_string($key) || $key === '') {
                    continue;
                }

                $normalizedFilters[$key] = $value;
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

        return [
            'search' => $normalizedSearch,
            'filters' => $normalizedFilters,
            'sort' => $normalizedSort,
            'per_page' => $normalizedPerPage,
        ];
    }
}
