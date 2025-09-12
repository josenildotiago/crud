<?php

namespace Crud;

use Crud\Console\InstallCommand;
use Crud\Console\CreateThemeCommand;
use Illuminate\Support\ServiceProvider;
use Crud\Console\InstallThemeSystemCommand;
use Crud\Console\InstallOnlyServicesCommand;

class CrudServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/crud.php', 'crud');
        $this->mergeConfigFrom(__DIR__ . '/config/themes.php', 'themes');

        $this->app->singleton('crud', function ($app) {
            return new \Crud\CrudManager($app);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CreateThemeCommand::class,
                InstallThemeSystemCommand::class,
                InstallOnlyServicesCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/config/crud.php' => config_path('crud.php'),
                __DIR__ . '/config/themes.php' => config_path('themes.php'),
            ], 'crud-config');

            $this->publishes([
                __DIR__ . '/stubs/js' => resource_path('js/crud'),
                __DIR__ . '/stubs/css' => resource_path('css/crud'),
            ], 'crud-assets');

            $this->publishes([
                __DIR__ . '/stubs/react' => resource_path('js'),
            ], 'theme-system');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['crud'];
    }
}
