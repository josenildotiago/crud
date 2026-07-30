<?php

namespace Crud\Tests\Unit;

use Crud\Console\InstallCommand;
use Crud\CrudServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;

/**
 * Dublê que mantém `buildRouter()` real junto de `buildSidebarNavigation()`.
 *
 * É de propósito: o link da sidebar já apontou para uma URL que o gerador nunca
 * declarou, e nenhum teste pegou porque cada ponta era exercitada sozinha. Aqui as
 * duas rodam na mesma execução.
 */
class RouteAndSidebarSpyInstallCommand extends InstallCommand
{
    protected $signature = 'test:route-sidebar-spy {name : Table name}
                                            {--stack=react : Frontend stack (react, vue, blade)}
                                            {--routes= : Route helper for the generated components (ziggy, wayfinder)}
                                            {--route= : Custom route name}
                                            {--relationship : Specify if you want to establish a relationship}
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

    /**
     * Colunas fixas no formato de `SHOW COLUMNS`.
     *
     * `buildRouter()` passa por `buildReplacements()`, que introspecta o schema. Este
     * teste é sobre a emenda entre sidebar e rotas, não sobre introspecção.
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
        return $this->buildSidebarNavigation();
    }

    /**
     * Mapa de replacements como os stubs o recebem.
     *
     * Os stubs são texto: o que decide a URL escrita dentro deles é este mapa, então é
     * nele que os placeholders de rota precisam concordar entre si.
     *
     * @return array<string, string>
     */
    public function replacementsForTest(): array
    {
        return $this->buildReplacements();
    }
}

class InstallCommandNavigationRouteTest extends TestCase
{
    private string $tmpBasePath;

    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpBasePath = sys_get_temp_dir() . '/crud-nav-route-' . uniqid();

        $files = new Filesystem();
        $files->makeDirectory($this->tmpBasePath . '/resources/js/components', 0755, true);
        $files->makeDirectory($this->tmpBasePath . '/routes', 0755, true);
        $files->put($this->tmpBasePath . '/routes/web.php', "<?php\n");
        $files->put($this->tmpBasePath . '/resources/js/components/app-sidebar.tsx', <<<'TSX'
        import { BookOpen, LayoutGrid } from 'lucide-react';
        import type { NavItem } from '@/types';

        const mainNavItems: NavItem[] = [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        ];
        TSX);

        $this->app->setBasePath($this->tmpBasePath);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tmpBasePath);

        parent::tearDown();
    }

    private function spyCommand(): RouteAndSidebarSpyInstallCommand
    {
        $command = new RouteAndSidebarSpyInstallCommand(new Filesystem());

        $this->app[Kernel::class]->registerCommand($command);

        return $command;
    }

    /**
     * O href que o pacote escreveu na sidebar.
     *
     * O item do Dashboard usa `href: dashboard()` (sem quotes), então a única captura
     * possível é a do item gerado.
     */
    private function hrefFromSidebar(): string
    {
        $sidebar = file_get_contents($this->tmpBasePath . '/resources/js/components/app-sidebar.tsx');

        $this->assertSame(1, preg_match("/href: '([^']+)'/", $sidebar, $matches), 'Nenhum href literal na sidebar.');

        return $matches[1];
    }

    /**
     * URIs GET que o arquivo de rotas gerado registra de fato.
     *
     * Carregar o arquivo é o ponto do teste: asseverar que o texto contém a rota
     * provaria só que a string foi escrita, e foi exatamente esse tipo de verificação
     * que deixou o 404 passar.
     */
    private function generatedGetUris(): array
    {
        require $this->tmpBasePath . '/routes/cliente.php';

        $uris = [];

        foreach (Route::getRoutes() as $route) {
            if (in_array('GET', $route->methods(), true)) {
                $uris[] = '/' . $route->uri();
            }
        }

        return $uris;
    }

    public function test_the_sidebar_href_resolves_to_a_route_the_generator_declares(): void
    {
        $this->spyCommand();

        $this->artisan('test:route-sidebar-spy clientes')
            ->expectsConfirmation('Adicionar o link na sidebar?', 'yes')
            ->assertExitCode(0);

        $this->assertContains($this->hrefFromSidebar(), $this->generatedGetUris());
    }

    public function test_custom_route_option_keeps_the_href_and_the_routes_aligned(): void
    {
        $this->spyCommand();

        $this->artisan('test:route-sidebar-spy clientes --route=parceiros')
            ->expectsConfirmation('Adicionar o link na sidebar?', 'yes')
            ->assertExitCode(0);

        $href = $this->hrefFromSidebar();

        $this->assertSame('/parceiros', $href);
        $this->assertContains($href, $this->generatedGetUris());
    }

    public function test_custom_route_reaches_the_url_placeholders_the_components_use(): void
    {
        $command = $this->spyCommand();

        $this->artisan('test:route-sidebar-spy clientes --route=parceiros')
            ->expectsConfirmation('Adicionar o link na sidebar?', 'yes')
            ->assertExitCode(0);

        $replacements = $command->replacementsForTest();

        // Os breadcrumbs dos componentes react são escritos com {{modelRoutePlural}} e
        // as rotas com {{modelRoute}}: se os dois divergirem, o breadcrumb aponta para
        // uma URL que não existe — o mesmo defeito do link da sidebar, seis vezes.
        $this->assertSame('parceiros', $replacements['{{modelRoute}}']);
        $this->assertSame('parceiros', $replacements['{{modelRoutePlural}}']);
    }
}
