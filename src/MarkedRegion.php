<?php

namespace Crud;

/**
 * Região marcada dentro de um arquivo do usuário.
 *
 * Trabalha só com texto: recebe conteúdo e devolve conteúdo novo, ou `null` quando não há
 * como escrever com segurança. Quem chama trata o `null` imprimindo o trecho para o usuário
 * colar à mão — o pacote nunca chuta a posição.
 *
 * Irmã da `NavigationRegion`, não substituta: lá a âncora tem forma de array ("antes do
 * primeiro fechamento depois da linha X"), aqui tem forma de elemento ("logo depois desta
 * linha"). Unificar as duas hoje seria refatoração sem demanda.
 */
final class MarkedRegion
{
    /** Import que abre e fecha na mesma linha, com o módulo capturado. */
    private const IMPORT = '/^\s*import\s.+\bfrom\s+[\'"](?<module>[^\'"]+)[\'"];$/';

    public function __construct(
        private readonly string $startMarker,
        private readonly string $endMarker,
    ) {
    }

    public function exists(string $content): bool
    {
        return str_contains($content, $this->startMarker) && str_contains($content, $this->endMarker);
    }

    /**
     * Cria a região logo depois da primeira linha que casa a âncora, com o mesmo recuo dela.
     *
     * Devolve null se a âncora não existir ou se a região já estiver instalada.
     */
    public function install(string $content, string $anchorPattern, string $block): ?string
    {
        if ($this->exists($content)) {
            return null;
        }

        $lines = explode("\n", $content);

        foreach ($lines as $number => $line) {
            if (preg_match($anchorPattern, $line) !== 1) {
                continue;
            }

            preg_match('/^\s*/', $line, $indent);

            $novo = array_map(
                static fn (string $l): string => $l === '' ? '' : $indent[0] . $l,
                array_merge([$this->startMarker], explode("\n", $block), [$this->endMarker])
            );

            array_splice($lines, $number + 1, 0, $novo);

            return implode("\n", $lines);
        }

        return null;
    }

    /**
     * Troca o conteúdo entre os marcadores, preservando o recuo deles.
     *
     * Devolve null se a região não existir, estiver malformada ou os marcadores
     * estiverem na mesma linha.
     */
    public function replace(string $content, string $block): ?string
    {
        if (!$this->exists($content)) {
            return null;
        }

        $lines = explode("\n", $content);
        [$start, $end] = $this->locate($lines);

        if ($start === null || $end === null || $end === $start) {
            return null;
        }

        preg_match('/^\s*/', $lines[$start], $indent);

        $novo = array_map(
            static fn (string $l): string => $l === '' ? '' : $indent[0] . $l,
            explode("\n", $block)
        );

        array_splice($lines, $start + 1, $end - $start - 1, $novo);

        return implode("\n", $lines);
    }

    /**
     * Remove a região inteira, marcadores inclusive.
     *
     * Devolve null se a região não existir, estiver malformada ou os marcadores
     * estiverem na mesma linha.
     */
    public function remove(string $content): ?string
    {
        if (!$this->exists($content)) {
            return null;
        }

        $lines = explode("\n", $content);
        [$start, $end] = $this->locate($lines);

        if ($start === null || $end === null || $end === $start) {
            return null;
        }

        array_splice($lines, $start, $end - $start + 1);

        return implode("\n", $lines);
    }

    /**
     * Índices das linhas dos marcadores, ou [null, null] se a região for inválida.
     *
     * O marcador final só conta depois do inicial, então `end` antes de `start` cai
     * no mesmo caminho de "não encontrado". A primeira ocorrência do marcador inicial
     * é sempre usada (guard `$startLine === null &&`).
     *
     * @param array<int, string> $lines
     * @return array{0: int|null, 1: int|null}
     */
    private function locate(array $lines): array
    {
        $startLine = null;

        foreach ($lines as $i => $line) {
            if ($startLine === null && str_contains($line, $this->startMarker)) {
                $startLine = $i;
                continue;
            }

            if ($startLine !== null && str_contains($line, $this->endMarker)) {
                return [$startLine, $i];
            }
        }

        return [null, null];
    }

