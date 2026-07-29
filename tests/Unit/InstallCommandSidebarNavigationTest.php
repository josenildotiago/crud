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

    protected function debugColumns(): void
    {
        // Sem banco no teste.
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
            "{ title: 'Clientes', href: '/clientes', icon: List },",
            $result
        );
        $this->assertStringContainsString("import { List } from 'lucide-react';", $result);
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
        $this->assertSame(1, substr_count($result, "import { List } from 'lucide-react';"));
    }
}
