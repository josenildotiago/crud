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

            // Achado 6: não existe tag `crud-palette`. Publicar `src/stubs/palette` para
            // `resources/js/crud-palette/` não tinha efeito nenhum — InstallPaletteCommand
            // lê sempre de `__DIR__.'/../stubs/palette/'` e nunca consulta
            // `crud.stub_path` nem esse destino publicado. Tag de publish é API pública
            // (item 3 de "API pública" no CLAUDE.md); numa release major, o certo é
            // remover, não deixar API morta.
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
