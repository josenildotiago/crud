<?php

namespace Crud;

use Illuminate\Contracts\Foundation\Application;

class CrudManager
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * A camada de paleta está instalada neste projeto?
     */
    public function isPaletteInstalled(): bool
    {
        return file_exists(resource_path('js/lib/crud-palette.ts'));
    }

    /**
     * Get CRUD configuration.
     */
    public function getConfig(?string $key = null): mixed
    {
        return $key ? config("crud.{$key}") : config('crud');
    }
}
