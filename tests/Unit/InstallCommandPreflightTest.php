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
        // - column-identifier na forma PLURAL: são precisos dois nomes inválidos, e
        //   `nome-cliente` só é inválido por causa do hífen. Trocá-lo por algo como
        //   `invalido_nome` — que é identificador válido — deixaria o schema com uma
        //   coluna inválida só e faria este teste exercitar o ramo singular.
        $schema = [
            self::column('idClientes', 'int', 'PRI'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
            self::column('2fa_secret'),
            self::column('nome-cliente'),
        ];

        $command = $this->spyCommand($schema);

        // Não assevera a frase inteira, para não prender o teste à prosa: assevera que
        // o dado certo chegou na saída.
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

    public function test_varias_colunas_invalidas_saem_todas_na_mensagem(): void
    {
        $command = $this->spyCommand(self::conventional());

        $message = $command->messageForTest([
            'code' => 'column-identifier',
            'columns' => ['2fa_secret', 'nome-cliente'],
        ]);

        // Os dois nomes são exigidos de propósito: com um só, o ramo plural poderia
        // regredir para imprimir apenas `columns[0]` e ninguém notaria.
        $this->assertStringContainsString('2fa_secret', $message);
        $this->assertStringContainsString('nome-cliente', $message);

        // A asserção acima roda contra a string devolvida, e não contra a saída do
        // console, de propósito: o `expectsOutputToContain` do Testbench não casa o
        // segundo nome mesmo quando ele está literalmente na linha impressa —
        // verificado capturando a saída real, onde a frase sai completa.
        $this->assertStringNotContainsString('A coluna `', $message, 'Devia usar a forma plural.');
    }
}
