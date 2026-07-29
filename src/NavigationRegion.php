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
     * Devolve null se a âncora ou o fechamento não forem encontrados. Falhar aqui é
     * seguro: quem chama cai no caminho de imprimir o trecho para colar à mão.
     */
    public function install(string $content, string $openPattern, string $importLine): ?string
    {
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
     * Devolve null se a região não existir ou estiver malformada; escrever pela metade
     * seria pior que não escrever.
     */
    public function upsert(string $content, string $key, string $item): ?string
    {
        $lines = preg_split('/\R/', $content);

        [$startLine, $endLine] = $this->locate($lines);

        if ($startLine === null || $endLine === null) {
            return null;
        }

        $indent = $this->indentOf($lines[$startLine]);

        $body = array_values(array_filter(
            array_slice($lines, $startLine + 1, $endLine - $startLine - 1),
            static fn (string $line): bool => trim($line) !== ''
        ));

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

        foreach ($lines as $i => $line) {
            if (str_starts_with(trim($line), 'import ')) {
                $lastImport = $i;
            }
        }

        $at = $lastImport === null ? 0 : $lastImport + 1;

        return implode("\n", array_merge(
            array_slice($lines, 0, $at),
            [$importLine],
            array_slice($lines, $at)
        ));
    }
}
