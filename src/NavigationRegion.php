<?php

namespace Crud;

/**
 * Manipula a região da navegação do usuário delimitada por comentários.
 *
 * Trabalha só com texto: recebe o conteúdo do arquivo e devolve o novo conteúdo, ou
 * `null` quando não há como escrever com segurança. Não conhece stack nenhuma — quem
 * chama informa os marcadores, que acompanham a sintaxe de comentário do arquivo alvo
 * (`//` em TSX e Svelte, `{{-- --}}` em Blade).
 */
final class NavigationRegion
{
    /**
     * Import que abre e fecha na mesma linha, em qualquer forma: `import X from 'm';`,
     * `import { A, B } from 'm';`, `import type { T } from 'm';`.
     *
     * Import multilinha não casa de propósito: a primeira linha dele também começa com
     * `import `, e ancorar ali colocaria a linha nova entre as chaves.
     */
    private const SINGLE_LINE_IMPORT = '/^\s*import\s.+\bfrom\s+(?<module>\'[^\']+\'|"[^"]+");$/';

    public function __construct(
        private readonly string $startMarker,
        private readonly string $endMarker,
    ) {
    }

    /**
     * Cria a região vazia dentro do array de navegação e garante o import do ícone.
     *
     * Este é o único momento em que o pacote escreve fora de uma região que já
     * controla, e por isso depende de uma âncora: $openPattern casa a linha que abre
     * o array, e a região entra antes do primeiro fechamento depois dela — assim os
     * itens gerados ficam abaixo do que o usuário já tem.
     *
     * Devolve null se a âncora não for encontrada, se o fechamento não for encontrado,
     * ou se a região já existe. Falhar aqui é seguro: quem chama cai no caminho de
     * imprimir o trecho para colar à mão.
     */
    public function install(string $content, string $openPattern, string $importLine): ?string
    {
        if (str_contains($content, $this->startMarker)) {
            return null;
        }

        $lines = preg_split('/\R/', $content);

        $openLine = null;

        foreach ($lines as $i => $line) {
            if (preg_match($openPattern, $line) === 1) {
                $openLine = $i;
                break;
            }
        }

        if ($openLine === null) {
            return null;
        }

        $closeLine = null;

        foreach (array_slice($lines, $openLine + 1, null, true) as $i => $line) {
            if (trim($line) === '];') {
                $closeLine = $i;
                break;
            }
        }

        if ($closeLine === null) {
            return null;
        }

        $indent = $this->indentOf($lines[$openLine]) . '    ';

        $lines = array_merge(
            array_slice($lines, 0, $closeLine),
            [$indent . $this->startMarker, $indent . $this->endMarker],
            array_slice($lines, $closeLine)
        );

        return $this->ensureImport(implode("\n", $lines), $importLine);
    }

    /**
     * Insere ou substitui um item na região.
     *
     * $key identifica o item para fins de idempotência — na prática, o trecho do href.
     * Devolve null se a região não existir, estiver malformada ou tiver item quebrado
     * em várias linhas; escrever pela metade seria pior que não escrever.
     */
    public function upsert(string $content, string $key, string $item): ?string
    {
        $lines = preg_split('/\R/', $content);

        [$startLine, $endLine] = $this->locate($lines);

        if ($startLine === null || $endLine === null) {
            return null;
        }

        $indent = $this->indentOf($lines[$startLine]);

        $body = $this->bodyOf($lines, $startLine, $endLine);

        if (!$this->isOneItemPerLine($body)) {
            return null;
        }

        $replaced = false;

        foreach ($body as $i => $line) {
            if (str_contains($line, $key)) {
                $body[$i] = $indent . $item;
                $replaced = true;
                break;
            }
        }

        if (!$replaced) {
            $body[] = $indent . $item;
        }

        return implode("\n", array_merge(
            array_slice($lines, 0, $startLine + 1),
            $body,
            array_slice($lines, $endLine)
        ));
    }

