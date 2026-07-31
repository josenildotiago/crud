<?php

namespace Crud\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isPaletteInstalled()
 * @method static mixed getConfig(?string $key = null)
 */
class Crud extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'crud';
    }
}
