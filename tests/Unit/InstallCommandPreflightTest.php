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

    /** @param array{code: string, columns: array<int, string>} $finding */
    public function messageForTest(array $finding): string
    {
        return $this->preflightMessage($finding);
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

    public function test_modo_nao_interativo_avisa_e_segue(): void
    {
        $command = $this->spyCommand(self::legacy());

        // Testa a decisão do dono do pacote: quando não há terminal interativo,
        // avisar que segue por conta e risco, e não bloquear. Scripts em lote
        // não devem abortar em silêncio só porque a tabela não é convencional.
        $this->artisan('test:preflight clientes --no-interaction')
            ->expectsOutputToContain('Modo não interativo: seguindo por sua conta e risco.')
            ->assertExitCode(0);

        $this->assertTrue($command->generated);
    }

    public function test_avisos_de_chave_primaria_e_coluna_invalida(): void
    {
        // Schema que dispara dois ramos do match em preflightMessage():
        // - primary-key-not-id: chave primária é 'idClientes', não 'id'
        // - column-identifier (plural): colunas '2fa_secret' e 'invalido_nome' são inválidas
        $schema = [
            self::column('idClientes', 'int', 'PRI'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
            self::column('2fa_secret'),
            self::column('invalido_nome'),
        ];

        $command = $this->spyCommand($schema);

        // Testa a cobertura dos ramos: a mensagem deve interpolar a chave primária
        // e listar os identificadores inválidos. Não assevera a frase inteira para
        // pegar mudanças de interpolação ou ramo trocado. Verifica a chave primária
        // não convencional (ramo primary-key-not-id) e a coluna inválida que dispara
        // o ramo column-identifier (que pode ter um ou múltiplos nomes). O comando
        // deve gerar mesmo assim após confirmação.
        $this->artisan('test:preflight clientes')
            ->expectsConfirmation('Gerar mesmo assim?', 'yes')
            ->expectsOutputToContain('idClientes')
            ->expectsOutputToContain('2fa_secret')
            ->assertExitCode(0);

        $this->assertTrue($command->generated);
    }

    public function test_preflightMessage_cobre_todos_os_codigos_conhecidos(): void
    {
        // Este teste existe para que um código novo em TableInspection sem frase
        // correspondente quebre a suíte em vez de quebrar no terminal do usuário.
        // Se TableInspection emitir um novo código e InstallCommand não tiver o braço
        // correspondente no match, preflightMessage() lança UnhandledMatchError aqui.
        $command = new PreflightSpyInstallCommand(new Filesystem());

        // timestamps: dispara faltando qualquer uma (ou ambas) das colunas.
        $message = $command->messageForTest(['code' => 'timestamps', 'columns' => []]);
        $this->assertIsString($message);
        $this->assertNotEmpty($message);

        // primary-key-missing: tabela sem chave primária declarada.
        $message = $command->messageForTest(['code' => 'primary-key-missing', 'columns' => []]);
        $this->assertIsString($message);
        $this->assertNotEmpty($message);

        // primary-key-not-id: chave primária existe, mas é chamada de outro nome.
        $message = $command->messageForTest(['code' => 'primary-key-not-id', 'columns' => ['idClientes']]);
        $this->assertIsString($message);
        $this->assertNotEmpty($message);
        $this->assertStringContainsString('idClientes', $message);

        // column-identifier: coluna cujo nome não é identificador válido em PHP/TS.
        $message = $command->messageForTest(['code' => 'column-identifier', 'columns' => ['2fa_secret']]);
        $this->assertIsString($message);
        $this->assertNotEmpty($message);
        $this->assertStringContainsString('2fa_secret', $message);
    }
}
