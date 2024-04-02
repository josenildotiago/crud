<?php

namespace Crud;

use Illuminate\Support\ServiceProvider;
use Crud\Commands\CrudGenerator;

class CrudServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CrudGenerator::class,
            ]);
        }

        $this->publishes([
            __DIR__ . '/config/crud.php' => config_path('crud.php'),
        ], 'crud');
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
