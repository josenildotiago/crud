<?php

namespace Crud\Tests\Unit;

use Crud\Console\InstallCommand;
use Crud\CrudServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

class LintSpyInstallCommand extends InstallCommand
{
    protected $signature = 'test:lint-contract {name : Table name}
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
            (object) ['Field' => 'descricao', 'Type' => 'text', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'ativo', 'Type' => 'tinyint(1)', 'Null' => 'NO', 'Key' => '', 'Default' => '1', 'Extra' => ''],
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

    /**
     * Renderiza um stub react como o comando renderiza.
     *
     * Os mapas de `buildReactComponents()` e `buildListComponent()` entram juntos: eles
     * divergem só nas células da tabela, e nada aqui asevera célula — o alvo é o bloco de
     * imports e a pontuação das instruções de controle.
     */
    public function render(string $component): string
    {
        $replace = array_merge($this->buildReplacements(), [
            '{{tableHeaders}}' => $this->generateTableHeaders(),
            '{{formFields}}' => $this->generateFormFields(),
            '{{showFields}}' => $this->generateShowFields(),
            '{{searchPlaceholder}}' => $this->getSearchPlaceholder(),
            '{{colSpan}}' => $this->getColSpan(),
            '{{themeImports}}' => '',
            '{{themeComponents}}' => '',
            '{{routeImports}}' => $this->getRouteImports($component),
        ]);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $this->getStub("react/{$component}")
        );
    }
}

/**
 * O `lint:check` dos starter kits é gate de CI deles, e o pacote sobrescreve as páginas
 * `.tsx` sem perguntar — arquivo gerado que viola regra do eslint quebra a build do
 * usuário sem ele ter escrito uma linha.
 *
 * Aqui não roda eslint: o config e os plugins moram no projeto do usuário, e o CI deste
 * pacote não tem node. O que dá para fixar em PHP são as regras que morderam de fato —
 * `import/order`, `consistent-type-imports`, `curly` e a linha em branco antes de
 * instrução de controle. Elas são determinísticas e é nelas que a regressão bate quando
 * alguém acrescenta um import a um stub.
 */
class GeneratedLintContractTest extends TestCase
{
    /** Componentes react e o helper de rota de cada execução. */
    private const COMPONENTS = ['Index', 'Create', 'Edit', 'Show', 'FormField', 'ModelList'];

    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    private function command(string $routes): LintSpyInstallCommand
    {
        $command = new LintSpyInstallCommand(new Filesystem());

        $this->app[Kernel::class]->registerCommand($command);
        $this->artisan("test:lint-contract items --routes={$routes}")->assertExitCode(0);

        return $command;
    }

