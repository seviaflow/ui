<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Relations;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Forms\FormFields;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\ResourceContext;

final class PivotFormHandler
{
    /**
     * @param  array<int, Field>  $fields
     * @return array<string, mixed>
     */
    public function initializeFromPivotRecord(Model $record, array $fields): array
    {
        $values = [];
        $pivot = $record->getRelationValue('pivot');

        foreach ($fields as $field) {
            $name = $field->name();
            $value = $pivot instanceof Model ? $pivot->getAttribute($name) : null;

            if ($field instanceof ToggleField) {
                $values[$name] = (bool) $value;

                continue;
            }

            $values[$name] = $value === null ? '' : (string) $value;
        }

        return $values;
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function valuesForPersistence(array $fields, array $values, ResourceContext $context): array
    {
        $safe = [];

        foreach ((new FormFields)->persistable($fields, $context) as $field) {
            $raw = $values[$field->name()] ?? null;

            if ($field instanceof ToggleField) {
                $safe[$field->name()] = (bool) $raw;

                continue;
            }

            if ($field instanceof NumberField) {
                if ($raw === null || $raw === '') {
                    $safe[$field->name()] = null;
                } elseif (is_int($raw) || is_float($raw)) {
                    $safe[$field->name()] = $raw;
                } else {
                    $safe[$field->name()] = str_contains((string) $raw, '.') ? (float) $raw : (int) $raw;
                }

                continue;
            }

            $safe[$field->name()] = $raw === '' ? null : $raw;
        }

        return $safe;
    }
}
