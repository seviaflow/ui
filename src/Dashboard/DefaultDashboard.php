<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Dashboard;

final class DefaultDashboard extends Dashboard
{
    public static function slug(): string
    {
        return 'default';
    }

    public static function label(): string
    {
        return 'Dashboard';
    }

    public static function description(): string
    {
        return 'Welcome to SeviaFlow';
    }
}
