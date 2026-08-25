<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use SeviaFlow\Ui\SeviaFlowUiServiceProvider;

it('renders login page', function (): void {
    pest_test_case()->get('/admin/login')
        ->assertOk()
        ->assertSee('SeviaFlow')
        ->assertSee('Sign in to your account')
        ->assertSee('Email address')
        ->assertSee('Password')
        ->assertSee('Forgot password?')
        ->assertSee('action="'.route('seviaflow.login.store').'"', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.css', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.js', false)
        ->assertDontSee('@vite', false);
});

it('renders forgot password page', function (): void {
    pest_test_case()->get('/admin/forgot-password')
        ->assertOk()
        ->assertSee('SeviaFlow')
        ->assertSee('Reset your password')
        ->assertSee('Email address')
        ->assertSee('Send reset link')
        ->assertSee('Back to sign in')
        ->assertSee('action="'.route('seviaflow.password.email').'"', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.css', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.js', false)
        ->assertDontSee('@vite', false);
});

it('renders reset password page', function (): void {
    pest_test_case()->get('/admin/reset-password/test-token')
        ->assertOk()
        ->assertSee('Reset password')
        ->assertSee('Email address')
        ->assertSee('Password')
        ->assertSee('Confirm password')
        ->assertSee('action="'.route('seviaflow.password.update').'"', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.css', false)
        ->assertSee('vendor/seviaflow/ui/seviaflow.js', false)
        ->assertDontSee('@vite', false);
});

it('auth routes respect custom admin prefix', function (): void {
    config()->set('seviaflow-ui.route_prefix', 'backoffice');

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, true);

    pest_test_case()->get('/backoffice/login')->assertOk();
    pest_test_case()->get('/backoffice/forgot-password')->assertOk();
    pest_test_case()->get('/backoffice/reset-password/test-token')->assertOk();
});

it('can disable auth routes through config', function (): void {
    config()->set('seviaflow-ui.auth.enabled', false);

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, true);

    pest_test_case()->get('/admin/login')->assertNotFound();
    pest_test_case()->get('/admin/forgot-password')->assertNotFound();
    pest_test_case()->get('/admin/reset-password/test-token')->assertNotFound();
});

it('public auth routes remain guest-accessible when admin routes require auth', function (): void {
    config()->set('seviaflow-ui.admin_middleware', ['web', 'auth']);

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, true);

    pest_test_case()->get('/admin/login')->assertOk();
    pest_test_case()->get('/admin/forgot-password')->assertOk();
    pest_test_case()->get('/admin/reset-password/test-token')->assertOk();
});

it('resource and profile routes require authentication when admin middleware includes auth', function (): void {
    config()->set('seviaflow-ui.admin_middleware', ['web', 'auth']);

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, true);

    pest_test_case()->get('/admin/resources/test-resource')->assertStatus(302);
    pest_test_case()->get('/admin/resources/test-resource/1')->assertStatus(302);
    pest_test_case()->get('/admin/profile')->assertStatus(302);
});

it('uses legacy middleware config as fallback for admin routes', function (): void {
    config()->set('seviaflow-ui.admin_middleware', []);
    config()->set('seviaflow-ui.middleware', ['web', 'auth']);

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, true);

    pest_test_case()->get('/admin/resources/test-resource')->assertStatus(302);
    pest_test_case()->get('/admin/profile')->assertStatus(302);
});

it('respects configured admin middleware for protected routes', function (): void {
    config()->set('seviaflow-ui.admin_middleware', ['web']);

    app('router')->setRoutes(new RouteCollection);
    app()->register(SeviaFlowUiServiceProvider::class, true);

    pest_test_case()->get('/admin/resources/test-resource')->assertStatus(302);
    pest_test_case()->get('/admin/profile')->assertStatus(302);
});
