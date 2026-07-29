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
}
