<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Index;

use Illuminate\Database\Eloquent\Model;

final class BulkSelectionState
{
    /**
     * @param  array<int, mixed>  $values
     * @param  array<int, string>  $visibleKeys
     * @return array<int, string>
     */
    public function normalize(array $values, array $visibleKeys): array
    {
        $visibleLookup = array_fill_keys($visibleKeys, true);
        $normalized = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $key = (string) $value;

            if ($key === '' || ! isset($visibleLookup[$key])) {
                continue;
            }

            $normalized[$key] = $key;
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, Model>  $records
     * @return array<int, string>
     */
    public function visibleKeys(array $records): array
    {
        return array_values(array_map(
            static fn (Model $record): string => (string) $record->getKey(),
            $records
        ));
    }

    /**
     * @param  array<int, string>  $visibleKeys
     * @return array{selectedRecords: array<int, string>, selectPage: bool}
     */
    public function selectAllVisible(array $visibleKeys): array
    {
        return [
            'selectedRecords' => $visibleKeys,
            'selectPage' => $visibleKeys !== [],
        ];
    }

    /**
     * @param  array<int, string>  $selectedRecords
     * @param  array<int, string>  $visibleKeys
     * @return array{selectedRecords: array<int, string>, selectPage: bool}
     */
    public function removeRecord(array $selectedRecords, string $recordKey, array $visibleKeys): array
    {
        $selected = array_values(array_filter(
            $selectedRecords,
            static fn (string $selectedKey): bool => $selectedKey !== $recordKey
        ));

        return [
            'selectedRecords' => $selected,
            'selectPage' => $selected !== [] && $selected === $visibleKeys,
        ];
    }

    /**
     * @param  array<int, string>  $selectedRecords
     */
    public function count(array $selectedRecords): int
    {
        return count($selectedRecords);
    }
}
