<?php

namespace Crud\Tests\Unit;

use Crud\Console\InstallCommand;
use Crud\CrudServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

/**
 * Dublê que corta o acesso ao banco e isola a orquestração de
 * `buildSidebarNavigation()`: os demais builders da stack react (listagem, tipos
 * TypeScript, componentes shadcn/ui) exigem stubs e não são o alvo deste teste.
 */
class SidebarSpyInstallCommand extends InstallCommand
{
    protected $signature = 'test:sidebar-spy {name : Table name}
                                            {--stack=react : Frontend stack (react, vue, blade)}
                                            {--routes= : Route helper for the generated components (ziggy, wayfinder)}
                                            {--route= : Custom route name}
                                            {--relationship : Specify if you want to establish a relationship}
                                            {--api : Generate API endpoints}
                                            {--theme : Include theme-aware components}';

    protected function tableExists()
    {
        return true;
    }

    protected function buildController(): self
    {
        return $this;
    }

    protected function buildModel(): self
    {
        return $this;
    }

    public function buildRouter(): self
    {
        return $this;
    }

    /**
     * Schema convencional para o pré-voo ficar silencioso: este arquivo é sobre a
     * sidebar, não sobre a inspeção da tabela.
     */
    protected function getColumns()
    {
        return [
            (object) ['Field' => 'id', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Key' => 'PRI', 'Default' => null, 'Extra' => 'auto_increment'],
            (object) ['Field' => 'nome', 'Type' => 'varchar(255)', 'Null' => 'NO', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'created_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'updated_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
        ];
    }

    protected function generateWayfinderRoutes(): self
    {
        // Não dispara subprocesso no teste.
        return $this;
    }

    protected function buildReactComponents(): self
    {
        // Só o que este teste quer exercitar; listagem/tipos/ui components ficam
        // fora porque dependem de stubs e não têm nada a ver com a sidebar.
        return $this->buildSidebarNavigation();
    }
}

class InstallCommandSidebarNavigationTest extends TestCase
{
    private string $tmpBasePath;

    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // resource_path() é `basePath('resources')`: para controlar o destino sem
        // depender do app de exemplo, trocamos o basePath da aplicação de teste por
        // um diretório temporário só desta execução.
        $this->tmpBasePath = sys_get_temp_dir() . '/crud-sidebar-nav-' . uniqid();
        (new Filesystem())->makeDirectory($this->sidebarDir(), 0755, true);
        $this->app->setBasePath($this->tmpBasePath);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tmpBasePath);

        parent::tearDown();
    }

    private function sidebarDir(): string
    {
        return $this->tmpBasePath . '/resources/js/components';
    }

    private function sidebarPath(): string
    {
        return $this->sidebarDir() . '/app-sidebar.tsx';
    }

    private function putSidebar(string $content): void
    {
        (new Filesystem())->put($this->sidebarPath(), $content);
    }

    private function getSidebar(): ?string
    {
        return file_exists($this->sidebarPath()) ? file_get_contents($this->sidebarPath()) : null;
    }

    private function spyCommand(): SidebarSpyInstallCommand
    {
        $command = new SidebarSpyInstallCommand(new Filesystem());

        $this->app[Kernel::class]->registerCommand($command);

        return $command;
    }

    private function sidebarWithoutRegion(): string
    {
        return <<<'TSX'
        import { BookOpen, LayoutGrid } from 'lucide-react';
        import type { NavItem } from '@/types';

        const mainNavItems: NavItem[] = [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        ];
        TSX;
    }

    private function sidebarWithMalformedRegion(): string
    {
        return <<<'TSX'
        import { BookOpen, LayoutGrid } from 'lucide-react';
        import type { NavItem } from '@/types';

        const mainNavItems: NavItem[] = [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            // crud:nav:start
        ];
        TSX;
    }

    /**
     * A sidebar depois de um `npm run format` — script do próprio starter kit, com
     * `printWidth: 80`.
     *
     * O item que a geração de `configuracoes_sistema` escreve tem 88 colunas, então o
     * prettier o quebra em várias linhas. Regerar a mesma tabela cai aqui.
     */
    private function sidebarWithPrettierFormattedItem(): string
    {
        return <<<'TSX'
        import { LayoutGrid, List as CrudNavIcon } from 'lucide-react';
        import type { NavItem } from '@/types';

        const mainNavItems: NavItem[] = [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            // crud:nav:start
            {
                title: 'Configuracoes Sistemas',
                href: '/configuracoes-sistemas',
                icon: CrudNavIcon,
            },
            // crud:nav:end
        ];
        TSX;
    }

    public function test_a_reformatted_item_is_left_alone_and_the_reason_is_named(): void
    {
        $original = $this->sidebarWithPrettierFormattedItem();
        $this->putSidebar($original);

        $this->spyCommand();

        // Uma palavra só, e não a frase: `expectsOutputToContain` casa contra a saída
        // já quebrada em linhas, e o aviso não cabe numa. O que o teste prende é que a
        // causa seja nomeada — dizer "marcadores malformados" aqui manda o usuário
        // procurar um defeito que não existe, e colar um item que já está no arquivo.
        $this->artisan('test:sidebar-spy configuracoes_sistema')
            ->expectsOutputToContain('reformatado')
            ->assertExitCode(0);

        $this->assertSame($original, $this->getSidebar());
    }

