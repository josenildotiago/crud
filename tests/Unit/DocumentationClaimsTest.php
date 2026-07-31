<?php

namespace Crud\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Achado 9: duas afirmações de documentação que não batiam com o código.
 *
 * Não é teste de comportamento — é o mínimo automatizável para uma correção de prosa:
 * trava o texto errado fora e o texto certo dentro, para a próxima revisão não reintroduzir
 * a mesma afirmação falsa sem que a suíte reclame.
 */
class DocumentationClaimsTest extends TestCase
{
    private function readmeContent(): string
    {
        return file_get_contents(__DIR__ . '/../../README.md');
    }

    private function specContent(): string
    {
        return file_get_contents(__DIR__ . '/../../docs/superpowers/specs/2026-07-30-paleta-camada-design.md');
    }

    /**
     * `crud:create-palette` não tem prompt de identificador — o id sai do `Str::slug()`
     * do nome (`src/Console/CreatePaletteCommand.php`). O README dizia que os prompts
     * guiavam por "Nome e identificador da paleta", implicando os dois separados.
     */
    public function test_readme_nao_afirma_prompt_de_identificador_separado(): void
    {
        $readme = $this->readmeContent();

        $this->assertStringNotContainsString('Nome e identificador da paleta', $readme);
        $this->assertStringContainsString(
            'o identificador sai sozinho do nome, via slug',
            $readme
        );
    }

    /**
     * `InstallPaletteCommand::resolveStack()` erra e sai (`FAILURE`) quando o projeto não
     * bate com nenhuma das três stacks — não pergunta nada, ao contrário do que a spec
     * dizia no item 5 da seção de testes.
     */
    public function test_spec_nao_afirma_que_projeto_sem_stack_pergunta_antes(): void
    {
        $spec = $this->specContent();

        $this->assertStringNotContainsString(
            'um projeto que não casa com nenhuma das três pergunta antes de instalar',
            $spec
        );
        $this->assertStringContainsString('erra e sai', $spec);
    }
}