    /**
     * As linhas `import` do topo do arquivo, na ordem em que saem.
     *
     * @return array<int, string> O caminho do módulo de cada import.
     */
    private function importedModules(string $rendered): array
    {
        $modules = [];

        foreach (explode("\n", $rendered) as $line) {
            if (preg_match("/^import .* from '([^']+)';$/", $line, $match) === 1) {
                $modules[] = $match[1];
                continue;
            }

            // Passou do bloco de imports assim que aparece código de verdade.
            if (trim($line) !== '' && !str_starts_with(trim($line), "'use client'") && $modules !== []) {
                break;
            }
        }

        return $modules;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function routeHelpers(): array
    {
        return ['wayfinder' => ['wayfinder'], 'ziggy' => ['ziggy']];
    }

    #[DataProvider('routeHelpers')]
    public function test_pacotes_externos_vem_antes_dos_internos(string $routes): void
    {
        $command = $this->command($routes);

        foreach (self::COMPONENTS as $component) {
            $modules = $this->importedModules($command->render($component));
            $internos = false;

            foreach ($modules as $module) {
                if (str_starts_with($module, '@/')) {
                    $internos = true;
                    continue;
                }

                $this->assertFalse(
                    $internos,
                    "{$component}: `{$module}` é externo e veio depois de um import `@/`."
                );
            }
        }
    }

    #[DataProvider('routeHelpers')]
    public function test_cada_grupo_de_imports_esta_em_ordem_alfabetica(string $routes): void
    {
        $command = $this->command($routes);

        foreach (self::COMPONENTS as $component) {
            $modules = $this->importedModules($command->render($component));

            $grupos = [
                'externo' => array_values(array_filter($modules, static fn (string $m): bool => !str_starts_with($m, '@/'))),
                'interno' => array_values(array_filter($modules, static fn (string $m): bool => str_starts_with($m, '@/'))),
            ];

            foreach ($grupos as $nome => $grupo) {
                $ordenado = $grupo;
                usort($ordenado, static fn (string $a, string $b): int => strcasecmp($a, $b));

                $this->assertSame(
                    $ordenado,
                    $grupo,
                    "{$component}: o grupo {$nome} não está em ordem alfabética."
                );
            }
        }
    }

    #[DataProvider('routeHelpers')]
    public function test_o_import_de_types_e_import_type(string $routes): void
    {
        $command = $this->command($routes);

        foreach (self::COMPONENTS as $component) {
            $rendered = $command->render($component);

            foreach (explode("\n", $rendered) as $line) {
                if (!str_contains($line, "from '@/types';")) {
                    continue;
                }

                $this->assertStringStartsWith(
                    'import type {',
                    $line,
                    "{$component}: import de `@/types` sem `import type` viola consistent-type-imports."
                );
            }
        }
    }

    #[DataProvider('routeHelpers')]
    public function test_nenhum_if_sem_chaves(string $routes): void
    {
        $command = $this->command($routes);

        foreach (self::COMPONENTS as $component) {
            foreach (explode("\n", $command->render($component)) as $numero => $line) {
                $trimmed = trim($line);

                if (!str_starts_with($trimmed, 'if (')) {
                    continue;
                }

                // Condição em várias linhas termina em operador; a de uma linha só, em `{`.
                $this->assertTrue(
                    str_ends_with($trimmed, '{') || str_ends_with($trimmed, '&&') || str_ends_with($trimmed, '||'),
                    "{$component}: linha " . ($numero + 1) . " ({$trimmed}) é `if` sem chaves, que viola curly."
                );
            }
        }
    }

    /**
     * `padding-line-between-statements` exige linha em branco antes de instrução de
     * controle, a não ser que ela abra o bloco. É a aproximação por linha da regra: ou a
     * anterior está em branco, ou ela abre escopo.
     */
    #[DataProvider('routeHelpers')]
    public function test_instrucao_de_controle_tem_linha_em_branco_antes(string $routes): void
    {
        $command = $this->command($routes);

        foreach (self::COMPONENTS as $component) {
            $linhas = explode("\n", $command->render($component));

            foreach ($linhas as $numero => $line) {
                if (preg_match('/^\s*(if|for|while|switch|try|return|throw)\b/', $line) !== 1 || $numero === 0) {
                    continue;
                }

                // Comentário adere à instrução de baixo: o eslint conta a linha em branco
                // que vem antes dele, então a varredura para trás pula comentário.
                $anterior = '';

                for ($i = $numero - 1; $i >= 0; $i--) {
                    $candidato = rtrim($linhas[$i]);
                    $conteudo = ltrim($candidato);

                    if (str_starts_with($conteudo, '//') || str_starts_with($conteudo, '*') || str_starts_with($conteudo, '/*')) {
                        continue;
                    }

                    $anterior = $candidato;
                    break;
                }

                // `:` cobre o rótulo de `case`, que não é instrução — o `return` logo
                // abaixo dele abre o bloco e o eslint não exige espaço.
                $this->assertTrue(
                    $anterior === ''
                        || str_ends_with($anterior, '{')
                        || str_ends_with($anterior, '=>')
                        || str_ends_with($anterior, ':'),
                    "{$component}: linha " . ($numero + 1) . " ({$line}) precisa de linha em branco antes."
                );
            }
        }
    }
}
