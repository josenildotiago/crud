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

    /**
     * Achado 3: `--force` sobrescrevia `crud-palettes.css` e `crud-palette.ts` a partir do
     * stub sem avisar, levando junto qualquer paleta que `crud:create-palette` tenha
     * acrescentado. A perda é de dado do usuário, não de arquivo reinstalável — por isso
     * o comando tem que perguntar mesmo com `--force`, que só pula a confirmação de
     * sobrescrita comum.
     */
    public function test_force_com_paleta_extra_avisa_perda_e_recusar_mantem(): void
    {
        $this->artisan('crud:install-palette')->assertExitCode(0);
        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(0);

        $files = new Filesystem();
        $tsPath = $this->base . '/resources/js/lib/crud-palette.ts';
        $cssPath = $this->base . '/resources/css/crud-palettes.css';

        $this->artisan('crud:install-palette --force')
            ->expectsOutputToContain('laranja')
            ->expectsConfirmation('Sobrescrever mesmo assim?', 'no')
            ->expectsConfirmation('Sobrescrever mesmo assim?', 'no')
            ->assertExitCode(0);

        $this->assertStringContainsString("id: 'laranja'", $files->get($tsPath));
        $this->assertStringContainsString("data-crud-palette='laranja'", $files->get($cssPath));
    }

    public function test_force_com_paleta_extra_confirmado_apaga_a_paleta(): void
    {
        $this->artisan('crud:install-palette')->assertExitCode(0);
        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(0);

        $files = new Filesystem();
        $tsPath = $this->base . '/resources/js/lib/crud-palette.ts';
        $cssPath = $this->base . '/resources/css/crud-palettes.css';

        $this->artisan('crud:install-palette --force')
            ->expectsConfirmation('Sobrescrever mesmo assim?', 'yes')
            ->expectsConfirmation('Sobrescrever mesmo assim?', 'yes')
            ->assertExitCode(0);

        $this->assertStringNotContainsString("id: 'laranja'", $files->get($tsPath));
        $this->assertStringNotContainsString("data-crud-palette='laranja'", $files->get($cssPath));
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

    /**
     * Achado 2: `MarkedRegion::IMPORT` não casava `;\r`, então `insertImport()` devolvia
     * null em projeto CRLF e `editAppTsx()`/`editAppearancePage()` desistiam de editar —
     * mas `editAppCss()` não passa pela `MarkedRegion` e escrevia mesmo assim, misturando
     * uma linha LF solta no meio de um arquivo CRLF, sem erro que explicasse. Este teste
     * cobre o projeto inteiro em CRLF: as três edições têm que acontecer, e o resultado
     * tem que continuar CRLF, sem `\n` solto em lugar nenhum.
     */
    public function test_projeto_crlf_recebe_as_tres_edicoes_e_continua_crlf(): void
    {
        $files = new Filesystem();
        $crlf = static fn (string $s): string => str_replace("\n", "\r\n", $s);

        $files->put($this->base . '/resources/css/app.css', $crlf(
            "@import 'tailwindcss';\n\n@import 'tw-animate-css';\n"
        ));
        $files->put($this->base . '/resources/js/app.tsx', $crlf(
            "import { createInertiaApp } from '@inertiajs/react';\n"
                . "import AppLayout from '@/layouts/app-layout';\n\n"
                . "createInertiaApp({});\n\n"
                . "initializeTheme();\n"
        ));
        $files->put($this->base . '/resources/js/pages/settings/appearance.tsx', $crlf(
            "import AppearanceTabs from '@/components/appearance-tabs';\n\n"
                . "export default function Appearance() {\n"
                . "    return (\n"
                . "        <div className=\"space-y-6\">\n"
                . "            <AppearanceTabs />\n"
                . "        </div>\n"
                . "    );\n"
                . "}\n"
        ));

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $css = $files->get($this->base . '/resources/css/app.css');
        $tsx = $files->get($this->base . '/resources/js/app.tsx');
        $page = $files->get($this->base . '/resources/js/pages/settings/appearance.tsx');

        $this->assertStringContainsString("@import './crud-palettes.css';", $css);
        $this->assertStringContainsString('initializeCrudPalette();', $tsx);
        $this->assertStringContainsString('<CrudPaletteSelector />', $page);

        foreach (['css' => $css, 'app.tsx' => $tsx, 'appearance.tsx' => $page] as $rotulo => $conteudo) {
            $this->assertSame(0, preg_match('/(?<!\r)\n/', $conteudo), "{$rotulo} tem \\n solto misturado em arquivo CRLF.");
        }
    }

    private function putAppCssVue(): void
    {
        (new Filesystem())->put($this->base . '/resources/css/app.css', <<<'CSS'
        @import 'tailwindcss';

        @import 'tw-animate-css';

        @custom-variant dark (&:is(.dark *));
        CSS);
    }

    private function putAppTsVue(): void
    {
        (new Filesystem())->put($this->base . '/resources/js/app.ts', <<<'TS'
        import { createApp } from 'vue';
        import { initializeTheme } from '@/hooks/use-appearance';
        import AppLayout from '@/layouts/AppLayout.vue';

        createApp({}).mount('#app');

        // This will set light / dark mode on load...
        initializeTheme();
        TS);
    }

    private function putAppearancePageVue(): void
    {
        (new Filesystem())->put($this->base . '/resources/js/pages/settings/Appearance.vue', <<<'VUE'
        <template>
            <div class="space-y-6">
                <AppearanceTabs />
            </div>
        </template>

        <script setup lang="ts">
        import AppearanceTabs from '@/components/appearance-tabs';
        </script>
        VUE);
    }

    private function putAppearancePageVueWithCrudList(): void
    {
        (new Filesystem())->put($this->base . '/resources/js/pages/settings/Appearance.vue', <<<'VUE'
        <template>
            <div class="space-y-6">
                <AppearanceTabs />
            </div>
        </template>

        <script setup lang="ts">
        import AppearanceTabs from '@/components/appearance-tabs';
        import CrudList from '@/components/CrudList.vue';
        import Heading from '@/components/Heading.vue';
        </script>
        VUE);
    }

    /**
     * Forma real da página no starter kit svelte: dois blocos `<script>`, com import
     * recuado em 4 espaços nos dois. `<script module>` traz o import da rota do
     * breadcrumb (`@/routes/appearance`, também `@/`) — é o que expôs o bug do achado 1:
     * o `insertImport()` varria o arquivo inteiro e achava esse import primeiro, inserindo
     * a linha nova ali, sem recuo, em vez de ir para o bloco de instância junto dos
     * outros imports de componente. Ver
     * `/home/sp1d3r/Documentos/projetos/pacotes/laravel/projeto-exemplo-svelte/resources/js/pages/settings/Appearance.svelte`
     * (estrutura, não o conteúdo — aquele arquivo já foi editado pela instalação).
     */
    private function putAppearancePageSvelte(): void
    {
        (new Filesystem())->put($this->base . '/resources/js/pages/settings/Appearance.svelte', <<<'SVELTE'
        <script module lang="ts">
            import { edit as editAppearance } from '@/routes/appearance';

            export const layout = {
                breadcrumbs: [
                    {
                        title: 'Appearance settings',
                        href: editAppearance(),
                    },
                ],
            };
        </script>

        <script lang="ts">
            import AppearanceTabs from '@/components/AppearanceTabs.svelte';
            import AppHead from '@/components/AppHead.svelte';
            import Heading from '@/components/Heading.svelte';
        </script>

        <AppHead title="Appearance settings" />

        <div class="space-y-6">
            <AppearanceTabs />
        </div>
        SVELTE);
    }

    public function test_vue_acrescenta_o_import_do_css(): void
    {
        $files = new Filesystem();
        $files->delete($this->base . '/resources/js/pages/settings/appearance.tsx');
        $this->putAppearancePageVue();
        $this->putAppCssVue();

        $this->artisan('crud:install-palette --stack=vue')->assertExitCode(0);

        $css = $files->get($this->base . '/resources/css/app.css');

        $this->assertStringContainsString(
            "@import 'tw-animate-css';\n@import './crud-palettes.css';",
            $css
        );
    }

    public function test_vue_acrescenta_a_chamada_de_inicializacao_no_app_ts(): void
    {
        $files = new Filesystem();
        $files->delete($this->base . '/resources/js/pages/settings/appearance.tsx');
        $this->putAppearancePageVue();
        $this->putAppTsVue();

        $this->artisan('crud:install-palette --stack=vue')->assertExitCode(0);

        $ts = $files->get($this->base . '/resources/js/app.ts');

        $this->assertStringContainsString(
            "import AppLayout from '@/layouts/AppLayout.vue';\nimport { initializeCrudPalette } from '@/lib/crud-palette';",
            $ts
        );
        $this->assertStringContainsString("initializeTheme();\ninitializeCrudPalette();", $ts);
    }

    public function test_vue_insere_o_seletor_na_pagina_de_aparencia(): void
    {
        $files = new Filesystem();
        $files->delete($this->base . '/resources/js/pages/settings/appearance.tsx');
        $this->putAppearancePageVue();

        $this->artisan('crud:install-palette --stack=vue')->assertExitCode(0);

        $page = $files->get($this->base . '/resources/js/pages/settings/Appearance.vue');

        $this->assertStringContainsString('<!-- crud:palette:start -->', $page);
        $this->assertStringContainsString('<CrudPaletteSelector />', $page);
        $this->assertStringContainsString(
            "import CrudPaletteSelector from '@/components/CrudPaletteSelector.vue';",
            $page
        );
    }

    public function test_vue_import_em_ordem_correta_com_vizinhos(): void
    {
        $files = new Filesystem();
        $files->delete($this->base . '/resources/js/pages/settings/appearance.tsx');
        $this->putAppearancePageVueWithCrudList();

        $this->artisan('crud:install-palette --stack=vue')->assertExitCode(0);

        $page = $files->get($this->base . '/resources/js/pages/settings/Appearance.vue');

        // A ordem esperada: AppearanceTabs, CrudList, CrudPaletteSelector, Heading
        // CrudPaletteSelector vem depois de CrudList (C... vs C...) por ordem alfabética
        $lines = explode("\n", $page);
        $imports = array_filter($lines, fn ($line) => str_starts_with(trim($line), 'import'));

        $importsStr = implode("\n", $imports);

        $this->assertStringContainsString(
            "import CrudList from '@/components/CrudList.vue';\nimport CrudPaletteSelector from '@/components/CrudPaletteSelector.vue';",
            $importsStr
        );
    }

    public function test_svelte_acrescenta_o_import_do_css(): void
    {
        $files = new Filesystem();
        $files->delete($this->base . '/resources/js/pages/settings/appearance.tsx');
        $this->putAppearancePageSvelte();
        $this->putAppCssVue();

        $this->artisan('crud:install-palette --stack=svelte')->assertExitCode(0);

        $css = $files->get($this->base . '/resources/css/app.css');

        $this->assertStringContainsString(
            "@import 'tw-animate-css';\n@import './crud-palettes.css';",
            $css
        );
    }

    public function test_svelte_acrescenta_a_chamada_de_inicializacao_no_app_ts(): void
    {
        $files = new Filesystem();
        $files->delete($this->base . '/resources/js/pages/settings/appearance.tsx');
        $this->putAppearancePageSvelte();
        $this->putAppTsVue();

        $this->artisan('crud:install-palette --stack=svelte')->assertExitCode(0);

        $ts = $files->get($this->base . '/resources/js/app.ts');

        $this->assertStringContainsString(
            "import AppLayout from '@/layouts/AppLayout.vue';\nimport { initializeCrudPalette } from '@/lib/crud-palette';",
            $ts
        );
        $this->assertStringContainsString("initializeTheme();\ninitializeCrudPalette();", $ts);
    }

    public function test_svelte_insere_o_seletor_na_pagina_de_aparencia(): void
    {
        $files = new Filesystem();
        $files->delete($this->base . '/resources/js/pages/settings/appearance.tsx');
        $this->putAppearancePageSvelte();

        $this->artisan('crud:install-palette --stack=svelte')->assertExitCode(0);

        $page = $files->get($this->base . '/resources/js/pages/settings/Appearance.svelte');

        $this->assertStringContainsString('<!-- crud:palette:start -->', $page);
        $this->assertStringContainsString('<CrudPaletteSelector />', $page);

        // O import tem que herdar o recuo de 4 espaços dos vizinhos do bloco de
        // instância (achado 1) — sem recuo, `npx prettier --check` reprova o arquivo.
        $this->assertStringContainsString(
            "    import CrudPaletteSelector from '@/components/CrudPaletteSelector.svelte';",
            $page
        );

        // E tem que entrar no bloco `<script lang="ts">` de instância, junto dos outros
        // imports de componente — não no `<script module lang="ts">` de cima, que só tem
        // o import da rota do breadcrumb.
        $this->assertStringContainsString(
            "    import AppHead from '@/components/AppHead.svelte';\n"
                . "    import CrudPaletteSelector from '@/components/CrudPaletteSelector.svelte';\n"
                . "    import Heading from '@/components/Heading.svelte';",
            $page
        );

        $moduleBlockEnd = strpos($page, '</script>');
        $importPosition = strpos($page, "import CrudPaletteSelector from '@/components/CrudPaletteSelector.svelte';");
        $this->assertGreaterThan(
            $moduleBlockEnd,
            $importPosition,
            'O import da paleta entrou no bloco <script module>, e não no bloco de instância.'
        );
    }

    private function putLegacy(): void
    {
        $files = new Filesystem();
        $files->makeDirectory($this->base . '/resources/js/lib', 0755, true);
        $files->makeDirectory($this->base . '/resources/js/hooks', 0755, true);
        $files->makeDirectory($this->base . '/resources/js/components', 0755, true);
        $files->put($this->base . '/resources/js/lib/themes.ts', 'export const themes = [];');
        $files->put($this->base . '/resources/js/components/theme-selector.tsx', 'antigo');
        $files->put($this->base . '/resources/js/hooks/use-appearance.tsx', 'do starter kit, sobrescrito');
    }

    public function test_oferece_apagar_so_os_arquivos_do_pacote(): void
    {
        $this->putLegacy();

        $this->artisan('crud:install-palette')
            ->expectsConfirmation('Apagar os arquivos que a versão antiga instalou?', 'yes')
            ->assertExitCode(0);

        $files = new Filesystem();

        $this->assertFalse($files->exists($this->base . '/resources/js/lib/themes.ts'));
        $this->assertFalse($files->exists($this->base . '/resources/js/components/theme-selector.tsx'));
    }

    public function test_nunca_toca_no_que_e_do_starter_kit(): void
    {
        $this->putLegacy();

        $this->artisan('crud:install-palette')
            ->expectsConfirmation('Apagar os arquivos que a versão antiga instalou?', 'yes')
            ->assertExitCode(0);

        $files = new Filesystem();

        $this->assertTrue($files->exists($this->base . '/resources/js/hooks/use-appearance.tsx'));
        $this->assertSame(
            'do starter kit, sobrescrito',
            $files->get($this->base . '/resources/js/hooks/use-appearance.tsx')
        );
    }

    public function test_recusar_a_limpeza_mantem_tudo(): void
    {
        $this->putLegacy();

        $this->artisan('crud:install-palette')
            ->expectsConfirmation('Apagar os arquivos que a versão antiga instalou?', 'no')
            ->assertExitCode(0);

        $this->assertTrue((new Filesystem())->exists($this->base . '/resources/js/lib/themes.ts'));
    }

    public function test_projeto_sem_legado_nao_avisa_nada(): void
    {
        $this->artisan('crud:install-palette')
            ->doesntExpectOutputToContain('Encontrei o sistema de temas antigo neste projeto.')
            ->assertExitCode(0);
    }

    /**
     * Achado 4: apagar `theme-selector.tsx` quebra a build de qualquer página CRUD gerada
     * com a antiga flag `--theme` — ela ainda tem `import ThemeSelector from
     * '@/components/theme-selector'`. O comando não sabe quais páginas foram geradas
     * assim, então tem que avisar de qualquer forma, não silenciar o risco.
     */
    public function test_avisa_que_paginas_geradas_com_theme_precisam_ser_regeradas(): void
    {
        $this->putLegacy();

        // Os dois módulos vivem na mesma linha (um só `warn()`), então uma única
        // asserção cobrindo os dois: duas chamadas a `expectsOutputToContain()` para
        // texto dentro da mesma linha colidem no mock de teste do Artisan — só a
        // primeira reclama a chamada, e a segunda nunca vê o `doWrite()` de novo.
        $this->artisan('crud:install-palette')
            ->expectsOutputToContain('@/components/theme-selector` e `@/hooks/use-appearance')
            ->expectsConfirmation('Apagar os arquivos que a versão antiga instalou?', 'no')
            ->assertExitCode(0);
    }
}
