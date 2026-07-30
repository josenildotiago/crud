<?php

namespace Crud\Tests\Unit;

use Crud\Console\InstallCommand;
use Crud\CrudServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

class RouteImportsSpyInstallCommand extends InstallCommand
{
    protected $signature = 'test:route-imports {name : Table name}
                                            {--stack=react : Frontend stack (react, vue, blade)}
                                            {--routes= : Route helper for the generated components (ziggy, wayfinder)}
                                            {--route= : Custom route name}
                                            {--relationship : Specify if you want to establish a relationship}
                                            {--theme : Include theme-aware components}';

    protected function tableExists()
    {
        return true;
    }

    protected function getColumns()
    {
        return [
            (object) ['Field' => 'id', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Key' => 'PRI', 'Default' => null, 'Extra' => 'auto_increment'],
            (object) ['Field' => 'nome', 'Type' => 'varchar(255)', 'Null' => 'NO', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'created_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'updated_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
        ];
    }

    protected function buildController(): self
    {
        return $this;
    }

    protected function buildModel(): self
    {
        return $this;
    }

    protected function buildViews(): self
    {
        return $this;
    }

    public function buildRouter(): self
    {
        return $this;
    }

    protected function generateWayfinderRoutes(): self
    {
        return $this;
    }

    public function routeImportsForTest(string $component): string
    {
        return $this->getRouteImports($component);
    }

    /** @return array<string, string> */
    public function replacementsForTest(): array
    {
        return $this->buildReplacements();
    }
}

/**
 * O modo wayfinder chama as rotas como função (`editRoute(item.id)`), então cada
 * componente gerado precisa da linha de import correspondente. Sem ela o arquivo é
 * escrito com uma referência a identificador inexistente e o build do usuário quebra —
 * quando o próprio `getRouteImports()` não morre antes, no retorno.
 */
class InstallCommandRouteImportsTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    private function spyCommand(string $arguments): RouteImportsSpyInstallCommand
    {
        $command = new RouteImportsSpyInstallCommand(new Filesystem());

        $this->app[Kernel::class]->registerCommand($command);
        $this->artisan("test:route-imports {$arguments}")->assertExitCode(0);

        return $command;
    }

    public function test_o_import_do_index_traz_as_seis_funcoes_de_rota(): void
    {
        $command = $this->spyCommand('items --routes=wayfinder');

        $this->assertSame(
            "\nimport { index as indexRoute, create as createRoute, show as showRoute,"
                . " edit as editRoute, destroy as destroyRoute, bulkDestroy as bulkDestroyRoute }"
                . " from '@/routes/items';",
            $command->routeImportsForTest('Index')
        );
    }

    public function test_o_import_da_listagem_traz_so_o_que_ela_usa(): void
    {
        $command = $this->spyCommand('items --routes=wayfinder');

        $this->assertSame(
            "\nimport { edit as editRoute, destroy as destroyRoute } from '@/routes/items';",
            $command->routeImportsForTest('ModelList')
        );
    }

    public function test_o_import_honra_a_rota_customizada(): void
    {
        $command = $this->spyCommand('items --routes=wayfinder --route=produtos');

        $this->assertStringContainsString(
            "from '@/routes/produtos';",
            $command->routeImportsForTest('Index')
        );
    }

    public function test_ziggy_nao_importa_nada(): void
    {
        $command = $this->spyCommand('items --routes=ziggy');

        foreach (['Index', 'Create', 'Edit', 'Show', 'FormField', 'ModelList'] as $component) {
            $this->assertSame('', $command->routeImportsForTest($component));
        }
    }

    public function test_componente_que_nao_navega_nao_importa_nada(): void
    {
        $command = $this->spyCommand('items --routes=wayfinder');

        $this->assertSame('', $command->routeImportsForTest('FormField'));
        $this->assertSame('', $command->routeImportsForTest('ComponenteQueNaoExiste'));
    }

    /**
     * O contrato entre WAYFINDER_IMPORTS e os stubs: toda função `xRoute()` que o
     * componente renderizado chama tem que estar na linha de import dele.
     */
    public function test_cada_funcao_de_rota_chamada_pelo_stub_esta_importada(): void
    {
        $command = $this->spyCommand('items --routes=wayfinder');
        $replacements = $command->replacementsForTest();

        $components = [
            'Index' => 'Index.stub',
            'Create' => 'Create.stub',
            'Edit' => 'Edit.stub',
            'Show' => 'Show.stub',
            'FormField' => 'FormField.stub',
            'ModelList' => 'ModelList.stub',
        ];

        foreach ($components as $component => $stub) {
            $imports = $command->routeImportsForTest($component);

            $rendered = str_replace(
                array_keys($replacements),
                array_values($replacements),
                file_get_contents(__DIR__ . '/../../src/stubs/react/' . $stub)
            );

            preg_match_all('/\b([a-z][A-Za-z]*)Route\(/', $rendered, $matches);

            foreach (array_unique($matches[1]) as $symbol) {
                $this->assertStringContainsString(
                    "{$symbol} as {$symbol}Route",
                    $imports,
                    "{$stub} chama {$symbol}Route() sem importar {$symbol}."
                );
            }
        }
    }
}
