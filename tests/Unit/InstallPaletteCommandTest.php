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

    private function putAppCss(): void
    {
        (new Filesystem())->put($this->base . '/resources/css/app.css', <<<'CSS'
        @import 'tailwindcss';

        @import 'tw-animate-css';

        @custom-variant dark (&:is(.dark *));
        CSS);
    }

    private function putAppTsx(): void
    {
        (new Filesystem())->put($this->base . '/resources/js/app.tsx', <<<'TSX'
        import { createInertiaApp } from '@inertiajs/react';
        import { initializeTheme } from '@/hooks/use-appearance';
        import AppLayout from '@/layouts/app-layout';

        createInertiaApp({});

        // This will set light / dark mode on load...
        initializeTheme();
        TSX);
    }

    private function putAppearancePage(): void
    {
        (new Filesystem())->put($this->base . '/resources/js/pages/settings/appearance.tsx', <<<'TSX'
        import { Head } from '@inertiajs/react';
        import AppearanceTabs from '@/components/appearance-tabs';
        import Heading from '@/components/heading';

        export default function Appearance() {
            return (
                <div className="space-y-6">
                    <AppearanceTabs />
                </div>
            );
        }
        TSX);
    }

    public function test_acrescenta_o_import_do_css_depois_do_ultimo_import(): void
    {
        $this->putAppCss();

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $css = (new Filesystem())->get($this->base . '/resources/css/app.css');

        $this->assertStringContainsString(
            "@import 'tw-animate-css';\n@import './crud-palettes.css';",
            $css
        );
    }

    public function test_acrescenta_a_chamada_de_inicializacao_no_app_tsx(): void
    {
        $this->putAppTsx();

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $tsx = (new Filesystem())->get($this->base . '/resources/js/app.tsx');

        $this->assertStringContainsString(
            "import AppLayout from '@/layouts/app-layout';\nimport { initializeCrudPalette } from '@/lib/crud-palette';",
            $tsx
        );
        $this->assertStringContainsString("initializeTheme();\ninitializeCrudPalette();", $tsx);
    }

    public function test_insere_o_seletor_na_pagina_de_aparencia(): void
    {
        $this->putAppearancePage();

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $page = (new Filesystem())->get($this->base . '/resources/js/pages/settings/appearance.tsx');

        $this->assertStringContainsString('{/* crud:palette:start */}', $page);
        $this->assertStringContainsString('<CrudPaletteSelector />', $page);
        $this->assertStringContainsString(
            "import { CrudPaletteSelector } from '@/components/crud-palette-selector';",
            $page
        );
    }

    public function test_rodar_duas_vezes_nao_muda_nada(): void
    {
        $this->putAppCss();
        $this->putAppTsx();
        $this->putAppearancePage();

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $files = new Filesystem();
        $primeira = [
            $files->get($this->base . '/resources/css/app.css'),
            $files->get($this->base . '/resources/js/app.tsx'),
            $files->get($this->base . '/resources/js/pages/settings/appearance.tsx'),
        ];

        $this->artisan('crud:install-palette --force')->assertExitCode(0);

        $this->assertSame($primeira[0], $files->get($this->base . '/resources/css/app.css'));
        $this->assertSame($primeira[1], $files->get($this->base . '/resources/js/app.tsx'));
        $this->assertSame($primeira[2], $files->get($this->base . '/resources/js/pages/settings/appearance.tsx'));
    }

    public function test_sem_ancora_nao_escreve_e_avisa(): void
    {
        (new Filesystem())->put($this->base . '/resources/js/pages/settings/appearance.tsx', '<div>outra coisa</div>');

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $page = (new Filesystem())->get($this->base . '/resources/js/pages/settings/appearance.tsx');

        $this->assertSame('<div>outra coisa</div>', $page);
    }

    public function test_config_desliga_a_edicao_da_pagina(): void
    {
        $this->putAppearancePage();
        config()->set('crud.palette.settings_page', false);

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $page = (new Filesystem())->get($this->base . '/resources/js/pages/settings/appearance.tsx');

        $this->assertStringNotContainsString('crud:palette:start', $page);
    }
}
