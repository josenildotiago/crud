<?php

namespace Crud;

/**
 * Diz o que na tabela está fora da convenção que o código gerado assume.
 *
 * Só dados: recebe as colunas no formato de `SHOW COLUMNS` e devolve achados com um
 * código. Não conhece console, não escreve frase e não decide nada — quem traduz para
 * português e quem pergunta é o comando. Assim a prosa muda sem tocar em teste.
 */
final class TableInspection
{
    /**
     * Regra de identificador do PHP. Nomes acentuados (`endereço`) são válidos e por isso
     * não entram nos achados; palavra reservada também não, porque `$model->class` e
     * `class: string;` são ambos legais.
     */
    private const IDENTIFIER = '/^[A-Za-z_\x80-\xFF][A-Za-z0-9_\x80-\xFF]*$/';

    /**
     * @param array<int, object> $columns Formato de `SHOW COLUMNS`.
     * @return array<int, array{code: string, columns: array<int, string>}>
     */
    public function inspect(array $columns): array
    {
        $names = array_map(static fn (object $column): string => $column->Field, $columns);

        $findings = [];

        if (!in_array('created_at', $names, true) || !in_array('updated_at', $names, true)) {
            $findings[] = ['code' => 'timestamps', 'columns' => []];
        }

        return $findings;
    }
}