    private function sidebarAlreadyImportingTheIcon(): string
    {
        return <<<'TSX'
        import { BookOpen, LayoutGrid, List } from 'lucide-react';
        import type { NavItem } from '@/types';

        const mainNavItems: NavItem[] = [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            { title: 'Tarefas', href: '/tarefas', icon: List },
        ];
        TSX;
    }

    /**
     * Identificadores que os imports do lucide-react ligam em escopo.
     *
     * `X as Y` liga `Y`; sem alias, liga o próprio nome. Ligar o mesmo identificador
     * duas vezes não é duplicação cosmética de import: é erro de compilação em TS e
     * SyntaxError em ES modules, ou seja, o build do usuário para.
     *
     * @return array<int, string>
     */
    private function lucideBindings(string $sidebar): array
    {
        preg_match_all("/^import \{([^}]*)\} from 'lucide-react';$/m", $sidebar, $matches);

        $bindings = [];

        foreach ($matches[1] as $group) {
            foreach (explode(',', $group) as $specifier) {
                $specifier = trim($specifier);

                if ($specifier === '') {
                    continue;
                }

                $parts = preg_split('/\s+as\s+/', $specifier);
                $bindings[] = end($parts);
            }
        }

        return $bindings;
    }

    public function test_an_icon_already_imported_does_not_gain_a_second_binding(): void
    {
        $this->putSidebar($this->sidebarAlreadyImportingTheIcon());

        $this->spyCommand();

        $this->artisan('test:sidebar-spy clientes')
            ->expectsConfirmation('Adicionar o link na sidebar?', 'yes')
            ->assertExitCode(0);

        $result = $this->getSidebar();
        $bindings = $this->lucideBindings($result);

        $this->assertSame(
            array_values(array_unique($bindings)),
            $bindings,
            'A sidebar ficou com o mesmo identificador importado duas vezes do lucide-react.'
        );

        // E o ícone que o item gerado usa tem de estar entre os identificadores ligados.
        $this->assertSame(1, preg_match('/href: \'\/clientes\', icon: (\w+) \},/', $result, $matches));
        $this->assertContains($matches[1], $bindings);
    }

    public function test_navigation_disabled_by_config_leaves_the_file_untouched(): void
    {
        $original = $this->sidebarWithoutRegion();
        $this->putSidebar($original);

        config()->set('crud.navigation.sidebar', false);

        $this->spyCommand();
        $this->artisan('test:sidebar-spy clientes')->assertExitCode(0);

        $this->assertSame($original, $this->getSidebar());
    }

    public function test_missing_sidebar_file_does_not_abort_the_generation(): void
    {
        // Nenhum app-sidebar.tsx no diretório temporário.
        $this->spyCommand();

        $this->artisan('test:sidebar-spy clientes')->assertExitCode(0);

        $this->assertNull($this->getSidebar());
    }

    public function test_first_generation_creates_the_region_and_the_item(): void
    {
        $this->putSidebar($this->sidebarWithoutRegion());

        $this->spyCommand();

        // Sob o Testbench, Prompt::fallbackWhen(runningUnitTests()) força o confirm()
        // a cair no componente de console mockado -- sem expectsConfirmation() aqui,
        // o mock não tem expectativa e o teste falha com BadMethodCallException.
        $this->artisan('test:sidebar-spy clientes')
            ->expectsConfirmation('Adicionar o link na sidebar?', 'yes')
            ->assertExitCode(0);

        $result = $this->getSidebar();

        $this->assertNotNull($result);
        $this->assertStringContainsString('// crud:nav:start', $result);
        $this->assertStringContainsString('// crud:nav:end', $result);
        $this->assertStringContainsString(
            "{ title: 'Clientes', href: '/clientes', icon: CrudNavIcon },",
            $result
        );
        $this->assertStringContainsString("import { List as CrudNavIcon } from 'lucide-react';", $result);
    }

    public function test_malformed_markers_leave_the_file_byte_for_byte_unchanged(): void
    {
        $original = $this->sidebarWithMalformedRegion();
        $this->putSidebar($original);

        $this->spyCommand();
        $this->artisan('test:sidebar-spy clientes')->assertExitCode(0);

        $this->assertSame($original, $this->getSidebar());
    }

    public function test_regenerating_does_not_duplicate_the_item(): void
    {
        $this->putSidebar($this->sidebarWithoutRegion());

        $this->spyCommand();

        // Só a primeira chamada encontra a sidebar sem marcadores e pergunta; a
        // segunda já acha a região instalada e vai direto para o upsert().
        $this->artisan('test:sidebar-spy clientes')
            ->expectsConfirmation('Adicionar o link na sidebar?', 'yes')
            ->assertExitCode(0);
        $this->artisan('test:sidebar-spy clientes')->assertExitCode(0);

        $result = $this->getSidebar();

        $this->assertSame(1, substr_count($result, "href: '/clientes'"));
        $this->assertSame(1, substr_count($result, '// crud:nav:start'));
        $this->assertSame(1, substr_count($result, "import { List as CrudNavIcon } from 'lucide-react';"));
    }
}
