<?php

namespace Crud;

use Crud\Console\InstallCommand;
use Illuminate\Support\ServiceProvider;
use Crud\Console\InstallPaletteCommand;
use Crud\Console\InstallOnlyServicesCommand;
use Crud\Console\CreatePaletteCommand;

class CrudServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/crud.php', 'crud');

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
                InstallPaletteCommand::class,
                InstallOnlyServicesCommand::class,
                CreatePaletteCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/config/crud.php' => config_path('crud.php'),
            ], 'crud-config');

            $this->publishes([
                __DIR__ . '/stubs/palette' => resource_path('js/crud-palette'),
            ], 'crud-palette');
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
