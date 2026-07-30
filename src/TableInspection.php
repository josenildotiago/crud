<?php

namespace Crud;

/**
 * Diz o que na tabela está fora da convenção que o código gerado assume.
 *
 * Só dados: recebe as colunas no formato de `SHOW COLUMNS` e devolve achados com um
 * código. Não conhece console, não escreve frase e não decide nada — quem traduz para
 * português e quem pergunta é o comando. Assim a prosa muda sem tocar em teste.
 *
 * Os códigos são um tipo fechado de propósito: `InstallCommand::preflightMessage()` casa
 * um `match` sem `default` em cima deles. Declarar o código novo na união abaixo sem
 * escrever a frase lá é erro de análise estática, em vez de `UnhandledMatchError` no
 * terminal do usuário.
 *
 * O que o PHPStan **não** pega: emitir um `code` daqui que não esteja na união. O tipo do
 * `$findings` acumulado é largo demais para ele comparar. Enquanto os códigos forem string
 * solta, essa ponta é manual — fechar as duas exigiria enum, o que mexe nos testes.
 *
 * @phpstan-type Finding array{
 *     code: 'timestamps'|'primary-key-missing'|'primary-key-not-id'|'column-identifier',
 *     columns: array<int, string>
 * }
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
     * @return array<int, Finding>
     */
    public function inspect(array $columns): array
    {
        $names = array_map(static fn (object $column): string => $column->Field, $columns);

        $findings = [];

        if (!in_array('created_at', $names, true) || !in_array('updated_at', $names, true)) {
            $findings[] = ['code' => 'timestamps', 'columns' => []];
        }

        $primaryKey = null;

        foreach ($columns as $column) {
            if ($column->Key === 'PRI') {
                $primaryKey = $column->Field;
                break;
            }
        }

        if ($primaryKey === null) {
            $findings[] = ['code' => 'primary-key-missing', 'columns' => []];
        } elseif ($primaryKey !== 'id') {
            $findings[] = ['code' => 'primary-key-not-id', 'columns' => [$primaryKey]];
        }

        $invalid = array_values(array_filter(
            $names,
            static fn (string $name): bool => preg_match(self::IDENTIFIER, $name) !== 1
        ));

        if ($invalid !== []) {
            $findings[] = ['code' => 'column-identifier', 'columns' => $invalid];
        }

        return $findings;
    }
}
