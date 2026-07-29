<?php

namespace Crud\Tests\Unit;

use Crud\TableInspection;
use PHPUnit\Framework\TestCase;

class TableInspectionTest extends TestCase
{
    /**
     * Uma coluna no formato que `SHOW COLUMNS` devolve.
     */
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

    /**
     * Tabela criada por migration do Laravel: nada a avisar.
     *
     * @return array<int, object>
     */
    private static function conventional(): array
    {
        return [
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('nome'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ];
    }

    private function codes(array $findings): array
    {
        return array_column($findings, 'code');
    }

    public function test_tabela_sem_os_dois_timestamps_gera_um_aviso_so(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('nome'),
        ]);

        $this->assertSame(['timestamps'], $this->codes($findings));
        $this->assertSame([], $findings[0]['columns']);
    }

    public function test_tabela_convencional_nao_gera_aviso(): void
    {
        $this->assertSame([], (new TableInspection())->inspect(self::conventional()));
    }

    public function test_falta_de_uma_coluna_de_timestamp_ja_gera_o_aviso(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('created_at', 'timestamp'),
        ]);

        $this->assertSame(['timestamps'], $this->codes($findings));
    }

    public function test_tabela_sem_chave_primaria_declarada(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('idClientes', 'int'),
            self::column('nomeCliente'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ]);

        $this->assertSame(['primary-key-missing'], $this->codes($findings));
        $this->assertSame([], $findings[0]['columns']);
    }

    public function test_chave_primaria_com_nome_diferente_de_id(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('idClientes', 'int', 'PRI'),
            self::column('nomeCliente'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ]);

        $this->assertSame(['primary-key-not-id'], $this->codes($findings));
        $this->assertSame(['idClientes'], $findings[0]['columns']);
    }

    public function test_coluna_com_nome_que_nao_e_identificador_valido(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('2fa_secret'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ]);

        $this->assertSame(['column-identifier'], $this->codes($findings));
        $this->assertSame(['2fa_secret'], $findings[0]['columns']);
    }

    public function test_varias_colunas_invalidas_viram_um_achado_so(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('2fa_secret'),
            self::column('nome-cliente'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ]);

        $this->assertSame(['column-identifier'], $this->codes($findings));
        $this->assertSame(['2fa_secret', 'nome-cliente'], $findings[0]['columns']);
    }

    public function test_nome_acentuado_e_identificador_valido_em_php(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('endereço'),
            self::column('órgão'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ]);

        $this->assertSame([], $findings, 'Acento não quebra `$model->endereço` nem o tipo TS.');
    }

    public function test_a_ordem_dos_achados_e_fixa(): void
    {
        // `clientes` do banco de teste, reduzida: sem timestamps, sem PRI, e com um nome
        // inválido acrescentado para exercitar os três de uma vez.
        $findings = (new TableInspection())->inspect([
            self::column('idClientes', 'int'),
            self::column('2fa_secret'),
        ]);

        $this->assertSame(
            ['timestamps', 'primary-key-missing', 'column-identifier'],
            $this->codes($findings)
        );
    }
}
