<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use SeviaFlow\Core\SeviaFlowCoreServiceProvider;
use SeviaFlow\Ui\SeviaFlowUiServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            SeviaFlowCoreServiceProvider::class,
            SeviaFlowUiServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('seviaflow-ui.admin_middleware', ['web']);
        $app['config']->set('seviaflow-ui.middleware', ['web']);
        $app['config']->set('seviaflow.permissions.admin_gate', static fn (mixed $user): bool => $user !== null);
    }
}
