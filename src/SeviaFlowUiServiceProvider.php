<?php

declare(strict_types=1);

namespace SeviaFlow\Ui;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use SeviaFlow\Core\Events\Auth\RoleAssignmentsChanged;
use SeviaFlow\Core\Events\Resources\ResourceCreated;
use SeviaFlow\Core\Events\Resources\ResourceDeleted;
use SeviaFlow\Core\Events\Resources\ResourceRestored;
use SeviaFlow\Core\Events\Resources\ResourceUpdated;
use SeviaFlow\Core\Resources\Fields\DateField;
use SeviaFlow\Core\Resources\Fields\DateTimeField;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\RepeaterField;
use SeviaFlow\Core\Resources\Fields\RichTextField;
use SeviaFlow\Core\Resources\Fields\TimeField;
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
use SeviaFlow\Ui\Fields\FieldRendererRegistry;
use SeviaFlow\Ui\Http\Middleware\AdminAuthenticate;
use SeviaFlow\Ui\Http\Middleware\AdminRedirectIfAuthenticated;
use SeviaFlow\Ui\Navigation\NavigationContributor;
use SeviaFlow\Ui\Navigation\NavigationMetadataCache;
use SeviaFlow\Ui\Navigation\NavigationMetadataResolver;
use SeviaFlow\Ui\Support\Cache\CacheInvalidator;
use SeviaFlow\Ui\Support\Cache\CacheVersionStore;
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
        $this->registerCacheInvalidation();
        $this->registerFieldRendererBindings();
    }

    public function boot(CapabilityRegistry $capabilities): void
    {
        $this->registerCacheListeners();
        RateLimiter::for('seviaflow.login', function ($request): Limit {
            $limit = max(1, (int) config('seviaflow-ui.auth.login_throttle.max_attempts', 5));
            $decay = max(1, (int) config('seviaflow-ui.auth.login_throttle.decay_minutes', 1));
            $email = strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinutes($decay, $limit)->by('seviaflow-login|'.$email.'|'.$request->ip());
        });
        RateLimiter::for('seviaflow.two-factor', function ($request): Limit {
            $limit = max(1, (int) config('seviaflow-ui.auth.login_throttle.max_attempts', 5));
            $decay = max(1, (int) config('seviaflow-ui.auth.login_throttle.decay_minutes', 1));
            $pending = $request->session()->get('seviaflow.auth.pending_two_factor');
            $key = is_array($pending) ? (string) ($pending['id'] ?? '') : (string) $request->user()?->getAuthIdentifier();

            return Limit::perMinutes($decay, $limit)->by('seviaflow-two-factor|'.$key.'|'.$request->ip());
        });
        $capabilities->register('ui', (bool) config('seviaflow-ui.enabled', true), 'Operator-facing Livewire UI');
        $capabilities->register('dashboard', (bool) config('seviaflow-ui.dashboard.enabled', true), 'Dashboard UI');

        if (! (bool) config('seviaflow-ui.enabled', true)) {
            return;
        }

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'seviaflow-ui');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'seviaflow-ui');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->registerMiddlewareAliases();
        if (class_exists(Livewire::class)) {
            Livewire::addPersistentMiddleware(AdminAuthenticate::class);
        }

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

    private function registerCacheInvalidation(): void
    {
        $this->app->singleton(CacheVersionStore::class);
        $this->app->singleton(CacheInvalidator::class);
    }

    private function registerCacheListeners(): void
    {
        $events = $this->app['events'];
        foreach ([ResourceCreated::class, ResourceUpdated::class, ResourceDeleted::class, ResourceRestored::class] as $event) {
            $events->listen($event, [CacheInvalidator::class, 'resourceChanged']);
        }
        $events->listen(RoleAssignmentsChanged::class, [CacheInvalidator::class, 'roleAssignmentsChanged']);
    }

    private function registerFieldRendererBindings(): void
    {
        $this->app->singleton(FieldRendererRegistry::class, function (): FieldRendererRegistry {
            $registry = new FieldRendererRegistry;
            $inputView = 'seviaflow-ui::fields.controls.input';
            $showView = 'seviaflow-ui::fields.show.value';

            $registry
                ->register('text', $inputView, $showView, $showView, ['inputType' => 'text'])
                ->register('email', $inputView, $showView, $showView, ['inputType' => 'email'])
                ->register('url', $inputView, $showView, $showView, ['inputType' => 'url'])
                ->register('phone', $inputView, $showView, $showView, ['inputType' => 'tel'])
                ->register('slug', $inputView, $showView, $showView, ['inputType' => 'text'])
                ->register('number', $inputView, $showView, $showView, [
                    'inputType' => 'number',
                    'inputAttributes' => static fn (NumberField $field): array => array_filter([
                        'min' => $field->getMin(),
                        'max' => $field->getMax(),
                        'step' => $field->getStep(),
                    ], static fn (mixed $value): bool => $value !== null),
                ])
                ->register('date', $inputView, 'seviaflow-ui::fields.show.date', $showView, [
                    'inputType' => 'date',
                    'inputAttributes' => static fn (DateField $field): array => ['data-input-format' => $field->getInputFormat()],
                ])
                ->register('datetime', $inputView, $showView, $showView, [
                    'inputType' => 'datetime-local',
                    'inputAttributes' => static fn (DateTimeField $field): array => [
                        'data-input-format' => $field->getInputFormat(),
                        'data-timezone' => $field->getTimezone(),
                    ],
                ])
                ->register('time', $inputView, $showView, $showView, [
                    'inputType' => 'time',
                    'inputAttributes' => static fn (TimeField $field): array => [
                        'data-input-format' => $field->getInputFormat(),
                        'data-timezone' => $field->getTimezone(),
                    ],
                ])
                ->register('password', $inputView, $showView, $showView, ['inputType' => 'password'])
                ->register('textarea', 'seviaflow-ui::fields.controls.textarea', $showView, $showView)
                ->register('toggle', 'seviaflow-ui::fields.controls.toggle', 'seviaflow-ui::fields.show.toggle', 'seviaflow-ui::fields.show.toggle')
                ->register('select', 'seviaflow-ui::fields.controls.select', 'seviaflow-ui::fields.show.select', $showView)
                ->register('belongs-to-many', 'seviaflow-ui::fields.controls.multiselect', 'seviaflow-ui::fields.show.belongs-to-many', 'seviaflow-ui::fields.show.belongs-to-many')
                ->register('multiselect', 'seviaflow-ui::fields.controls.multiselect', $showView, $showView)
                ->register('belongs-to', 'seviaflow-ui::fields.controls.belongs-to', $showView, $showView)
                ->register('file', 'seviaflow-ui::fields.controls.file', $showView, $showView)
                ->register('image', 'seviaflow-ui::fields.controls.file', $showView, $showView)
                ->register('money', 'seviaflow-ui::fields.controls.money', 'seviaflow-ui::fields.show.money', 'seviaflow-ui::fields.show.money')
                ->register('radio-group', 'seviaflow-ui::fields.controls.radio-group', 'seviaflow-ui::fields.show.radio-group', 'seviaflow-ui::fields.show.radio-group')
                ->register('checkbox-group', 'seviaflow-ui::fields.controls.checkbox-group', 'seviaflow-ui::fields.show.checkbox-group', 'seviaflow-ui::fields.show.checkbox-group')
                ->register('tags', 'seviaflow-ui::fields.controls.tags', 'seviaflow-ui::fields.show.tags', 'seviaflow-ui::fields.show.tags')
                ->register('key-value', 'seviaflow-ui::fields.controls.key-value', 'seviaflow-ui::fields.show.key-value', 'seviaflow-ui::fields.show.key-value')
                ->register('json', 'seviaflow-ui::fields.controls.json', 'seviaflow-ui::fields.show.json', 'seviaflow-ui::fields.show.json')
                ->register('color', 'seviaflow-ui::fields.controls.color', 'seviaflow-ui::fields.show.color', 'seviaflow-ui::fields.show.color')
                ->register('rich-text', 'seviaflow-ui::fields.controls.rich-text', 'seviaflow-ui::fields.show.rich-text', 'seviaflow-ui::fields.show.rich-text', [
                    'inputType' => 'rich-text',
                    'inputAttributes' => static fn (RichTextField $field): array => ['data-max-bytes' => $field->getMaxBytes()],
                ])
                ->register('repeater', 'seviaflow-ui::fields.controls.repeater', 'seviaflow-ui::fields.show.repeater', 'seviaflow-ui::fields.show.repeater', [
                    'inputType' => 'repeater',
                    'inputAttributes' => static fn (RepeaterField $field): array => [
                        'data-max-items' => $field->getMaxItems(),
                        'data-max-bytes' => $field->getMaxBytes(),
                    ],
                ]);

            return $registry;
        });
    }

    private function registerMiddlewareAliases(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('seviaflow.auth', AdminAuthenticate::class);
        $router->aliasMiddleware('seviaflow.guest', AdminRedirectIfAuthenticated::class);
    }
}
