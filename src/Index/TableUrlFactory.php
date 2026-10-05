<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index;

use Illuminate\Database\Eloquent\Model;

final class TableUrlFactory
{
    public function createUrl(string $resourceSlug): string
    {
        return route($this->routePrefix().'resources.create', [
            'resource' => $resourceSlug,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function editUrl(string $resourceSlug, Model $record, array $context = []): string
    {
        return $this->appendContext(route($this->routePrefix().'resources.edit', [
            'resource' => $resourceSlug,
            'record' => $record->getKey(),
        ]), $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function showUrl(string $resourceSlug, Model $record, array $context = []): string
    {
        return $this->appendContext(route($this->routePrefix().'resources.show', [
            'resource' => $resourceSlug,
            'record' => $record->getKey(),
        ]), $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function indexUrl(string $resourceSlug, array $context = []): string
    {
        return $this->appendContext(route($this->routePrefix().'resources.index', [
            'resource' => $resourceSlug,
        ]), $context);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function contextFromQuery(array $query): array
    {
        return $this->normalizeContext($query);
    }

    private function routePrefix(): string
    {
        return config('seviaflow-ui.route_name_prefix', 'seviaflow.');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function appendContext(string $url, array $context): string
    {
        $normalized = $this->normalizeContext($context);

        if ($normalized === []) {
            return $url;
        }

        return $url.'?'.http_build_query($normalized, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $normalized = [];

        if (is_string($context['search'] ?? null)) {
            $search = mb_substr(trim($context['search']), 0, 200);
            if ($search !== '') {
                $normalized['search'] = $search;
            }
        }

        $filters = $context['filter'] ?? $context['filterValues'] ?? null;
        if (is_array($filters)) {
            $normalizedFilters = [];
            foreach ($filters as $key => $value) {
                if (! is_string($key) || trim($key) === '') {
                    continue;
                }

                $safeValue = $this->normalizeValue($value);
                if ($safeValue !== null && $safeValue !== '') {
                    $normalizedFilters[$key] = $safeValue;
                }
            }

            if ($normalizedFilters !== []) {
                $normalized['filter'] = $normalizedFilters;
            }
        }

        if (is_string($context['sort'] ?? null) && trim($context['sort']) !== '') {
            $normalized['sort'] = mb_substr(trim($context['sort']), 0, 100);
        }

        if (in_array(strtolower((string) ($context['direction'] ?? '')), ['asc', 'desc'], true)) {
            $normalized['direction'] = strtolower((string) $context['direction']);
        }

        if (is_numeric($context['per_page'] ?? null) && (int) $context['per_page'] > 0) {
            $normalized['per_page'] = min((int) $context['per_page'], 100);
        }

        if (in_array($context['trash'] ?? null, ['active', 'trashed', 'with_trashed'], true)) {
            $normalized['trash'] = $context['trash'];
        }

        if (is_numeric($context['page'] ?? null) && (int) $context['page'] > 1) {
            $normalized['page'] = min((int) $context['page'], 100000);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 2) {
            return null;
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $nestedValue) {
                $normalized[$key] = $this->normalizeValue($nestedValue, $depth + 1);
            }

            return $normalized;
        }

        return is_scalar($value) ? $value : null;
    }
}
