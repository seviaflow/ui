<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Show\Relations;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Forms\FieldValueNormalizer;
use SeviaFlow\Core\Forms\FormFields;
use SeviaFlow\Core\Forms\FormStateInitializer;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\ResourceContext;

final class PivotFormHandler
{
    /**
     * @param  array<int, Field>  $fields
     * @return array<string, mixed>
     */
    public function initializeFromPivotRecord(Model $record, array $fields): array
    {
        $pivot = $record->getRelationValue('pivot');

        return (new FormStateInitializer)->initialize($fields, $pivot instanceof Model ? $pivot : null);
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function valuesForPersistence(array $fields, array $values, ResourceContext $context): array
    {
        $safe = [];
        $normalizer = new FieldValueNormalizer;

        foreach ((new FormFields)->persistable($fields, $context) as $field) {
            $raw = $values[$field->name()] ?? null;
            $safe[$field->name()] = $normalizer->normalize($field, $raw);
        }

        return $safe;
    }
}
