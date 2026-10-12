<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Fields;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Forms\FormState;
use SeviaFlow\Core\Resources\Fields\BelongsToManyField;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Fields\RepeaterField;
use SeviaFlow\Core\Resources\Fields\SelectField;
use SeviaFlow\Core\Resources\ResourceContext;

final class SelectChoiceState
{
    /** @param class-string<Model> $modelClass
     * @return array<int, string>
     */
    public function allowedKeys(MultiSelectField $field, string $modelClass, FormState $state, string $candidate): array
    {
        if ($field instanceof BelongsToManyField) {
            $selected = $state->value($field->name(), []);
            $selected = is_array($selected) ? $selected : [];
            $selected[] = $candidate;

            return array_map(static fn (int|string $key): string => (string) $key, array_keys($field->resolveOptions(new $modelClass, '', $selected)));
        }

        return $field->getSelectableOptionKeys($state);
    }

    /** @param array<int, Field> $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function prune(array $fields, array $values, string $changed, ResourceContext $context, ?Model $record = null): array
    {
        $parts = explode('.', $changed);
        foreach ($fields as $field) {
            if ($field instanceof RepeaterField && count($parts) === 3 && $parts[0] === $field->name()) {
                $item = $values[$parts[0]][$parts[1]] ?? null;
                if (is_array($item)) {
                    $values[$parts[0]][$parts[1]] = $this->prune($field->getItemFields(), $item, $parts[2], $context);
                }
            }
            if ((! $field instanceof SelectField && ! $field instanceof MultiSelectField) || $field instanceof BelongsToManyField || ! in_array($changed, $field->getDependencyFields(), true)) {
                continue;
            }
            $state = new FormState($values, $context, $record);
            if ($field->isReadonly($context) || $field->isDisabledForState($context, $state)) {
                continue;
            }
            $keys = $field->getSelectableOptionKeys($state);
            $current = $values[$field->name()] ?? null;
            if ($field instanceof MultiSelectField) {
                $values[$field->name()] = is_array($current)
                    ? array_values(array_filter($current, static fn (mixed $value): bool => is_scalar($value) && in_array((string) $value, $keys, true)))
                    : [];
            } elseif (is_scalar($current) && ! in_array((string) $current, $keys, true)) {
                $values[$field->name()] = '';
            }
        }

        return $values;
    }
}
