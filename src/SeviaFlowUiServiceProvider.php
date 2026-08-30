<?php

declare(strict_types=1);

namespace SeviaFlow\Ui;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Support\Cache\CacheParticipant;
use SeviaFlow\Core\Support\Capabilities\CapabilityRegistry;
use SeviaFlow\Ui\Dashboard\DashboardRegistry;
use SeviaFlow\Ui\Dashboard\DashboardSummaryCache;
use SeviaFlow\Ui\Dashboard\Layouts\DashboardLayoutRepository;
use SeviaFlow\Ui\Dashboard\Layouts\DashboardLayoutStore;
use SeviaFlow\Ui\Dashboard\Layouts\SavedDashboardLayoutStore;
use SeviaFlow\Ui\Dashboard\Layouts\SavedDashboardRepository;
use SeviaFlow\Ui\Dashboard\Widgets\DashboardWidgetResolver;
use SeviaFlow\Ui\Dashboard\Widgets\WidgetCache;
use SeviaFlow\Ui\Http\Middleware\AdminAuthenticate;
use SeviaFlow\Ui\Http\Middleware\AdminRedirectIfAuthenticated;
use SeviaFlow\Ui\Navigation\NavigationContributor;
use SeviaFlow\Ui\Navigation\NavigationMetadataCache;
use SeviaFlow\Ui\Navigation\NavigationMetadataResolver;
use SeviaFlow\Ui\Support\Cache\DashboardSummaryCacheParticipant;
use SeviaFlow\Ui\Support\Cache\DashboardWidgetCacheParticipant;
use SeviaFlow\Ui\Support\Cache\NavigationMetadataCacheParticipant;

final class SeviaFlowUiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/seviaflow-ui.php', 'seviaflow-ui');

        $this->registerDashboardBindings();
        $this->registerNavigationBindings();
        $this->registerCacheParticipants();
    }

    public function boot(CapabilityRegistry $capabilities): void
    {
        $capabilities->register('ui', (bool) config('seviaflow-ui.enabled', true), 'Operator-facing Livewire UI');
        $capabilities->register('dashboard', (bool) config('seviaflow-ui.dashboard.enabled', true), 'Dashboard UI');

        if (! (bool) config('seviaflow-ui.enabled', true)) {
            return;
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'seviaflow-ui');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->registerMiddlewareAliases();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/seviaflow-ui.php' => config_path('seviaflow-ui.php'),
        ], 'seviaflow-ui-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'seviaflow-ui-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/seviaflow-ui'),
        ], 'seviaflow-ui-views');

        $this->publishes([
            __DIR__.'/../public/dist' => public_path('vendor/seviaflow/ui'),
        ], 'seviaflow-ui-assets');
    }

    private function registerDashboardBindings(): void
    {
        $this->app->singleton(DashboardRegistry::class, fn (): DashboardRegistry => new DashboardRegistry);
        $this->app->singleton(DashboardWidgetResolver::class, fn (): DashboardWidgetResolver => new DashboardWidgetResolver);
        $this->app->singleton(DashboardLayoutRepository::class, fn (): DashboardLayoutRepository => new DashboardLayoutRepository);
        $this->app->singleton(SavedDashboardRepository::class, fn (): SavedDashboardRepository => new SavedDashboardRepository);
        $this->app->singleton(WidgetCache::class, fn (): WidgetCache => new WidgetCache);
        $this->app->singleton(DashboardSummaryCache::class, fn (): DashboardSummaryCache => new DashboardSummaryCache);
        $this->app->singleton(
            DashboardLayoutStore::class,
            fn ($app): DashboardLayoutStore => new DashboardLayoutStore(
                repository: $app->make(DashboardLayoutRepository::class),
                widgetResolver: $app->make(DashboardWidgetResolver::class),
                savedDashboardRepository: $app->make(SavedDashboardRepository::class),
            )
        );
        $this->app->singleton(
            SavedDashboardLayoutStore::class,
            fn ($app): SavedDashboardLayoutStore => new SavedDashboardLayoutStore(
                repository: $app->make(SavedDashboardRepository::class),
                layoutStore: $app->make(DashboardLayoutStore::class),
            )
        );
    }

    private function registerNavigationBindings(): void
    {
        $this->app->singleton(NavigationMetadataResolver::class, function ($app): NavigationMetadataResolver {
            return new NavigationMetadataResolver(
                $app->make(ResourceRegistry::class),
                $app->make(DashboardRegistry::class),
                $app->tagged(NavigationContributor::class),
            );
        });
        $this->app->singleton(
            NavigationMetadataCache::class,
            fn ($app): NavigationMetadataCache => new NavigationMetadataCache($app->make(NavigationMetadataResolver::class))
        );
    }

    private function registerCacheParticipants(): void
    {
        $this->app->singleton(NavigationMetadataCacheParticipant::class);
        $this->app->singleton(DashboardSummaryCacheParticipant::class);
        $this->app->singleton(DashboardWidgetCacheParticipant::class);
        $this->app->tag([
            NavigationMetadataCacheParticipant::class,
            DashboardSummaryCacheParticipant::class,
            DashboardWidgetCacheParticipant::class,
        ], CacheParticipant::class);
    }

    private function registerMiddlewareAliases(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('seviaflow.auth', AdminAuthenticate::class);
        $router->aliasMiddleware('seviaflow.guest', AdminRedirectIfAuthenticated::class);
    }
}
