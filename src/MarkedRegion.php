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
     */
    public function replace(string $content, string $block): ?string
    {
        if (!$this->exists($content)) {
            return null;
        }

        $lines = explode("\n", $content);
        $start = null;
        $end = null;

        foreach ($lines as $number => $line) {
            if (str_contains($line, $this->startMarker)) {
                $start = $number;
            }

            if (str_contains($line, $this->endMarker)) {
                $end = $number;
                break;
            }
        }

        if ($start === null || $end === null || $end < $start) {
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
     */
    public function remove(string $content): ?string
    {
        if (!$this->exists($content)) {
            return null;
        }

        $lines = explode("\n", $content);
        $start = null;
        $end = null;

        foreach ($lines as $number => $line) {
            if (str_contains($line, $this->startMarker)) {
                $start = $number;
            }

            if (str_contains($line, $this->endMarker)) {
                $end = $number;
                break;
            }
        }

        if ($start === null || $end === null || $end < $start) {
            return null;
        }

        array_splice($lines, $start, $end - $start + 1);

        return implode("\n", $lines);
    }

    /**
     * Insere a linha de import na posição alfabética dentro do grupo do módulo.
     *
     * O `import/order` do starter kit é gate de CI deles: import fora de ordem quebra a
     * build do usuário. Grupo aqui é "começa com `@/`" ou "não começa"; dentro do grupo a
     * ordem é alfabética, insensível a caixa, como o eslint pede.
     *
     * Devolve o conteúdo intacto se o módulo já estiver importado, e null se o arquivo não
     * tiver import nenhum — sem âncora, não há onde acertar.
     */
    public static function insertImport(string $content, string $importLine, string $module): ?string
    {
        if (str_contains($content, $importLine)) {
            return $content;
        }

        $lines = explode("\n", $content);
        $interno = str_starts_with($module, '@/');
        $ultimo = null;

        foreach ($lines as $number => $line) {
            if (preg_match(self::IMPORT, $line, $match) !== 1) {
                continue;
            }

            if (str_starts_with($match['module'], '@/') !== $interno) {
                continue;
            }

            $ultimo = $number;

            if (strcasecmp($match['module'], $module) > 0) {
                array_splice($lines, $number, 0, [$importLine]);

                return implode("\n", $lines);
            }
        }

        if ($ultimo === null) {
            return null;
        }

        array_splice($lines, $ultimo + 1, 0, [$importLine]);

        return implode("\n", $lines);
    }
}
