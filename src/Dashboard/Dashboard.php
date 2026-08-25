<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard;

abstract class Dashboard
{
    abstract public static function slug(): string;

    abstract public static function label(): string;

    public static function description(): ?string
    {
        return null;
    }

    /**
     * @return array<int, mixed>
     */
    public static function widgets(): array
    {
        return [];
    }

    public static function visibleFor(mixed $user): bool
    {
        return true;
    }

    /**
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public static function permissions(): array
    {
        return [];
    }
}
