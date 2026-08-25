<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;

final class AdminAuthenticate extends Authenticate
{
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        if (! (bool) config('seviaflow-ui.auth.enabled', true)) {
            return '/';
        }

        $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');
        $loginRouteName = $routeNamePrefix.'login';

        if (app('router')->has($loginRouteName)) {
            return route($loginRouteName);
        }

        $routePrefix = trim((string) config('seviaflow-ui.route_prefix', 'admin'), '/');

        return '/'.$routePrefix.'/login';
    }
}
