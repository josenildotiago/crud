<?php

namespace Crud;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;

class CrudManager
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get all available themes.
     */
    public function getThemes(): Collection
    {
        $themesPath = resource_path('js/lib/themes.ts');

        if (!file_exists($themesPath)) {
            return collect();
        }

        // Parse themes from TypeScript file
        $content = file_get_contents($themesPath);
        // This is a simplified parser - in a real implementation you might want to use a proper TS parser
        preg_match_all('/id:\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);

        return collect($matches[1] ?? []);
    }

    /**
     * Check if theme system is installed.
     */
    public function isThemeSystemInstalled(): bool
    {
        return file_exists(resource_path('js/lib/themes.ts')) &&
            file_exists(resource_path('js/hooks/use-appearance.tsx'));
    }

    /**
     * Get CRUD configuration.
     */
    public function getConfig(?string $key = null): mixed
    {
        return $key ? config("crud.{$key}") : config('crud');
    }
}