    /**
     * A região existe e tem item quebrado em mais de uma linha.
     *
     * Consulta para quem chama distinguir as duas causas de `upsert()` devolver null:
     * marcador faltando é defeito no arquivo, item reformatado é o `npm run format`
     * fazendo o trabalho dele. As mensagens são diferentes, e trocá-las manda o
     * usuário procurar um problema que não existe.
     */
    public function hasMultilineItem(string $content): bool
    {
        $lines = preg_split('/\R/', $content);

        [$startLine, $endLine] = $this->locate($lines);

        if ($startLine === null || $endLine === null) {
            return false;
        }

        return !$this->isOneItemPerLine($this->bodyOf($lines, $startLine, $endLine));
    }

    /**
     * Linhas não vazias entre os marcadores.
     *
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    private function bodyOf(array $lines, int $startLine, int $endLine): array
    {
        return array_values(array_filter(
            array_slice($lines, $startLine + 1, $endLine - $startLine - 1),
            static fn (string $line): bool => trim($line) !== ''
        ));
    }

    /**
     * Toda linha da região é um item inteiro.
     *
     * A idempotência do `upsert()` é linha a linha, então ela só está correta enquanto
     * item e linha forem a mesma coisa. O `npm run format` do starter kit quebra o item
     * em várias linhas assim que ele passa das 80 colunas — o que acontece com nome de
     * tabela médio — e daí substituir a linha do href deixaria as irmãs órfãs e o
     * arquivo sem compilar. Chave que abre e não fecha na mesma linha é a assinatura
     * disso, e recusar devolve o controle a quem chama.
     *
     * @param array<int, string> $body
     */
    private function isOneItemPerLine(array $body): bool
    {
        foreach ($body as $line) {
            if (substr_count($line, '{') !== substr_count($line, '}')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Índices das linhas dos marcadores, ou [null, null] se a região for inválida.
     *
     * O marcador final só conta depois do inicial, então `end` antes de `start` cai
     * no mesmo caminho de "não encontrado".
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

    private function indentOf(string $line): string
    {
        return substr($line, 0, strlen($line) - strlen(ltrim($line)));
    }

    /**
     * Acrescenta o import depois do último já existente, se ainda não estiver lá.
     */
    private function ensureImport(string $content, string $importLine): string
    {
        if (str_contains($content, $importLine)) {
            return $content;
        }

        $lines = preg_split('/\R/', $content);

        $lastImport = null;
        $lastExternalImport = null;

        foreach ($lines as $i => $line) {
            if (preg_match(self::SINGLE_LINE_IMPORT, $line, $parsed) !== 1) {
                continue;
            }

            $lastImport = $i;

            if (!$this->isLocalModule($parsed['module'])) {
                $lastExternalImport = $i;
            }
        }

        // Depois do último import de módulo externo, e não simplesmente depois do último
        // import: no app-sidebar.tsx dos starter kits os imports `@/` vêm por último, e
        // uma linha de `lucide-react` colada ali viola a regra `import/order` do eslint
        // deles — cujo `npm run lint:check` é gate de CI e passaria a falhar por causa
        // da nossa edição. Fundir o símbolo no import que já existe também resolveria o
        // eslint, mas passaria a linha de 80 colunas e quebraria o `prettier --check`.
        $anchor = $lastExternalImport ?? $lastImport;
        $at = $anchor === null ? 0 : $anchor + 1;

        return implode("\n", array_merge(
            array_slice($lines, 0, $at),
            [$importLine],
            array_slice($lines, $at)
        ));
    }

    /**
     * Módulo do próprio projeto — alias `@/` ou caminho relativo.
     *
     * O `$module` chega com as aspas, como casado pelo regex.
     */
    private function isLocalModule(string $module): bool
    {
        $path = trim($module, '\'"');

        return str_starts_with($path, '@/')
            || str_starts_with($path, './')
            || str_starts_with($path, '../');
    }
}
