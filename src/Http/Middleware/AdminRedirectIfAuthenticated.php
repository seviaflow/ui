<?php

declare(strict_types=1);

namespace SeviaFlow\Ui\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SeviaFlow\Core\Auth\AdminAccessGate;
use Symfony\Component\HttpFoundation\Response;

final class AdminRedirectIfAuthenticated extends RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guard = $guards[0] ?? config('seviaflow-ui.auth.guard', 'web');
        $user = Auth::guard($guard)->user();

        if (! $user instanceof Authenticatable || ! app(AdminAccessGate::class)->allows($user)) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }

    protected function redirectTo(Request $request): ?string
    {
        $routeNamePrefix = (string) config('seviaflow-ui.route_name_prefix', 'seviaflow.');

        if (app('router')->has($routeNamePrefix.'dashboard')) {
            return route($routeNamePrefix.'dashboard');
        }

        return parent::redirectTo($request);
    }
}
