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
}
