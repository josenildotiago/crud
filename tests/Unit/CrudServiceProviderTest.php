<?php

namespace Crud\Tests\Unit;

use Crud\CrudServiceProvider;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Achado 6: a tag de publish `crud-palette` publicava `src/stubs/palette` para
 * `resources/js/crud-palette/`, mas `InstallPaletteCommand` lê sempre de
 * `__DIR__.'/../stubs/palette/'` e nunca consulta `crud.stub_path` nem o destino
 * publicado. Quem publicasse para customizar não mudava nada — API morta numa release
 * major, onde tag de publish é API pública. Removida em vez de mantida morta.
 */
class CrudServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    public function test_tag_crud_config_continua_publicando(): void
    {
        $paths = ServiceProvider::pathsToPublish(CrudServiceProvider::class, 'crud-config');

        $this->assertNotEmpty($paths);
    }

    public function test_tag_crud_palette_nao_existe_mais(): void
    {
        $paths = ServiceProvider::pathsToPublish(CrudServiceProvider::class, 'crud-palette');

        $this->assertEmpty($paths);
    }
}
