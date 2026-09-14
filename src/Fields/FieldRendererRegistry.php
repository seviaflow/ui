<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Fields;

use InvalidArgumentException;
use LogicException;
use SeviaFlow\Core\Resources\Fields\Field;

final class FieldRendererRegistry
{
    /**
     * @var array<string, FieldRendererDefinition>
     */
    private array $renderers = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(
        string $type,
        string $editView,
        ?string $showView = null,
        ?string $cellView = null,
        array $data = []
    ): static {
        $type = trim($type);
        $editView = trim($editView);

        if ($type === '' || $editView === '') {
            throw new InvalidArgumentException('Field renderer type and edit view cannot be empty.');
        }

        $this->renderers[$type] = new FieldRendererDefinition(
            editView: $editView,
            showView: $showView !== null ? trim($showView) : null,
            cellView: $cellView !== null ? trim($cellView) : null,
            data: $data,
        );

        return $this;
    }

    public function has(string $type): bool
    {
        return isset($this->renderers[$type]);
    }

    public function resolve(Field $field, string $mode = 'edit'): FieldRendererDefinition
    {
        if (! method_exists($field, 'type')) {
            throw new LogicException(sprintf(
                'Field [%s] does not expose the SeviaFlow field type contract.',
                $field->name()
            ));
        }

        $type = call_user_func([$field, 'type']);

        if (! is_string($type) || $type === '') {
            throw new LogicException(sprintf(
                'Field [%s] exposes an invalid field type contract.',
                $field->name()
            ));
        }

        $renderer = $this->renderers[$type] ?? null;

        if (! $renderer instanceof FieldRendererDefinition) {
            throw new LogicException(sprintf(
                'No UI field renderer is registered for field type [%s] (field [%s]). Register an edit, show, and cell adapter.',
                $type,
                $field->name()
            ));
        }

        $renderer->view($mode);

        return $renderer;
    }

    public function view(Field $field, string $mode = 'edit'): string
    {
        return $this->resolve($field, $mode)->view($mode);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(Field $field, string $mode, array $data = []): string
    {
        $renderer = $this->resolve($field, $mode);

        return view($renderer->view($mode), array_merge(
            ['field' => $field],
            $renderer->data(),
            $data,
        ))->render();
    }
}
