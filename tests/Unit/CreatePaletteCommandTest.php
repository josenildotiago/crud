<?php

namespace Crud\Tests\Unit;

use Crud\CrudServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

class CreatePaletteCommandTest extends TestCase
{
    private string $base;

    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/crud-create-palette-' . uniqid();
        (new Filesystem())->makeDirectory($this->base . '/resources/css', 0755, true);
        // A stack e detectada pela pagina de aparencia; sem ela o install falha antes de escrever.
        (new Filesystem())->makeDirectory($this->base . '/resources/js/pages/settings', 0755, true);
        (new Filesystem())->put($this->base . '/resources/js/pages/settings/appearance.tsx', '<AppearanceTabs />');
        $this->app->setBasePath($this->base);
        $this->artisan('crud:install-palette')->run();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->base);

        parent::tearDown();
    }

    public function test_acrescenta_os_dois_blocos_no_css(): void
    {
        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(0);

        $css = (new Filesystem())->get($this->base . '/resources/css/crud-palettes.css');

        $this->assertStringContainsString(":root[data-crud-palette='laranja']", $css);
        $this->assertStringContainsString(":root.dark[data-crud-palette='laranja']", $css);
        $this->assertStringContainsString('oklch(0.55 0.19 70)', $css);
    }

    public function test_acrescenta_a_entrada_na_lista(): void
    {
        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(0);

        $ts = (new Filesystem())->get($this->base . '/resources/js/lib/crud-palette.ts');

        $this->assertStringContainsString("{ id: 'laranja', name: 'Laranja' },", $ts);
    }

    public function test_id_repetido_falha_sem_escrever(): void
    {
        $this->artisan('crud:create-palette Azul --hue=250')->assertExitCode(1);

        $css = (new Filesystem())->get($this->base . '/resources/css/crud-palettes.css');

        $this->assertSame(2, substr_count($css, "data-crud-palette='azul'"));
    }

    public function test_matiz_fora_da_faixa_falha(): void
    {
        $this->artisan('crud:create-palette Laranja --hue=400')->assertExitCode(1);
    }
}
