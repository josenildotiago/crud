<?php

namespace Crud\Tests\Unit;

use Crud\Console\InstallCommand;
use Crud\CrudServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

/**
 * Dublê que só deixa o pré-voo e o `handle()` reais. `buildRouter()` registra que rodou:
 * é assim que o teste prova que responder "não" não escreveu nada, sem depender de
 * inspecionar disco.
 */
class PreflightSpyInstallCommand extends InstallCommand
{
    protected $signature = 'test:preflight {name : Table name}
                                            {--stack=react : Frontend stack (react, vue, blade)}
                                            {--routes= : Route helper for the generated components (ziggy, wayfinder)}
                                            {--route= : Custom route name}
                                            {--relationship : Specify if you want to establish a relationship}
                                            {--api : Generate API endpoints}
                                            {--theme : Include theme-aware components}';

    /** @var array<int, object> */
    public array $columns = [];

    public bool $generated = false;

    protected function tableExists()
    {
        return true;
    }

    protected function getColumns()
    {
        return $this->columns;
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
        $this->generated = true;

        return $this;
    }

    protected function generateWayfinderRoutes(): self
    {
        return $this;
    }
}

class InstallCommandPreflightTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    private static function column(string $field, string $type = 'varchar(255)', string $key = ''): object
    {
        return (object) [
            'Field' => $field,
            'Type' => $type,
            'Null' => 'YES',
            'Key' => $key,
            'Default' => null,
            'Extra' => '',
        ];
    }

    /** @return array<int, object> */
    private static function legacy(): array
    {
        return [
            self::column('idClientes', 'int'),
            self::column('nomeCliente'),
        ];
    }

    /** @return array<int, object> */
    private static function conventional(): array
    {
        return [
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('nome'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ];
    }

    /** @param array<int, object> $columns */
    private function spyCommand(array $columns): PreflightSpyInstallCommand
    {
        $command = new PreflightSpyInstallCommand(new Filesystem());
        $command->columns = $columns;

        $this->app[Kernel::class]->registerCommand($command);

        return $command;
    }

    public function test_recusar_o_aviso_nao_gera_nada(): void
    {
        $command = $this->spyCommand(self::legacy());

        $this->artisan('test:preflight clientes')
            ->expectsConfirmation('Gerar mesmo assim?', 'no')
            ->assertFailed();

        $this->assertFalse($command->generated, 'Nenhum arquivo deveria ter sido gerado.');
    }

    public function test_aceitar_o_aviso_gera(): void
    {
        $command = $this->spyCommand(self::legacy());

        $this->artisan('test:preflight clientes')
            ->expectsConfirmation('Gerar mesmo assim?', 'yes')
            ->assertExitCode(0);

        $this->assertTrue($command->generated);
    }

    public function test_tabela_convencional_nao_pergunta_nada(): void
    {
        $command = $this->spyCommand(self::conventional());

        // Sem expectsConfirmation: se o comando perguntar, o mock não tem expectativa
        // registrada e o teste falha. É esse o ponto.
        $this->artisan('test:preflight clientes')->assertExitCode(0);

        $this->assertTrue($command->generated);
    }
}
