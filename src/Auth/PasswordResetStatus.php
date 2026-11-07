<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Auth;

use Illuminate\Support\Facades\Lang;

final class PasswordResetStatus
{
    public static function message(string $status): string
    {
        // Respect the application's translations before using package defaults.
        if (Lang::hasForLocale($status)) {
            return __($status);
        }

        $key = 'seviaflow-ui::'.$status;

        return Lang::has($key) ? __($key) : __($status);
    }
}
