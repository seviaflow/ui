<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SeviaFlow\Core\Auth\AdminAccessGate;

final class AdminAuthenticate extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        parent::authenticate($request, $guards);

        $guard = $guards[0] ?? config('seviaflow-ui.auth.guard', 'web');
        $user = Auth::guard($guard)->user();

        if (! $user instanceof Authenticatable || ! app(AdminAccessGate::class)->allows($user)) {
            abort(403);
        }
    }

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
