<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Fields;

final class FieldRendererDefinition
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly string $editView,
        private readonly ?string $showView,
        private readonly ?string $cellView,
        private readonly array $data = [],
    ) {}

    public function view(string $mode): string
    {
        return match ($mode) {
            'edit' => $this->editView,
            'show' => $this->showView ?? throw new \LogicException('Field renderer is missing its show adapter.'),
            'cell' => $this->cellView ?? throw new \LogicException('Field renderer is missing its cell adapter.'),
            default => throw new \InvalidArgumentException(sprintf(
                'Unknown field renderer mode [%s]. Use edit, show, or cell.',
                $mode
            )),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
