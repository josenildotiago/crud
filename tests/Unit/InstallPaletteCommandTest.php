<?php

namespace Crud\Tests\Unit;

use Crud\CrudServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

class InstallPaletteCommandTest extends TestCase
{
    private string $base;

    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/crud-palette-' . uniqid();
        (new Filesystem())->makeDirectory($this->base . '/resources/js/pages/settings', 0755, true);
        (new Filesystem())->makeDirectory($this->base . '/resources/css', 0755, true);
        // A deteccao de stack e pela pagina de aparencia: sem ela, todo teste desta classe
        // cairia no erro de "nao identifiquei a stack". Os casos de vue e svelte apagam
        // este arquivo e criam o equivalente deles.
        (new Filesystem())->put(
            $this->base . '/resources/js/pages/settings/appearance.tsx',
            "import AppearanceTabs from '@/components/appearance-tabs';\n<AppearanceTabs />"
        );
        $this->app->setBasePath($this->base);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->base);

        parent::tearDown();
    }

    public function test_escreve_os_arquivos_compartilhados_e_o_seletor_react(): void
    {
        $this->artisan('crud:install-palette')->assertExitCode(0);

        $files = new Filesystem();

        $this->assertTrue($files->exists($this->base . '/resources/css/crud-palettes.css'));
        $this->assertTrue($files->exists($this->base . '/resources/js/lib/crud-palette.ts'));
        $this->assertTrue($files->exists($this->base . '/resources/js/components/crud-palette-selector.tsx'));
    }

    public function test_projeto_vue_recebe_o_seletor_vue(): void
    {
        $files = new Filesystem();
        $files->delete($this->base . '/resources/js/pages/settings/appearance.tsx');
        $files->put($this->base . '/resources/js/pages/settings/Appearance.vue', '<template><AppearanceTabs /></template>');

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $this->assertTrue($files->exists($this->base . '/resources/js/components/CrudPaletteSelector.vue'));
        $this->assertFalse($files->exists($this->base . '/resources/js/components/crud-palette-selector.tsx'));
    }

    public function test_projeto_svelte_recebe_o_seletor_svelte(): void
    {
        $files = new Filesystem();
        $files->delete($this->base . '/resources/js/pages/settings/appearance.tsx');
        $files->put($this->base . '/resources/js/pages/settings/Appearance.svelte', '<div><AppearanceTabs /></div>');

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $this->assertTrue($files->exists($this->base . '/resources/js/components/CrudPaletteSelector.svelte'));
        $this->assertFalse($files->exists($this->base . '/resources/js/components/crud-palette-selector.tsx'));
    }

    public function test_o_css_traz_as_quatro_paletas_em_claro_e_escuro(): void
    {
        $this->artisan('crud:install-palette')->assertExitCode(0);

        $css = (new Filesystem())->get($this->base . '/resources/css/crud-palettes.css');

        foreach (['azul', 'verde', 'roxo', 'vermelho'] as $palette) {
            $this->assertStringContainsString(":root[data-crud-palette='{$palette}']", $css);
            $this->assertStringContainsString(":root.dark[data-crud-palette='{$palette}']", $css);
        }

        $this->assertStringNotContainsString("data-crud-palette='default'", $css);
    }

    public function test_nao_sobrescreve_sem_force(): void
    {
        $files = new Filesystem();
        $files->makeDirectory($this->base . '/resources/js/lib', 0755, true);
        $files->put($this->base . '/resources/js/lib/crud-palette.ts', 'MEU CONTEUDO');

        $this->artisan('crud:install-palette')
            ->expectsConfirmation('O arquivo resources/js/lib/crud-palette.ts já existe. Sobrescrever?', 'no')
            ->assertExitCode(0);

        $this->assertSame('MEU CONTEUDO', $files->get($this->base . '/resources/js/lib/crud-palette.ts'));
    }

    public function test_stack_invalida_falha(): void
    {
        $this->artisan('crud:install-palette --stack=angular')->assertExitCode(1);

        $this->assertFalse((new Filesystem())->exists($this->base . '/resources/js/lib/crud-palette.ts'));
    }

    public function test_projeto_que_nao_e_nenhuma_das_tres_falha_dizendo_o_que_fazer(): void
    {
        (new Filesystem())->delete($this->base . '/resources/js/pages/settings/appearance.tsx');

        $this->artisan('crud:install-palette')
            ->expectsOutputToContain('Não identifiquei a stack deste projeto')
            ->assertExitCode(1);
    }

    public function test_stack_pedida_diferente_da_detectada_pergunta_antes(): void
    {
        // O projeto é react (a página do setUp), mas o usuário pediu vue.
        $this->artisan('crud:install-palette --stack=vue')
            ->expectsConfirmation(
                'Você pediu `vue`, mas este projeto parece `react`. Instalar `vue` assim mesmo?',
                'no'
            )
            ->assertExitCode(1);

        $this->assertFalse((new Filesystem())->exists($this->base . '/resources/js/lib/crud-palette.ts'));
    }

    public function test_stack_pedida_diferente_e_confirmada_instala_a_pedida(): void
    {
        $this->artisan('crud:install-palette --stack=vue')
            ->expectsConfirmation(
                'Você pediu `vue`, mas este projeto parece `react`. Instalar `vue` assim mesmo?',
                'yes'
            )
            ->assertExitCode(0);

        $this->assertTrue((new Filesystem())->exists($this->base . '/resources/js/components/CrudPaletteSelector.vue'));
    }

    public function test_stack_detectada_nao_pergunta_nada(): void
    {
        $this->artisan('crud:install-palette')
            ->doesntExpectOutputToContain('assim mesmo?')
            ->assertExitCode(0);
    }
}
