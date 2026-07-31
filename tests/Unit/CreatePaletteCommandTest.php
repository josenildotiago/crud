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

    /**
     * Achado 7: `Str::slug('青い')` devolve string vazia — sem caractere latino para
     * transliterar, não sobra nada. Sem guard, o comando criava
     * `:root[data-crud-palette='']` no CSS e `{ id: '', name: '青い' }` no TS: paleta sem
     * id nenhum, indistinguível da paleta seguinte que também desse slug vazio.
     */
    public function test_nome_sem_ascii_falha_sem_escrever(): void
    {
        $fs = new Filesystem();
        $cssBefore = $fs->get($this->base . '/resources/css/crud-palettes.css');
        $tsBefore = $fs->get($this->base . '/resources/js/lib/crud-palette.ts');

        $this->artisan('crud:create-palette 青い --hue=200')->assertExitCode(1);

        $this->assertSame($cssBefore, $fs->get($this->base . '/resources/css/crud-palettes.css'));
        $this->assertSame($tsBefore, $fs->get($this->base . '/resources/js/lib/crud-palette.ts'));
        $this->assertStringNotContainsString("data-crud-palette='']", $fs->get($this->base . '/resources/css/crud-palettes.css'));
    }

    public function test_crlf_no_ts_funciona_e_preserva(): void
    {
        $fs = new Filesystem();
        $tsPath = $this->base . '/resources/js/lib/crud-palette.ts';

        // Substitui LF por CRLF
        $conteudo = $fs->get($tsPath);
        $conteudoCRLF = str_replace("\n", "\r\n", $conteudo);
        $fs->put($tsPath, $conteudoCRLF);

        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(0);

        // TS deve continuar com CRLF (preserva estilo original)
        $tsDepois = $fs->get($tsPath);
        $this->assertStringContainsString("\r\n", $tsDepois);
        $this->assertStringContainsString("{ id: 'laranja', name: 'Laranja' },", $tsDepois);

        // CSS deve ter a paleta
        $css = $fs->get($this->base . '/resources/css/crud-palettes.css');
        $this->assertStringContainsString(":root[data-crud-palette='laranja']", $css);
    }

    public function test_sem_ancora_no_ts_falha_sem_escrever(): void
    {
        $fs = new Filesystem();
        $tsPath = $this->base . '/resources/js/lib/crud-palette.ts';

        // Remove a função getPalette
        $conteudo = $fs->get($tsPath);
        $conteudo = str_replace("export function getPalette", "export function OTHER_FUNC", $conteudo);
        $fs->put($tsPath, $conteudo);

        $cssBefore = $fs->get($this->base . '/resources/css/crud-palettes.css');
        $tsBefore = $fs->get($tsPath);

        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(1);

        // Nenhum dos dois arquivos deve ser modificado
        $this->assertSame($cssBefore, $fs->get($this->base . '/resources/css/crud-palettes.css'));
        $this->assertSame($tsBefore, $fs->get($tsPath));
    }

    /**
     * Achado 3: a mensagem de erro recomendava `crud:install-palette --force` sem avisar
     * que isso reconstrói o `crud-palette.ts` a partir do stub e apaga qualquer paleta
     * que o usuário tenha criado — inclusive paletas sem nenhuma relação com este erro.
     */
    public function test_mensagem_de_ancora_ausente_nao_recomenda_force_as_cegas(): void
    {
        $fs = new Filesystem();
        $tsPath = $this->base . '/resources/js/lib/crud-palette.ts';

        $conteudo = $fs->get($tsPath);
        $conteudo = str_replace('export function getPalette', 'export function OTHER_FUNC', $conteudo);
        $fs->put($tsPath, $conteudo);

        $this->artisan('crud:create-palette Laranja --hue=70')
            ->expectsOutputToContain('apaga toda paleta')
            ->assertExitCode(1);
    }

    public function test_id_so_no_css_completa_ts(): void
    {
        // Cria a paleta primeiro
        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(0);

        // Remove do TS mas mantém no CSS
        $fs = new Filesystem();
        $tsPath = $this->base . '/resources/js/lib/crud-palette.ts';
        $conteudo = $fs->get($tsPath);
        $conteudo = str_replace("    { id: 'laranja', name: 'Laranja' },\n", "", $conteudo);
        $fs->put($tsPath, $conteudo);

        // Tenta criar de novo com mesmo hue — deve completar TS
        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(0);

        // TS agora deve ter a entrada
        $ts = $fs->get($tsPath);
        $this->assertStringContainsString("{ id: 'laranja', name: 'Laranja' },", $ts);
    }

    public function test_id_so_no_ts_completa_css(): void
    {
        // Cria a paleta primeiro
        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(0);

        // Remove do CSS mas mantém no TS (simulando dessincronização anterior)
        $fs = new Filesystem();
        $cssPath = $this->base . '/resources/css/crud-palettes.css';
        $conteudo = $fs->get($cssPath);

        // Remove ambos os blocos (claro e escuro)
        $conteudo = preg_replace(
            "/\\s*:root(?:\\.dark)?\\[data-crud-palette='laranja'\\]\\s*\\{[^}]+\\}/s",
            "",
            $conteudo
        );
        $fs->put($cssPath, $conteudo);

        // Tenta criar de novo com mesmo hue — deve completar CSS
        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(0);

        // CSS agora deve ter os blocos
        $css = $fs->get($cssPath);
        $this->assertStringContainsString(":root[data-crud-palette='laranja']", $css);
        $this->assertStringContainsString(":root.dark[data-crud-palette='laranja']", $css);
    }

    public function test_completa_ts_com_nome_via_prompt(): void
    {
        // Cria a paleta no CSS
        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(0);

        // Remove do TS mas mantém no CSS
        $fs = new Filesystem();
        $tsPath = $this->base . '/resources/js/lib/crud-palette.ts';
        $conteudo = $fs->get($tsPath);
        $conteudo = str_replace("    { id: 'laranja', name: 'Laranja' },\n", "", $conteudo);
        $fs->put($tsPath, $conteudo);

        // Tenta completar TS com nome via prompt (sem argumento)
        $this->artisan('crud:create-palette --hue=70')
            ->expectsQuestion('Nome da paleta?', 'Laranja')
            ->assertExitCode(0);

        // TS deve ter a entrada com o nome correto
        $ts = $fs->get($tsPath);
        $this->assertStringContainsString("{ id: 'laranja', name: 'Laranja' },", $ts);
        // NÃO deve ter nome vazio
        $this->assertStringNotContainsString("{ id: 'laranja', name: '' },", $ts);
    }

    public function test_id_em_ambos_arquivos_recusa(): void
    {
        // Cria a paleta primeiro
        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(0);

        // Ambos os arquivos têm a paleta — deve recusar
        $this->artisan('crud:create-palette Laranja --hue=100')->assertExitCode(1);
    }

    public function test_cria_paleta_com_apostrofo_no_nome(): void
    {
        $this->artisan('crud:create-palette "D\'Água" --hue=70')->assertExitCode(0);

        $fs = new Filesystem();
        $ts = $fs->get($this->base . '/resources/js/lib/crud-palette.ts');

        // Aspa simples deve estar escapada para TypeScript válido
        $this->assertStringContainsString("{ id: 'dagua', name: 'D\\'Água' },", $ts);
    }

    public function test_completa_paleta_com_apostrofo_no_nome(): void
    {
        // Cria a paleta no CSS
        $this->artisan('crud:create-palette "D\'Água" --hue=70')->assertExitCode(0);

        // Remove do TS mas mantém no CSS
        $fs = new Filesystem();
        $tsPath = $this->base . '/resources/js/lib/crud-palette.ts';
        $conteudo = $fs->get($tsPath);
        $conteudo = str_replace("    { id: 'dagua', name: 'D\\'Água' },\n", "", $conteudo);
        $fs->put($tsPath, $conteudo);

        // Completa TS
        $this->artisan('crud:create-palette "D\'Água" --hue=70')->assertExitCode(0);

        // TS deve ter a entrada com aspa escapada
        $ts = $fs->get($tsPath);
        $this->assertStringContainsString("{ id: 'dagua', name: 'D\\'Água' },", $ts);
    }
}
