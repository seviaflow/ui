<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Http\Middleware;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;

final class AdminRedirectIfAuthenticated extends RedirectIfAuthenticated
{
    protected function redirectTo(Request $request): ?string
    {
        $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');

        if (app('router')->has($routeNamePrefix.'dashboard')) {
            return route($routeNamePrefix.'dashboard');
        }

        return parent::redirectTo($request);
    }
}
