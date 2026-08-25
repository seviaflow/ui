<?php

declare(strict_types=1);

use SeviaFlow\Ui\Index\SavedViews\SavedViewStateManager;

it('collects current saved view state shape', function (): void {
    $state = (new SavedViewStateManager)->collectCurrentState(
        search: 'acme',
        filterValues: ['status' => 'published'],
        sortColumn: 'name',
        sortDirection: 'desc',
        perPage: 25
    );

    expect($state)->toBe([
        'search' => 'acme',
        'filters' => ['status' => 'published'],
        'sort' => ['column' => 'name', 'direction' => 'desc'],
        'per_page' => 25,
    ]);
});

it('applies saved state only to known filter keys and normalizes sort direction', function (): void {
    $applied = (new SavedViewStateManager)->applyState(
        state: [
            'search' => 'acme',
            'filters' => ['status' => 'published', 'unknown' => 'x'],
            'sort' => ['column' => 'name', 'direction' => 'DESC'],
            'per_page' => 50,
        ],
        currentFilterValues: ['status' => '', 'is_active' => '']
    );

    expect($applied['search'])->toBe('acme')
        ->and($applied['filter_values'])->toBe(['status' => 'published', 'is_active' => ''])
        ->and($applied['sort_column'])->toBe('name')
        ->and($applied['sort_direction'])->toBe('desc')
        ->and($applied['per_page'])->toBe(50);
});
