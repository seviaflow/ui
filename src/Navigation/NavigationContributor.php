<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Navigation;

interface NavigationContributor
{
    /**
     * @return array<int, array{
     *   key:string,
     *   label:string,
     *   items:array<int, array<string,mixed>>
     * }>
     */
    public function groups(): array;
}
