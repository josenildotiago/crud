<?php

namespace Crud\Tests\Unit;

use Crud\CrudServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

class CrudManagerTest extends TestCase
{
    private string $base;

    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/crud-manager-' . uniqid();
        (new Filesystem())->makeDirectory($this->base . '/resources/js/lib', 0755, true);
        $this->app->setBasePath($this->base);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->base);

        parent::tearDown();
    }

    public function test_sem_a_paleta_instalada(): void
    {
        $this->assertFalse(app('crud')->isPaletteInstalled());
    }

    public function test_com_a_paleta_instalada(): void
    {
        (new Filesystem())->put($this->base . '/resources/js/lib/crud-palette.ts', 'export const palettes = [];');

        $this->assertTrue(app('crud')->isPaletteInstalled());
    }

    public function test_a_config_global_de_temas_nao_existe_mais(): void
    {
        $this->assertNull(config('themes'));
    }
}