    /**
     * Insere a linha de import na posição alfabética dentro do grupo do módulo.
     *
     * O `import/order` do starter kit é gate de CI deles: import fora de ordem quebra a
     * build do usuário. Grupo aqui é "começa com `@/`" ou "não começa"; dentro do grupo a
     * ordem é alfabética, insensível a caixa, como o eslint pede.
     *
     * A linha entra com o mesmo recuo do vizinho que serviu de referência — um `.tsx` ou
     * `.vue` só tem um bloco de import, sem recuo nenhum, mas o `.svelte` do starter kit
     * tem dois `<script>`: um `module`, com o import da rota do breadcrumb, e um de
     * instância, com os imports de componente, cada linha recuada em 4 espaços. Por isso
     * a busca fica restrita ao bloco de instância quando há mais de um `<script>` —
     * `scriptBlockRange()` decide qual. Sem essa restrição, um import `@/` do bloco
     * `module` podia vencer a comparação alfabética antes do laço alcançar os vizinhos de
     * verdade, e a linha nova saía sem recuo e no lugar errado (visto de verdade no
     * `projeto-exemplo-svelte`: `npx prettier --check` reprovava o arquivo).
     *
     * Devolve o conteúdo intacto se o módulo já estiver importado, e null se não houver
     * import nenhum na faixa de busca — sem âncora, não há onde acertar.
     */
    public static function insertImport(string $content, string $importLine, string $module): ?string
    {
        if (str_contains($content, $importLine)) {
            return $content;
        }

        $lines = explode("\n", $content);
        [$inicio, $fim] = self::scriptBlockRange($lines);
        $interno = str_starts_with($module, '@/');
        $ultimo = null;

        for ($number = $inicio; $number < $fim; $number++) {
            $line = $lines[$number];

            if (preg_match(self::IMPORT, $line, $match) !== 1) {
                continue;
            }

            if (str_starts_with($match['module'], '@/') !== $interno) {
                continue;
            }

            $ultimo = $number;

            if (strcasecmp($match['module'], $module) > 0) {
                array_splice($lines, $number, 0, [self::indentOf($line) . $importLine]);

                return implode("\n", $lines);
            }
        }

        if ($ultimo === null) {
            return null;
        }

        array_splice($lines, $ultimo + 1, 0, [self::indentOf($lines[$ultimo]) . $importLine]);

        return implode("\n", $lines);
    }

    /**
     * Faixa de linhas `[início, fim)` onde `insertImport()` procura e insere.
     *
     * Um `.tsx` não tem tag `<script>` nenhuma — a faixa é o arquivo inteiro. Um `.vue` do
     * starter kit tem só um bloco `<script setup>` — também vale o arquivo inteiro, porque
     * não há ambiguidade para resolver. Um `.svelte` tem dois: `<script module>`, e o de
     * instância. Escolhemos o de instância porque é lá que o starter kit organiza os
     * imports de componente — os mesmos que `<AppearanceTabs />` e companhia usam no
     * template. Se nenhum bloco identificar como não-module (arquivo atípico), cai de volta
     * para o arquivo inteiro em vez de devolver uma faixa vazia.
     *
     * @param array<int, string> $lines
     * @return array{0: int, 1: int}
     */
    private static function scriptBlockRange(array $lines): array
    {
        $blocks = [];
        $inicio = null;
        $module = false;

        foreach ($lines as $number => $line) {
            if ($inicio === null && preg_match('/^\s*<script\b([^>]*)>/i', $line, $tag) === 1) {
                $inicio = $number;
                $module = (bool) preg_match('/\bmodule\b/', $tag[1]);
                continue;
            }

            if ($inicio !== null && preg_match('#^\s*</script>#i', $line) === 1) {
                $blocks[] = ['inicio' => $inicio, 'fim' => $number, 'module' => $module];
                $inicio = null;
            }
        }

        if (count($blocks) <= 1) {
            return [0, count($lines)];
        }

        foreach ($blocks as $bloco) {
            if (!$bloco['module']) {
                return [$bloco['inicio'] + 1, $bloco['fim']];
            }
        }

        return [0, count($lines)];
    }

    private static function indentOf(string $line): string
    {
        preg_match('/^\s*/', $line, $indent);

        return $indent[0];
    }
}
