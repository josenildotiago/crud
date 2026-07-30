# Camada de paleta — plano de implementação (5.0.0)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Trocar o sistema de temas por uma camada de paleta que nunca toca no que é do starter kit, encerrando a colisão que hoje quebra o `tsc` do projeto do usuário. Vale para react, vue e svelte; livewire é outra spec.

**Architecture:** As paletas viram CSS estático com seletor `:root[data-crud-palette='x']` e `:root.dark[data-crud-palette='x']`. O único trabalho do JavaScript é escrever o atributo no `<html>` e persistir no `localStorage`. O claro/escuro continua sendo da classe `.dark` do starter kit, e nenhum código do pacote observa, importa ou reaplica nada quando ele muda.

**Tech Stack:** PHP 8.2+, Laravel 12/13, `laravel/prompts`, PHPUnit 11/12 com Orchestra Testbench, stubs CSS/TypeScript/TSX para React 19 + Tailwind v4.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-30-paleta-camada-design.md`. Em conflito, a spec vence.
- Mensagens de console e prompts em **português**; mensagens de commit em **inglês**.
- Nunca sobrescrever arquivo do usuário sem `confirm()` ou `--force`.
- Quando não houver como escrever com segurança, **não escrever**: avisar, imprimir o trecho para colar e seguir para o próximo arquivo.
- Ler config sempre com default: `config('crud.x.y', $default)`.
- Rodar o comando duas vezes tem que produzir arquivos idênticos.
- Uma paleta define exatamente nove variáveis: `--primary`, `--primary-foreground`, `--ring`, `--sidebar-primary`, `--sidebar-primary-foreground`, `--sidebar-ring`, `--chart-1`, `--chart-2`, `--chart-3`. Nenhuma superfície.
- A paleta `default` não emite bloco CSS e corresponde à ausência do atributo.
- Chave do `localStorage`: `crud-palette`.
- Verificação a cada tarefa: `vendor/bin/phpunit` e `vendor/bin/phpstan analyse` sem erro.
- Trabalhar na branch `palette-layer`. Sem `git push`. Tag só depois de confirmação do dono.

## Estrutura de arquivos

**Criar**

| Arquivo | Responsabilidade |
|---|---|
| `src/MarkedRegion.php` | região marcada e inserção ordenada de import, só texto |
| `src/stubs/palette/crud-palettes.css.stub` | as quatro paletas |
| `src/stubs/palette/crud-palette.ts.stub` | ids, leitura/escrita do atributo, persistência |
| `src/stubs/palette/crud-palette-selector.tsx.stub` | o seletor React |
| `src/Console/InstallPaletteCommand.php` | escreve os stubs, faz as três edições, limpa o legado |
| `src/Console/CreatePaletteCommand.php` | acrescenta uma paleta ao CSS e à lista |
| `tests/Unit/MarkedRegionTest.php` | texto puro |
| `tests/Unit/InstallPaletteCommandTest.php` | filesystem temporário |
| `tests/Unit/CreatePaletteCommandTest.php` | filesystem temporário |

**Modificar**

`src/CrudServiceProvider.php`, `src/CrudManager.php`, `src/Facades/Crud.php`, `src/config/crud.php`, `src/Console/InstallCommand.php`, `src/stubs/react/{Index,Create,Edit,Show}.stub`, `tests/Unit/GeneratedLintContractTest.php`, `README.md`, `CHANGELOG.md`, `CLAUDE.md`.

**Apagar**

`src/Console/InstallThemeSystemCommand.php`, `src/Console/CreateThemeCommand.php`, `src/config/themes.php`, e os oito stubs de tema em `src/stubs/react/` (`themes.ts.stub`, `use-appearance.tsx.stub`, `theme-selector.tsx.stub`, `appearance-dropdown.tsx.stub`, `appearance-tabs.tsx.stub`, `appearance-theme-selector.tsx.stub`, `theme-demo.tsx.stub`, `ThemeExample.tsx.stub`).

---

### Task 1: `MarkedRegion`

**Files:**
- Create: `src/MarkedRegion.php`
- Test: `tests/Unit/MarkedRegionTest.php`

**Interfaces:**
- Consumes: nada.
- Produces:
  ```php
  new MarkedRegion(string $startMarker, string $endMarker)
  public function exists(string $content): bool
  public function install(string $content, string $anchorPattern, string $block): ?string
  public function replace(string $content, string $block): ?string
  public function remove(string $content): ?string
  public static function insertImport(string $content, string $importLine, string $module): ?string
  ```
  `insertImport` é estática de propósito: ela não usa os marcadores, e instanciar a classe com marcadores vazios só para chamá-la deixaria `exists()` respondendo `true` para qualquer conteúdo.
  Todo método que não consegue escrever com segurança devolve `null`. `install` casa a **primeira** ocorrência da âncora, indenta o bloco com o mesmo recuo da linha da âncora, e devolve `null` se a região já existe.

- [ ] **Step 1: Escrever os testes que falham**

```php
<?php

namespace Crud\Tests\Unit;

use Crud\MarkedRegion;
use PHPUnit\Framework\TestCase;

class MarkedRegionTest extends TestCase
{
    private function region(): MarkedRegion
    {
        return new MarkedRegion('{/* crud:palette:start */}', '{/* crud:palette:end */}');
    }

    private function page(): string
    {
        return <<<'TSX'
        export default function Appearance() {
            return (
                <div className="space-y-6">
                    <AppearanceTabs />
                </div>
            );
        }
        TSX;
    }

    public function test_instala_a_regiao_logo_depois_da_ancora(): void
    {
        $result = $this->region()->install($this->page(), '/<AppearanceTabs \/>/', '<CrudPaletteSelector />');

        $this->assertStringContainsString(
            "            <AppearanceTabs />\n"
                . "            {/* crud:palette:start */}\n"
                . "            <CrudPaletteSelector />\n"
                . "            {/* crud:palette:end */}",
            $result
        );
    }

    public function test_sem_ancora_nao_escreve(): void
    {
        $this->assertNull(
            $this->region()->install('<div>nada aqui</div>', '/<AppearanceTabs \/>/', '<X />')
        );
    }

    public function test_regiao_ja_instalada_nao_duplica(): void
    {
        $region = $this->region();
        $once = $region->install($this->page(), '/<AppearanceTabs \/>/', '<CrudPaletteSelector />');

        $this->assertNull($region->install($once, '/<AppearanceTabs \/>/', '<CrudPaletteSelector />'));
    }

    public function test_ancora_repetida_casa_a_primeira(): void
    {
        $content = "<AppearanceTabs />\n<hr />\n<AppearanceTabs />";
        $result = $this->region()->install($content, '/<AppearanceTabs \/>/', '<X />');

        $this->assertSame(
            "<AppearanceTabs />\n{/* crud:palette:start */}\n<X />\n{/* crud:palette:end */}\n<hr />\n<AppearanceTabs />",
            $result
        );
    }

    public function test_replace_troca_so_o_conteudo_da_regiao(): void
    {
        $region = $this->region();
        $installed = $region->install($this->page(), '/<AppearanceTabs \/>/', '<Antigo />');

        $result = $region->replace($installed, '<Novo />');

        $this->assertStringContainsString('<Novo />', $result);
        $this->assertStringNotContainsString('<Antigo />', $result);
        $this->assertStringContainsString('<AppearanceTabs />', $result);
    }

    public function test_replace_sem_regiao_devolve_null(): void
    {
        $this->assertNull($this->region()->replace($this->page(), '<X />'));
    }

    public function test_remove_tira_marcadores_e_conteudo(): void
    {
        $region = $this->region();
        $installed = $region->install($this->page(), '/<AppearanceTabs \/>/', '<X />');

        $result = $region->remove($installed);

        $this->assertStringNotContainsString('crud:palette', $result);
        $this->assertStringNotContainsString('<X />', $result);
        $this->assertStringContainsString('<AppearanceTabs />', $result);
    }

    public function test_remove_sem_regiao_devolve_null(): void
    {
        $this->assertNull($this->region()->remove($this->page()));
    }

    public function test_import_entra_em_ordem_alfabetica_no_grupo(): void
    {
        $content = <<<'TSX'
        import { Head } from '@inertiajs/react';
        import AppearanceTabs from '@/components/appearance-tabs';
        import Heading from '@/components/heading';
        import AppLayout from '@/layouts/app-layout';

        export default function Appearance() {}
        TSX;

        $result = MarkedRegion::insertImport(
            $content,
            "import { CrudPaletteSelector } from '@/components/crud-palette-selector';",
            '@/components/crud-palette-selector'
        );

        $this->assertStringContainsString(
            "import AppearanceTabs from '@/components/appearance-tabs';\n"
                . "import { CrudPaletteSelector } from '@/components/crud-palette-selector';\n"
                . "import Heading from '@/components/heading';",
            $result
        );
    }

    public function test_import_depois_do_ultimo_quando_ordena_por_ultimo(): void
    {
        $content = "import AppLayout from '@/layouts/app-layout';\n\nexport default function A() {}";

        $result = MarkedRegion::insertImport(
            $content,
            "import { initializeCrudPalette } from '@/lib/crud-palette';",
            '@/lib/crud-palette'
        );

        $this->assertStringContainsString(
            "import AppLayout from '@/layouts/app-layout';\n"
                . "import { initializeCrudPalette } from '@/lib/crud-palette';",
            $result
        );
    }

    public function test_import_ja_presente_devolve_o_conteudo_intacto(): void
    {
        $content = "import { initializeCrudPalette } from '@/lib/crud-palette';\n";

        $this->assertSame(
            $content,
            MarkedRegion::insertImport($content, "import { initializeCrudPalette } from '@/lib/crud-palette';", '@/lib/crud-palette')
        );
    }

    public function test_arquivo_sem_import_nenhum_devolve_null(): void
    {
        $this->assertNull(
            MarkedRegion::insertImport('const a = 1;', "import X from '@/x';", '@/x')
        );
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `vendor/bin/phpunit --filter MarkedRegionTest`
Expected: FAIL com `Class "Crud\MarkedRegion" not found`.

- [ ] **Step 3: Implementar**

```php
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
```

- [ ] **Step 4: Rodar até passar**

Run: `vendor/bin/phpunit --filter MarkedRegionTest`
Expected: PASS, 12 testes.

- [ ] **Step 5: Conferir a análise estática**

Run: `vendor/bin/phpstan analyse --no-progress`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/MarkedRegion.php tests/Unit/MarkedRegionTest.php
git commit -m "Add MarkedRegion for edits inside the user's own files"
```

---

### Task 2: Os três stubs da paleta

**Files:**
- Create: `src/stubs/palette/crud-palettes.css.stub`, `src/stubs/palette/crud-palette.ts.stub`,
  `src/stubs/palette/crud-palette-selector.tsx.stub`, `src/stubs/palette/CrudPaletteSelector.vue.stub`,
  `src/stubs/palette/CrudPaletteSelector.svelte.stub`
- Modify: `tests/Unit/GeneratedLintContractTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: o contrato TypeScript que os seletores e o arquivo de entrada da stack consomem —
  ```ts
  export const palettes: { id: string; name: string }[];
  export function getPalette(): string;
  export function setPalette(id: string): void;
  export function initializeCrudPalette(): void;
  ```
  Ids das paletas: `default`, `azul`, `verde`, `roxo`, `vermelho`.

- [ ] **Step 1: Escrever o teste de contrato de lint que falha**

Acrescentar ao final de `tests/Unit/GeneratedLintContractTest.php`, dentro da classe:

```php
    /**
     * O seletor não passa por replacement nenhum — é arquivo pronto — mas é TSX que o
     * pacote escreve no projeto do usuário, então vale o mesmo contrato dos componentes.
     */
    public function test_os_seletores_de_paleta_respeitam_o_contrato_de_lint(): void
    {
        $stubs = [
            'crud-palette-selector.tsx.stub',
            'CrudPaletteSelector.vue.stub',
            'CrudPaletteSelector.svelte.stub',
        ];

        foreach ($stubs as $stub) {
            $this->assertSelectorImportsAreOrdered($stub);
        }
    }

    /**
     * Os três starter kits impõem o mesmo `import/order`: externos antes dos `@/`, cada
     * grupo em ordem alfabética. Em `.vue` e `.svelte` os imports vivem dentro do bloco
     * `<script>`, e a varredura por linha acha do mesmo jeito.
     */
    private function assertSelectorImportsAreOrdered(string $stub): void
    {
        $rendered = file_get_contents(__DIR__ . '/../../src/stubs/palette/' . $stub);

        $this->assertIsString($rendered, "stub {$stub} não encontrado");

        $modules = $this->importedModules($rendered);
        $internos = false;

        foreach ($modules as $module) {
            if (str_starts_with($module, '@/')) {
                $internos = true;
                continue;
            }

            $this->assertFalse($internos, "{$stub}: `{$module}` é externo e veio depois de um import `@/`.");
        }

        $grupos = [
            'externo' => array_values(array_filter($modules, static fn (string $m): bool => !str_starts_with($m, '@/'))),
            'interno' => array_values(array_filter($modules, static fn (string $m): bool => str_starts_with($m, '@/'))),
        ];

        foreach ($grupos as $nome => $grupo) {
            $ordenado = $grupo;
            usort($ordenado, static fn (string $a, string $b): int => strcasecmp($a, $b));

            $this->assertSame($ordenado, $grupo, "{$stub}: o grupo {$nome} não está em ordem alfabética.");
        }
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `vendor/bin/phpunit --filter test_os_seletores_de_paleta_respeitam_o_contrato_de_lint`
Expected: FAIL — `file_get_contents` não acha os stubs.

- [ ] **Step 3: Escrever `src/stubs/palette/crud-palettes.css.stub`**

```css
/*
 * Paletas do pacote josenildotiago/crud.
 *
 * Cada paleta define só os acentos. Superfície, texto e borda continuam sendo do seu
 * app: é o que mantém o contraste de claro/escuro sob seu controle.
 *
 * A paleta "Padrão" não aparece aqui de propósito — ela é a ausência do atributo, ou
 * seja, as cores que você já tem.
 */

:root[data-crud-palette='azul'] {
    --primary: oklch(0.55 0.19 250);
    --primary-foreground: oklch(0.985 0 0);
    --ring: oklch(0.55 0.19 250);
    --sidebar-primary: oklch(0.55 0.19 250);
    --sidebar-primary-foreground: oklch(0.985 0 0);
    --sidebar-ring: oklch(0.55 0.19 250);
    --chart-1: oklch(0.55 0.19 250);
    --chart-2: oklch(0.65 0.15 275);
    --chart-3: oklch(0.75 0.11 300);
}

:root.dark[data-crud-palette='azul'] {
    --primary: oklch(0.7 0.16 250);
    --primary-foreground: oklch(0.21 0.03 250);
    --ring: oklch(0.7 0.16 250);
    --sidebar-primary: oklch(0.7 0.16 250);
    --sidebar-primary-foreground: oklch(0.21 0.03 250);
    --sidebar-ring: oklch(0.7 0.16 250);
    --chart-1: oklch(0.7 0.16 250);
    --chart-2: oklch(0.62 0.14 275);
    --chart-3: oklch(0.54 0.12 300);
}

:root[data-crud-palette='verde'] {
    --primary: oklch(0.55 0.19 155);
    --primary-foreground: oklch(0.985 0 0);
    --ring: oklch(0.55 0.19 155);
    --sidebar-primary: oklch(0.55 0.19 155);
    --sidebar-primary-foreground: oklch(0.985 0 0);
    --sidebar-ring: oklch(0.55 0.19 155);
    --chart-1: oklch(0.55 0.19 155);
    --chart-2: oklch(0.65 0.15 180);
    --chart-3: oklch(0.75 0.11 205);
}

:root.dark[data-crud-palette='verde'] {
    --primary: oklch(0.7 0.16 155);
    --primary-foreground: oklch(0.21 0.03 155);
    --ring: oklch(0.7 0.16 155);
    --sidebar-primary: oklch(0.7 0.16 155);
    --sidebar-primary-foreground: oklch(0.21 0.03 155);
    --sidebar-ring: oklch(0.7 0.16 155);
    --chart-1: oklch(0.7 0.16 155);
    --chart-2: oklch(0.62 0.14 180);
    --chart-3: oklch(0.54 0.12 205);
}

:root[data-crud-palette='roxo'] {
    --primary: oklch(0.55 0.19 300);
    --primary-foreground: oklch(0.985 0 0);
    --ring: oklch(0.55 0.19 300);
    --sidebar-primary: oklch(0.55 0.19 300);
    --sidebar-primary-foreground: oklch(0.985 0 0);
    --sidebar-ring: oklch(0.55 0.19 300);
    --chart-1: oklch(0.55 0.19 300);
    --chart-2: oklch(0.65 0.15 325);
    --chart-3: oklch(0.75 0.11 350);
}

:root.dark[data-crud-palette='roxo'] {
    --primary: oklch(0.7 0.16 300);
    --primary-foreground: oklch(0.21 0.03 300);
    --ring: oklch(0.7 0.16 300);
    --sidebar-primary: oklch(0.7 0.16 300);
    --sidebar-primary-foreground: oklch(0.21 0.03 300);
    --sidebar-ring: oklch(0.7 0.16 300);
    --chart-1: oklch(0.7 0.16 300);
    --chart-2: oklch(0.62 0.14 325);
    --chart-3: oklch(0.54 0.12 350);
}

:root[data-crud-palette='vermelho'] {
    --primary: oklch(0.55 0.19 25);
    --primary-foreground: oklch(0.985 0 0);
    --ring: oklch(0.55 0.19 25);
    --sidebar-primary: oklch(0.55 0.19 25);
    --sidebar-primary-foreground: oklch(0.985 0 0);
    --sidebar-ring: oklch(0.55 0.19 25);
    --chart-1: oklch(0.55 0.19 25);
    --chart-2: oklch(0.65 0.15 50);
    --chart-3: oklch(0.75 0.11 75);
}

:root.dark[data-crud-palette='vermelho'] {
    --primary: oklch(0.7 0.16 25);
    --primary-foreground: oklch(0.21 0.03 25);
    --ring: oklch(0.7 0.16 25);
    --sidebar-primary: oklch(0.7 0.16 25);
    --sidebar-primary-foreground: oklch(0.21 0.03 25);
    --sidebar-ring: oklch(0.7 0.16 25);
    --chart-1: oklch(0.7 0.16 25);
    --chart-2: oklch(0.62 0.14 50);
    --chart-3: oklch(0.54 0.12 75);
}
```

- [ ] **Step 4: Escrever `src/stubs/palette/crud-palette.ts.stub`**

```ts
/**
 * Paleta de cores — camada do pacote josenildotiago/crud.
 *
 * As cores moram no CSS (`resources/css/crud-palettes.css`); este arquivo só conhece ids.
 * Claro/escuro não passa por aqui: continua sendo da classe `.dark` do seu starter kit.
 */

const STORAGE_KEY = 'crud-palette';

export const palettes: { id: string; name: string }[] = [
    { id: 'default', name: 'Padrão' },
    { id: 'azul', name: 'Azul' },
    { id: 'verde', name: 'Verde' },
    { id: 'roxo', name: 'Roxo' },
    { id: 'vermelho', name: 'Vermelho' },
];

export function getPalette(): string {
    if (typeof window === 'undefined') {
        return 'default';
    }

    const stored = localStorage.getItem(STORAGE_KEY);

    // Id órfão (paleta apagada do CSS) cai no padrão: a tela nunca fica sem cor definida.
    if (!stored || !palettes.some((palette) => palette.id === stored)) {
        return 'default';
    }

    return stored;
}

export function setPalette(id: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem(STORAGE_KEY, id);

    if (id === 'default') {
        document.documentElement.removeAttribute('data-crud-palette');

        return;
    }

    document.documentElement.setAttribute('data-crud-palette', id);
}

export function initializeCrudPalette(): void {
    setPalette(getPalette());
}
```

- [ ] **Step 5: Escrever `src/stubs/palette/crud-palette-selector.tsx.stub`**

Ordem dos imports conferida contra o `import/order` dos starter kits: externos primeiro,
alfabéticos; depois os `@/`, alfabéticos. Vale igual nos três kits — os três configuram
`import/order` com `alphabetize: { order: 'asc', caseInsensitive: true }`.

```tsx
import { Check } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { getPalette, palettes, setPalette } from '@/lib/crud-palette';

export function CrudPaletteSelector() {
    const [selected, setSelected] = useState<string>(() => getPalette());

    const choose = (id: string) => {
        setPalette(id);
        setSelected(id);
    };

    return (
        <div className="space-y-2">
            <p className="text-sm font-medium">Paleta de cores</p>

            <div className="flex flex-wrap gap-2">
                {palettes.map((palette) => (
                    <Button
                        key={palette.id}
                        variant={selected === palette.id ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => choose(palette.id)}
                    >
                        {selected === palette.id && <Check className="mr-1 h-4 w-4" />}
                        {palette.name}
                    </Button>
                ))}
            </div>
        </div>
    );
}
```

- [ ] **Step 6: Escrever `src/stubs/palette/CrudPaletteSelector.vue.stub`**

**Atenção ao ler este stub:** o `{{ palette.name }}` do template é interpolação do Vue, com
espaços. Ele não é placeholder do pacote — os do pacote são `{{semEspaco}}` — e este arquivo
nunca passa por `str_replace`: o `InstallPaletteCommand` copia byte a byte. Não "consertar".

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { getPalette, palettes, setPalette } from '@/lib/crud-palette';

const selected = ref<string>(getPalette());

const choose = (id: string) => {
    setPalette(id);
    selected.value = id;
};
</script>

<template>
    <div class="space-y-2">
        <p class="text-sm font-medium">Paleta de cores</p>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="palette in palettes"
                :key="palette.id"
                type="button"
                class="rounded-md border px-3 py-1 text-sm"
                :class="selected === palette.id ? 'bg-primary text-primary-foreground' : ''"
                @click="choose(palette.id)"
            >
                {{ palette.name }}
            </button>
        </div>
    </div>
</template>
```

- [ ] **Step 7: Escrever `src/stubs/palette/CrudPaletteSelector.svelte.stub`**

O starter kit svelte é Svelte 5: estado reativo é `$state`, como em `lib/theme.svelte.ts` do
próprio kit.

```svelte
<script lang="ts">
    import { getPalette, palettes, setPalette } from '@/lib/crud-palette';

    let selected = $state(getPalette());

    function choose(id: string) {
        setPalette(id);
        selected = id;
    }
</script>

<div class="space-y-2">
    <p class="text-sm font-medium">Paleta de cores</p>

    <div class="flex flex-wrap gap-2">
        {#each palettes as palette (palette.id)}
            <button
                type="button"
                class="rounded-md border px-3 py-1 text-sm"
                class:bg-primary={selected === palette.id}
                class:text-primary-foreground={selected === palette.id}
                onclick={() => choose(palette.id)}
            >
                {palette.name}
            </button>
        {/each}
    </div>
</div>
```

- [ ] **Step 8: Rodar o teste de contrato**

Run: `vendor/bin/phpunit --filter GeneratedLintContractTest`
Expected: PASS, 11 testes.

- [ ] **Step 9: Commit**

```bash
git add src/stubs/palette tests/Unit/GeneratedLintContractTest.php
git commit -m "Add the palette stubs: four palettes, the id module and three selectors"
```

---

### Task 3: `crud:install-palette` detecta a stack e escreve os arquivos

**Files:**
- Create: `src/Console/InstallPaletteCommand.php`, `tests/Unit/InstallPaletteCommandTest.php`
- Modify: `src/CrudServiceProvider.php:33-38` (registrar o comando novo, sem tirar os antigos ainda)

**Interfaces:**
- Consumes: os stubs da Task 2.
- Produces:
  ```php
  class InstallPaletteCommand extends Command
  protected $signature = 'crud:install-palette
                          {--stack= : react, vue ou svelte (padrão: detectar pelo projeto)}
                          {--force : Sobrescreve arquivos existentes sem perguntar}';
  ```
  Escreve `resources/css/crud-palettes.css` e `resources/js/lib/crud-palette.ts` — iguais nas três stacks — mais o seletor da stack detectada. A detecção é pela página de aparência, porque `app.ts` serve vue e svelte e não distingue.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Crud\Tests\Unit;

use Crud\CrudServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

class InstallPaletteCommandTest extends TestCase
{
    private string $base;

    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/crud-palette-' . uniqid();
        (new Filesystem())->makeDirectory($this->base . '/resources/js/pages/settings', 0755, true);
        (new Filesystem())->makeDirectory($this->base . '/resources/css', 0755, true);
        // A deteccao de stack e pela pagina de aparencia: sem ela, todo teste desta classe
        // cairia no erro de "nao identifiquei a stack". Os casos de vue e svelte apagam
        // este arquivo e criam o equivalente deles.
        (new Filesystem())->put(
            $this->base . '/resources/js/pages/settings/appearance.tsx',
            "import AppearanceTabs from '@/components/appearance-tabs';\n<AppearanceTabs />"
        );
        $this->app->setBasePath($this->base);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->base);

        parent::tearDown();
    }

    public function test_escreve_os_arquivos_compartilhados_e_o_seletor_react(): void
    {
        $this->artisan('crud:install-palette')->assertExitCode(0);

        $files = new Filesystem();

        $this->assertTrue($files->exists($this->base . '/resources/css/crud-palettes.css'));
        $this->assertTrue($files->exists($this->base . '/resources/js/lib/crud-palette.ts'));
        $this->assertTrue($files->exists($this->base . '/resources/js/components/crud-palette-selector.tsx'));
    }

    public function test_projeto_vue_recebe_o_seletor_vue(): void
    {
        $files = new Filesystem();
        $files->delete($this->base . '/resources/js/pages/settings/appearance.tsx');
        $files->put($this->base . '/resources/js/pages/settings/Appearance.vue', '<template><AppearanceTabs /></template>');

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $this->assertTrue($files->exists($this->base . '/resources/js/components/CrudPaletteSelector.vue'));
        $this->assertFalse($files->exists($this->base . '/resources/js/components/crud-palette-selector.tsx'));
    }

    public function test_projeto_svelte_recebe_o_seletor_svelte(): void
    {
        $files = new Filesystem();
        $files->delete($this->base . '/resources/js/pages/settings/appearance.tsx');
        $files->put($this->base . '/resources/js/pages/settings/Appearance.svelte', '<div><AppearanceTabs /></div>');

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $this->assertTrue($files->exists($this->base . '/resources/js/components/CrudPaletteSelector.svelte'));
        $this->assertFalse($files->exists($this->base . '/resources/js/components/crud-palette-selector.tsx'));
    }

    public function test_o_css_traz_as_quatro_paletas_em_claro_e_escuro(): void
    {
        $this->artisan('crud:install-palette')->assertExitCode(0);

        $css = (new Filesystem())->get($this->base . '/resources/css/crud-palettes.css');

        foreach (['azul', 'verde', 'roxo', 'vermelho'] as $palette) {
            $this->assertStringContainsString(":root[data-crud-palette='{$palette}']", $css);
            $this->assertStringContainsString(":root.dark[data-crud-palette='{$palette}']", $css);
        }

        $this->assertStringNotContainsString("data-crud-palette='default'", $css);
    }

    public function test_nao_sobrescreve_sem_force(): void
    {
        $files = new Filesystem();
        $files->makeDirectory($this->base . '/resources/js/lib', 0755, true);
        $files->put($this->base . '/resources/js/lib/crud-palette.ts', 'MEU CONTEUDO');

        $this->artisan('crud:install-palette')
            ->expectsConfirmation('O arquivo resources/js/lib/crud-palette.ts já existe. Sobrescrever?', 'no')
            ->assertExitCode(0);

        $this->assertSame('MEU CONTEUDO', $files->get($this->base . '/resources/js/lib/crud-palette.ts'));
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `vendor/bin/phpunit --filter InstallPaletteCommandTest`
Expected: FAIL com `The command "crud:install-palette" does not exist`.

- [ ] **Step 3: Implementar o comando**

```php
<?php

namespace Crud\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class InstallPaletteCommand extends Command
{
    protected $signature = 'crud:install-palette
                            {--stack= : react, vue ou svelte (padrão: detectar pelo projeto)}
                            {--force : Sobrescreve arquivos existentes sem perguntar}';

    protected $description = 'Instala a camada de paleta de cores sobre o starter kit';

    /**
     * Stub => caminho em `resources/`, para o que é igual nas três stacks.
     *
     * @var array<string, string>
     */
    private const SHARED = [
        'crud-palettes.css.stub' => 'css/crud-palettes.css',
        'crud-palette.ts.stub' => 'js/lib/crud-palette.ts',
    ];

    /**
     * O que cada stack tem de próprio.
     *
     * A detecção é pela página de aparência, não pelo arquivo de entrada: `app.ts` serve
     * vue e svelte, então ele não distingue as duas. As âncoras não entram aqui porque são
     * as mesmas nos três kits — `<AppearanceTabs />` e `initializeTheme();`.
     *
     * @var array<string, array{page: string, entry: string, stub: string, target: string, markers: array{0: string, 1: string}, import: string}>
     */
    private const STACKS = [
        'react' => [
            'page' => 'js/pages/settings/appearance.tsx',
            'entry' => 'js/app.tsx',
            'stub' => 'crud-palette-selector.tsx.stub',
            'target' => 'js/components/crud-palette-selector.tsx',
            'markers' => ['{/* crud:palette:start */}', '{/* crud:palette:end */}'],
            'import' => "import { CrudPaletteSelector } from '@/components/crud-palette-selector';",
        ],
        'vue' => [
            'page' => 'js/pages/settings/Appearance.vue',
            'entry' => 'js/app.ts',
            'stub' => 'CrudPaletteSelector.vue.stub',
            'target' => 'js/components/CrudPaletteSelector.vue',
            'markers' => ['<!-- crud:palette:start -->', '<!-- crud:palette:end -->'],
            'import' => "import CrudPaletteSelector from '@/components/CrudPaletteSelector.vue';",
        ],
        'svelte' => [
            'page' => 'js/pages/settings/Appearance.svelte',
            'entry' => 'js/app.ts',
            'stub' => 'CrudPaletteSelector.svelte.stub',
            'target' => 'js/components/CrudPaletteSelector.svelte',
            'markers' => ['<!-- crud:palette:start -->', '<!-- crud:palette:end -->'],
            'import' => "import CrudPaletteSelector from '@/components/CrudPaletteSelector.svelte';",
        ],
    ];

    /** A stack desta execução, resolvida no `handle()`. */
    private string $stack = 'react';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $stack = $this->resolveStack();

        if ($stack === null) {
            return self::FAILURE;
        }

        $this->stack = $stack;

        info("🎨 Instalando a camada de paleta ({$this->stack})...");

        foreach (self::SHARED as $stub => $destino) {
            $this->writeStub($stub, $destino);
        }

        $this->writeStub(self::STACKS[$this->stack]['stub'], self::STACKS[$this->stack]['target']);

        info('✅ Paleta instalada.');

        return self::SUCCESS;
    }

    /**
     * Qual stack instalar: `--stack` manda, senão vale o que o projeto revela.
     *
     * Pedir uma stack que não bate com o projeto é o caso que merece pergunta: o usuário
     * pode estar fazendo isso de propósito, mas na maioria das vezes é engano, e o
     * resultado seria um seletor que a build dele não compila.
     *
     * Devolve null quando não há como seguir — quem chama devolve FAILURE.
     */
    private function resolveStack(): ?string
    {
        $pedida = $this->option('stack');
        $detectada = $this->detectStack();

        if ($pedida !== null && !array_key_exists($pedida, self::STACKS)) {
            $this->components->error(
                "Stack `{$pedida}` inválida. Opções: " . implode(', ', array_keys(self::STACKS))
            );

            return null;
        }

        if ($pedida === null && $detectada === null) {
            $this->components->error(
                'Não identifiquei a stack deste projeto: não achei a página de aparência de '
                    . 'nenhuma das stacks suportadas (react, vue, svelte). Se for uma delas, '
                    . 'passe --stack=. O livewire ainda não tem paleta.'
            );

            return null;
        }

        if ($pedida !== null && $detectada !== null && $pedida !== $detectada) {
            if (!confirm(
                "Você pediu `{$pedida}`, mas este projeto parece `{$detectada}`. Instalar `{$pedida}` assim mesmo?",
                default: false
            )) {
                info('Instalação cancelada.');

                return null;
            }
        }

        return $pedida ?? $detectada;
    }

    /**
     * A stack que o projeto revela, ou null se não for nenhuma das três.
     */
    private function detectStack(): ?string
    {
        foreach (self::STACKS as $stack => $config) {
            if ($this->files->exists(resource_path($config['page']))) {
                return $stack;
            }
        }

        return null;
    }

    private function writeStub(string $stub, string $destino): void
    {
        $caminho = resource_path($destino);

        if ($this->files->exists($caminho) && !$this->option('force')) {
            if (!confirm("O arquivo resources/{$destino} já existe. Sobrescrever?", default: false)) {
                $this->components->warn("Mantido: resources/{$destino}");

                return;
            }
        }

        $this->files->ensureDirectoryExists(dirname($caminho));
        $this->files->put($caminho, $this->files->get(__DIR__ . '/../stubs/palette/' . $stub));

        $this->components->info("Criado: resources/{$destino}");
    }
}
```

- [ ] **Step 4: Registrar no ServiceProvider**

Em `src/CrudServiceProvider.php`, acrescentar o `use` e a entrada no array de `commands()`:

```php
use Crud\Console\InstallPaletteCommand;
```

```php
            $this->commands([
                InstallCommand::class,
                CreateThemeCommand::class,
                InstallThemeSystemCommand::class,
                InstallPaletteCommand::class,
                InstallOnlyServicesCommand::class,
            ]);
```

- [ ] **Step 5: Escrever os testes da resolução de stack**

```php
    public function test_stack_invalida_falha(): void
    {
        $this->artisan('crud:install-palette --stack=angular')->assertExitCode(1);

        $this->assertFalse((new Filesystem())->exists($this->base . '/resources/js/lib/crud-palette.ts'));
    }

    public function test_projeto_que_nao_e_nenhuma_das_tres_falha_dizendo_o_que_fazer(): void
    {
        (new Filesystem())->delete($this->base . '/resources/js/pages/settings/appearance.tsx');

        $this->artisan('crud:install-palette')
            ->expectsOutputToContain('Não identifiquei a stack deste projeto')
            ->assertExitCode(1);
    }

    public function test_stack_pedida_diferente_da_detectada_pergunta_antes(): void
    {
        // O projeto é react (a página do setUp), mas o usuário pediu vue.
        $this->artisan('crud:install-palette --stack=vue')
            ->expectsConfirmation(
                'Você pediu `vue`, mas este projeto parece `react`. Instalar `vue` assim mesmo?',
                'no'
            )
            ->assertExitCode(1);

        $this->assertFalse((new Filesystem())->exists($this->base . '/resources/js/lib/crud-palette.ts'));
    }

    public function test_stack_pedida_diferente_e_confirmada_instala_a_pedida(): void
    {
        $this->artisan('crud:install-palette --stack=vue')
            ->expectsConfirmation(
                'Você pediu `vue`, mas este projeto parece `react`. Instalar `vue` assim mesmo?',
                'yes'
            )
            ->assertExitCode(0);

        $this->assertTrue((new Filesystem())->exists($this->base . '/resources/js/components/CrudPaletteSelector.vue'));
    }

    public function test_stack_detectada_nao_pergunta_nada(): void
    {
        $this->artisan('crud:install-palette')
            ->doesntExpectOutputToContain('assim mesmo?')
            ->assertExitCode(0);
    }
```

- [ ] **Step 6: Implementar a resolução de stack**

Já está escrita no Step 3: os métodos `resolveStack()` e `detectStack()`, mais a opção
`--stack` na `$signature`. Este passo é conferir que os cinco casos do teste batem com o
que aquele código faz, em especial o texto exato das mensagens — `expectsConfirmation` e
`expectsOutputToContain` casam string literal.

- [ ] **Step 7: Rodar até passar**

Run: `vendor/bin/phpunit --filter InstallPaletteCommandTest`
Expected: PASS, 10 testes.

Atenção ao escrever os testes desta tarefa e das seguintes: a stack é detectada pela página
de aparência, então todo teste que espera instalação bem-sucedida precisa da página no
projeto temporário — é o que o `setUp()` cria. Os casos de vue e svelte apagam a página
react e criam a deles.

- [ ] **Step 8: Commit**

```bash
git add src/Console/InstallPaletteCommand.php src/CrudServiceProvider.php tests/Unit/InstallPaletteCommandTest.php
git commit -m "Install the palette files into the user's project"
```

---

### Task 4: As três edições idempotentes

**Files:**
- Modify: `src/Console/InstallPaletteCommand.php`, `tests/Unit/InstallPaletteCommandTest.php`
- Modify: `src/config/crud.php` (chave `palette.settings_page`)

**Interfaces:**
- Consumes: `MarkedRegion` (Task 1), o comando da Task 3.
- Produces: métodos privados `editAppCss()`, `editAppTsx()`, `editAppearancePage()`, cada um devolvendo `bool` (escreveu ou não).

- [ ] **Step 1: Escrever os testes que falham**

Acrescentar à classe `InstallPaletteCommandTest`:

```php
    private function putAppCss(): void
    {
        (new Filesystem())->put($this->base . '/resources/css/app.css', <<<'CSS'
        @import 'tailwindcss';

        @import 'tw-animate-css';

        @custom-variant dark (&:is(.dark *));
        CSS);
    }

    private function putAppTsx(): void
    {
        (new Filesystem())->put($this->base . '/resources/js/app.tsx', <<<'TSX'
        import { createInertiaApp } from '@inertiajs/react';
        import { initializeTheme } from '@/hooks/use-appearance';
        import AppLayout from '@/layouts/app-layout';

        createInertiaApp({});

        // This will set light / dark mode on load...
        initializeTheme();
        TSX);
    }

    private function putAppearancePage(): void
    {
        (new Filesystem())->put($this->base . '/resources/js/pages/settings/appearance.tsx', <<<'TSX'
        import { Head } from '@inertiajs/react';
        import AppearanceTabs from '@/components/appearance-tabs';
        import Heading from '@/components/heading';

        export default function Appearance() {
            return (
                <div className="space-y-6">
                    <AppearanceTabs />
                </div>
            );
        }
        TSX);
    }

    public function test_acrescenta_o_import_do_css_depois_do_ultimo_import(): void
    {
        $this->putAppCss();

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $css = (new Filesystem())->get($this->base . '/resources/css/app.css');

        $this->assertStringContainsString(
            "@import 'tw-animate-css';\n@import './crud-palettes.css';",
            $css
        );
    }

    public function test_acrescenta_a_chamada_de_inicializacao_no_app_tsx(): void
    {
        $this->putAppTsx();

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $tsx = (new Filesystem())->get($this->base . '/resources/js/app.tsx');

        $this->assertStringContainsString(
            "import AppLayout from '@/layouts/app-layout';\nimport { initializeCrudPalette } from '@/lib/crud-palette';",
            $tsx
        );
        $this->assertStringContainsString("initializeTheme();\ninitializeCrudPalette();", $tsx);
    }

    public function test_insere_o_seletor_na_pagina_de_aparencia(): void
    {
        $this->putAppearancePage();

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $page = (new Filesystem())->get($this->base . '/resources/js/pages/settings/appearance.tsx');

        $this->assertStringContainsString('{/* crud:palette:start */}', $page);
        $this->assertStringContainsString('<CrudPaletteSelector />', $page);
        $this->assertStringContainsString(
            "import { CrudPaletteSelector } from '@/components/crud-palette-selector';",
            $page
        );
    }

    public function test_rodar_duas_vezes_nao_muda_nada(): void
    {
        $this->putAppCss();
        $this->putAppTsx();
        $this->putAppearancePage();

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $files = new Filesystem();
        $primeira = [
            $files->get($this->base . '/resources/css/app.css'),
            $files->get($this->base . '/resources/js/app.tsx'),
            $files->get($this->base . '/resources/js/pages/settings/appearance.tsx'),
        ];

        $this->artisan('crud:install-palette --force')->assertExitCode(0);

        $this->assertSame($primeira[0], $files->get($this->base . '/resources/css/app.css'));
        $this->assertSame($primeira[1], $files->get($this->base . '/resources/js/app.tsx'));
        $this->assertSame($primeira[2], $files->get($this->base . '/resources/js/pages/settings/appearance.tsx'));
    }

    public function test_sem_ancora_nao_escreve_e_avisa(): void
    {
        (new Filesystem())->put($this->base . '/resources/js/pages/settings/appearance.tsx', '<div>outra coisa</div>');

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $page = (new Filesystem())->get($this->base . '/resources/js/pages/settings/appearance.tsx');

        $this->assertSame('<div>outra coisa</div>', $page);
    }

    public function test_config_desliga_a_edicao_da_pagina(): void
    {
        $this->putAppearancePage();
        config()->set('crud.palette.settings_page', false);

        $this->artisan('crud:install-palette')->assertExitCode(0);

        $page = (new Filesystem())->get($this->base . '/resources/js/pages/settings/appearance.tsx');

        $this->assertStringNotContainsString('crud:palette:start', $page);
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `vendor/bin/phpunit --filter InstallPaletteCommandTest`
Expected: FAIL nos seis testes novos — nenhuma edição acontece ainda.

- [ ] **Step 3: Acrescentar a chave de config**

Em `src/config/crud.php`, dentro do array de retorno:

```php
    /*
    |--------------------------------------------------------------------------
    | Paleta de cores
    |--------------------------------------------------------------------------
    |
    | `settings_page` controla se o `crud:install-palette` insere o seletor na sua
    | página de aparência. Desligue se preferir posicionar o componente à mão.
    |
    */
    'palette' => [
        'settings_page' => true,
    ],
```

- [ ] **Step 4: Implementar as três edições**

Em `src/Console/InstallPaletteCommand.php`, acrescentar ao topo:

```php
use Crud\MarkedRegion;
```

E ao `handle()`, antes do `info('✅ Paleta instalada.')`:

```php
        $this->editAppCss();
        $this->editAppTsx();

        if (config('crud.palette.settings_page', true)) {
            $this->editAppearancePage();
        }
```

E os três métodos:

```php
    /**
     * Carrega as paletas pelo `app.css`, depois do último `@import` do topo.
     *
     * `@import` em CSS só vale antes das outras regras, por isso a âncora é o último
     * import e não o fim do arquivo.
     */
    private function editAppCss(): void
    {
        $caminho = resource_path('css/app.css');
        $linha = "@import './crud-palettes.css';";

        if (!$this->files->exists($caminho)) {
            $this->naoEditou('resources/css/app.css', $linha);

            return;
        }

        $conteudo = $this->files->get($caminho);

        if (str_contains($conteudo, $linha)) {
            return;
        }

        $linhas = explode("\n", $conteudo);
        $ultimo = null;

        foreach ($linhas as $numero => $atual) {
            if (str_starts_with(trim($atual), '@import ')) {
                $ultimo = $numero;
            }
        }

        if ($ultimo === null) {
            $this->naoEditou('resources/css/app.css', $linha);

            return;
        }

        array_splice($linhas, $ultimo + 1, 0, [$linha]);

        $this->files->put($caminho, implode("\n", $linhas));
        $this->components->info('Atualizado: resources/css/app.css');
    }

    /**
     * Aplica a paleta antes da primeira pintura, no mesmo ponto onde o starter kit já
     * chama `initializeTheme()`.
     */
    private function editAppTsx(): void
    {
        $caminho = resource_path(self::STACKS[$this->stack]['entry']);
        $import = "import { initializeCrudPalette } from '@/lib/crud-palette';";
        $chamada = 'initializeCrudPalette();';
        // O import do módulo de ids é igual nos três: é TypeScript puro dos dois lados.

        if (!$this->files->exists($caminho)) {
            $this->naoEditou('resources/' . self::STACKS[$this->stack]['entry'], $import . "\n" . $chamada);

            return;
        }

        $conteudo = $this->files->get($caminho);

        if (str_contains($conteudo, $chamada)) {
            return;
        }

        if (!str_contains($conteudo, 'initializeTheme();')) {
            $this->naoEditou('resources/' . self::STACKS[$this->stack]['entry'], $import . "\n" . $chamada);

            return;
        }

        $comImport = MarkedRegion::insertImport($conteudo, $import, '@/lib/crud-palette');

        if ($comImport === null) {
            $this->naoEditou('resources/' . self::STACKS[$this->stack]['entry'], $import . "\n" . $chamada);

            return;
        }

        $this->files->put(
            $caminho,
            str_replace('initializeTheme();', "initializeTheme();\n" . $chamada, $comImport)
        );

        $this->components->info('Atualizado: resources/' . self::STACKS[$this->stack]['entry']);
    }

    /**
     * Põe o seletor junto do claro/escuro, dentro de uma região que o pacote gerencia.
     */
    private function editAppearancePage(): void
    {
        $config = self::STACKS[$this->stack];
        $caminho = resource_path($config['page']);
        $import = $config['import'];
        // O elemento é o mesmo nas três stacks; o que muda é a sintaxe do comentário que
        // delimita a região — `{/* */}` em TSX, `<!-- -->` em Vue e Svelte.
        $bloco = '<CrudPaletteSelector />';
        $region = new MarkedRegion($config['markers'][0], $config['markers'][1]);

        if (!$this->files->exists($caminho)) {
            $this->naoEditou('resources/' . $config['page'], $import . "\n" . $bloco);

            return;
        }

        $conteudo = $this->files->get($caminho);

        $novo = $region->exists($conteudo)
            ? $region->replace($conteudo, $bloco)
            : $region->install($conteudo, '/<AppearanceTabs\s*\/>/', $bloco);

        if ($novo === null) {
            $this->naoEditou('resources/' . $config['page'], $import . "\n" . $bloco);

            return;
        }

        $comImport = MarkedRegion::insertImport($novo, $import, '@/components/crud-palette-selector');

        if ($comImport === null) {
            $this->naoEditou('resources/' . $config['page'], $import . "\n" . $bloco);

            return;
        }

        $this->files->put($caminho, $comImport);
        $this->components->info('Atualizado: resources/' . $config['page']);
    }

    /**
     * Não conseguimos escrever com segurança: o usuário recebe o trecho e decide.
     */
    private function naoEditou(string $arquivo, string $trecho): void
    {
        $this->components->warn("Não consegui editar {$arquivo}. Acrescente à mão:");
        $this->line('');
        $this->line($trecho);
        $this->line('');
    }
```

- [ ] **Step 5: Rodar até passar**

Run: `vendor/bin/phpunit --filter InstallPaletteCommandTest`
Expected: PASS, 9 testes.

- [ ] **Step 6: Análise estática e commit**

```bash
vendor/bin/phpstan analyse --no-progress
git add src/Console/InstallPaletteCommand.php src/config/crud.php tests/Unit/InstallPaletteCommandTest.php
git commit -m "Wire the palette into app.css, the entry file and the appearance page"
```

---

### Task 5: Detecção e limpeza do sistema antigo

**Files:**
- Modify: `src/Console/InstallPaletteCommand.php`, `tests/Unit/InstallPaletteCommandTest.php`

**Interfaces:**
- Consumes: o comando das tarefas 3 e 4.
- Produces: `private function handleLegacy(): void`, chamado no fim do `handle()`.

- [ ] **Step 1: Escrever os testes que falham**

```php
    private function putLegacy(): void
    {
        $files = new Filesystem();
        $files->makeDirectory($this->base . '/resources/js/lib', 0755, true);
        $files->makeDirectory($this->base . '/resources/js/hooks', 0755, true);
        $files->makeDirectory($this->base . '/resources/js/components', 0755, true);
        $files->put($this->base . '/resources/js/lib/themes.ts', 'export const themes = [];');
        $files->put($this->base . '/resources/js/components/theme-selector.tsx', 'antigo');
        $files->put($this->base . '/resources/js/hooks/use-appearance.tsx', 'do starter kit, sobrescrito');
    }

    public function test_oferece_apagar_so_os_arquivos_do_pacote(): void
    {
        $this->putLegacy();

        $this->artisan('crud:install-palette')
            ->expectsConfirmation('Apagar os arquivos que a versão antiga instalou?', 'yes')
            ->assertExitCode(0);

        $files = new Filesystem();

        $this->assertFalse($files->exists($this->base . '/resources/js/lib/themes.ts'));
        $this->assertFalse($files->exists($this->base . '/resources/js/components/theme-selector.tsx'));
    }

    public function test_nunca_toca_no_que_e_do_starter_kit(): void
    {
        $this->putLegacy();

        $this->artisan('crud:install-palette')
            ->expectsConfirmation('Apagar os arquivos que a versão antiga instalou?', 'yes')
            ->assertExitCode(0);

        $files = new Filesystem();

        $this->assertTrue($files->exists($this->base . '/resources/js/hooks/use-appearance.tsx'));
        $this->assertSame(
            'do starter kit, sobrescrito',
            $files->get($this->base . '/resources/js/hooks/use-appearance.tsx')
        );
    }

    public function test_recusar_a_limpeza_mantem_tudo(): void
    {
        $this->putLegacy();

        $this->artisan('crud:install-palette')
            ->expectsConfirmation('Apagar os arquivos que a versão antiga instalou?', 'no')
            ->assertExitCode(0);

        $this->assertTrue((new Filesystem())->exists($this->base . '/resources/js/lib/themes.ts'));
    }

    public function test_projeto_sem_legado_nao_avisa_nada(): void
    {
        $this->artisan('crud:install-palette')
            ->doesntExpectOutputToContain('Encontrei o sistema de temas antigo neste projeto.')
            ->assertExitCode(0);
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `vendor/bin/phpunit --filter InstallPaletteCommandTest`
Expected: FAIL — a confirmação esperada nunca aparece.

- [ ] **Step 3: Implementar**

Acrescentar ao fim do `handle()`, antes do `info('✅ Paleta instalada.')`:

```php
        $this->handleLegacy();
```

E os membros:

```php
    /**
     * Arquivos que a versão antiga instalou e que são só dela.
     *
     * @var array<int, string>
     */
    private const LEGACY_OURS = [
        'js/lib/themes.ts',
        'js/components/theme-selector.tsx',
        'js/components/appearance-dropdown.tsx',
        'js/components/appearance-theme-selector.tsx',
        'js/components/theme-demo.tsx',
        'js/pages/ThemeExample.tsx',
    ];

    /**
     * Arquivos do starter kit que a versão antiga sobrescreveu.
     *
     * O pacote não apaga nem tenta restaurar: só o git do usuário sabe o que estava lá.
     *
     * @var array<int, string>
     */
    private const LEGACY_THEIRS = [
        'js/hooks/use-appearance.tsx',
        'js/components/appearance-tabs.tsx',
    ];

    private function handleLegacy(): void
    {
        if (!$this->files->exists(resource_path('js/lib/themes.ts'))) {
            return;
        }

        $this->components->warn('Encontrei o sistema de temas antigo neste projeto.');

        $sobrescritos = array_values(array_filter(
            self::LEGACY_THEIRS,
            fn (string $arquivo): bool => $this->files->exists(resource_path($arquivo))
        ));

        if ($sobrescritos !== []) {
            $this->components->warn(
                'Estes arquivos são do seu starter kit e a versão antiga os substituiu. '
                    . 'Recupere-os do git:'
            );

            foreach ($sobrescritos as $arquivo) {
                $this->line("  git checkout -- resources/{$arquivo}");
            }

            $this->line('');
        }

        $nossos = array_values(array_filter(
            self::LEGACY_OURS,
            fn (string $arquivo): bool => $this->files->exists(resource_path($arquivo))
        ));

        if ($nossos === []) {
            return;
        }

        $this->components->info('Instalados pela versão antiga do pacote:');

        foreach ($nossos as $arquivo) {
            $this->line("  resources/{$arquivo}");
        }

        if (!confirm('Apagar os arquivos que a versão antiga instalou?', default: false)) {
            return;
        }

        foreach ($nossos as $arquivo) {
            $this->files->delete(resource_path($arquivo));
        }

        $this->components->info('Removidos. Confira também `@radix-ui/react-tabs` no package.json: '
            . 'a versão antiga acrescentou essa dependência.');
    }
```

- [ ] **Step 4: Rodar até passar**

Run: `vendor/bin/phpunit --filter InstallPaletteCommandTest`
Expected: PASS, 13 testes.

- [ ] **Step 5: Commit**

```bash
git add src/Console/InstallPaletteCommand.php tests/Unit/InstallPaletteCommandTest.php
git commit -m "Detect the old theme system and offer to clean up what was ours"
```

---

### Task 6: `crud:create-palette`

**Files:**
- Create: `src/Console/CreatePaletteCommand.php`, `tests/Unit/CreatePaletteCommandTest.php`
- Modify: `src/CrudServiceProvider.php`

**Interfaces:**
- Consumes: os arquivos escritos pela Task 3.
- Produces:
  ```php
  protected $signature = 'crud:create-palette {name? : Nome da paleta} {--hue= : Matiz OKLCH, 0 a 360}';
  ```
  Acrescenta o par de blocos ao `resources/css/crud-palettes.css` e a entrada em `palettes` do `resources/js/lib/crud-palette.ts`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Crud\Tests\Unit;

use Crud\CrudServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

class CreatePaletteCommandTest extends TestCase
{
    private string $base;

    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/crud-create-palette-' . uniqid();
        (new Filesystem())->makeDirectory($this->base . '/resources/css', 0755, true);
        // A stack e detectada pela pagina de aparencia; sem ela o install falha antes de escrever.
        (new Filesystem())->makeDirectory($this->base . '/resources/js/pages/settings', 0755, true);
        (new Filesystem())->put($this->base . '/resources/js/pages/settings/appearance.tsx', '<AppearanceTabs />');
        $this->app->setBasePath($this->base);
        $this->artisan('crud:install-palette')->run();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->base);

        parent::tearDown();
    }

    public function test_acrescenta_os_dois_blocos_no_css(): void
    {
        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(0);

        $css = (new Filesystem())->get($this->base . '/resources/css/crud-palettes.css');

        $this->assertStringContainsString(":root[data-crud-palette='laranja']", $css);
        $this->assertStringContainsString(":root.dark[data-crud-palette='laranja']", $css);
        $this->assertStringContainsString('oklch(0.55 0.19 70)', $css);
    }

    public function test_acrescenta_a_entrada_na_lista(): void
    {
        $this->artisan('crud:create-palette Laranja --hue=70')->assertExitCode(0);

        $ts = (new Filesystem())->get($this->base . '/resources/js/lib/crud-palette.ts');

        $this->assertStringContainsString("{ id: 'laranja', name: 'Laranja' },", $ts);
    }

    public function test_id_repetido_falha_sem_escrever(): void
    {
        $this->artisan('crud:create-palette Azul --hue=250')->assertExitCode(1);

        $css = (new Filesystem())->get($this->base . '/resources/css/crud-palettes.css');

        $this->assertSame(2, substr_count($css, "data-crud-palette='azul'"));
    }

    public function test_matiz_fora_da_faixa_falha(): void
    {
        $this->artisan('crud:create-palette Laranja --hue=400')->assertExitCode(1);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `vendor/bin/phpunit --filter CreatePaletteCommandTest`
Expected: FAIL com `The command "crud:create-palette" does not exist`.

- [ ] **Step 3: Implementar**

```php
<?php

namespace Crud\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

class CreatePaletteCommand extends Command
{
    protected $signature = 'crud:create-palette
                            {name? : Nome da paleta}
                            {--hue= : Matiz OKLCH, 0 a 360}';

    protected $description = 'Acrescenta uma paleta de cores ao projeto';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $cssPath = resource_path('css/crud-palettes.css');
        $tsPath = resource_path('js/lib/crud-palette.ts');

        if (!$this->files->exists($cssPath) || !$this->files->exists($tsPath)) {
            $this->components->error('Rode `php artisan crud:install-palette` antes.');

            return self::FAILURE;
        }

        $nome = $this->argument('name') ?? text('Nome da paleta?', required: true);
        $id = Str::slug($nome);

        $hue = $this->option('hue') ?? text('Matiz OKLCH (0 a 360)?', default: '250');

        if (!is_numeric($hue) || $hue < 0 || $hue > 360) {
            $this->components->error("Matiz `{$hue}` inválido. Use um número de 0 a 360.");

            return self::FAILURE;
        }

        $css = $this->files->get($cssPath);

        if (str_contains($css, "data-crud-palette='{$id}'")) {
            $this->components->error("Já existe uma paleta `{$id}`.");

            return self::FAILURE;
        }

        $this->files->put($cssPath, $css . "\n" . $this->blocks($id, (float) $hue));

        $ts = $this->files->get($tsPath);
        $entrada = "    { id: '{$id}', name: '{$nome}' },";

        $this->files->put($tsPath, str_replace(
            "];\n\nexport function getPalette",
            $entrada . "\n];\n\nexport function getPalette",
            $ts
        ));

        $this->components->info("Paleta `{$id}` criada. Rode `npm run build` para vê-la.");

        return self::SUCCESS;
    }

    /**
     * Os dois blocos da paleta, nas mesmas nove variáveis das que vêm no pacote.
     */
    private function blocks(string $id, float $hue): string
    {
        $claro = sprintf('oklch(0.55 0.19 %s)', $hue);
        $escuro = sprintf('oklch(0.7 0.16 %s)', $hue);
        $contraste = sprintf('oklch(0.21 0.03 %s)', $hue);

        return <<<CSS
        :root[data-crud-palette='{$id}'] {
            --primary: {$claro};
            --primary-foreground: oklch(0.985 0 0);
            --ring: {$claro};
            --sidebar-primary: {$claro};
            --sidebar-primary-foreground: oklch(0.985 0 0);
            --sidebar-ring: {$claro};
            --chart-1: {$claro};
            --chart-2: oklch(0.65 0.15 {$hue});
            --chart-3: oklch(0.75 0.11 {$hue});
        }

        :root.dark[data-crud-palette='{$id}'] {
            --primary: {$escuro};
            --primary-foreground: {$contraste};
            --ring: {$escuro};
            --sidebar-primary: {$escuro};
            --sidebar-primary-foreground: {$contraste};
            --sidebar-ring: {$escuro};
            --chart-1: {$escuro};
            --chart-2: oklch(0.62 0.14 {$hue});
            --chart-3: oklch(0.54 0.12 {$hue});
        }

        CSS;
    }
}
```

- [ ] **Step 4: Registrar no ServiceProvider**

```php
use Crud\Console\CreatePaletteCommand;
```

E acrescentar `CreatePaletteCommand::class,` ao array de `commands()`.

- [ ] **Step 5: Rodar até passar**

Run: `vendor/bin/phpunit --filter CreatePaletteCommandTest`
Expected: PASS, 4 testes.

- [ ] **Step 6: Commit**

```bash
git add src/Console/CreatePaletteCommand.php src/CrudServiceProvider.php tests/Unit/CreatePaletteCommandTest.php
git commit -m "Add crud:create-palette writing both the CSS and the id list"
```

---

### Task 7: Tirar o tema da geração

**Files:**
- Modify: `src/Console/InstallCommand.php` (linhas 28-35 da signature, 326-336 do prompt, 445-446 dos replacements, 712-731 dos dois métodos)
- Modify: `src/stubs/react/Index.stub`, `Create.stub`, `Edit.stub`, `Show.stub` (a linha `{{themeImports}}`)
- Modify: `tests/Unit/InstallCommandStackTest.php`, `InstallCommandPreflightTest.php`, `InstallCommandNavigationRouteTest.php`, `InstallCommandSidebarNavigationTest.php`, `GeneratedBreadcrumbTest.php`, `InstallCommandRouteImportsTest.php`, `GeneratedLintContractTest.php` (a `$signature` dos dublês)

**Interfaces:**
- Consumes: nada.
- Produces: `getic:install` sem a flag `--theme`, sem o prompt e sem os dois placeholders.

- [ ] **Step 1: Escrever o teste que falha**

Em `tests/Unit/InstallCommandStackTest.php`, acrescentar:

```php
    public function test_a_flag_de_tema_nao_existe_mais(): void
    {
        $command = new InstallCommand(new Filesystem());

        $this->assertFalse($command->getDefinition()->hasOption('theme'));
    }
```

E em `tests/Unit/GeneratedLintContractTest.php`:

```php
    public function test_nenhum_stub_react_carrega_placeholder_de_tema(): void
    {
        foreach (glob(__DIR__ . '/../../src/stubs/react/*.stub') as $stub) {
            $conteudo = file_get_contents($stub);

            $this->assertStringNotContainsString('{{themeImports}}', $conteudo, basename($stub));
            $this->assertStringNotContainsString('{{themeComponents}}', $conteudo, basename($stub));
        }
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `vendor/bin/phpunit --filter "test_a_flag_de_tema_nao_existe_mais|test_nenhum_stub_react_carrega_placeholder_de_tema"`
Expected: FAIL nos dois — a flag existe e os stubs têm `{{themeImports}}`.

- [ ] **Step 3: Tirar da signature e dos dublês**

Em `src/Console/InstallCommand.php`, remover a última linha da `$signature`:

```php
                                            {--theme : Include theme-aware components}';
```

deixando a linha anterior terminando com `';`. Fazer o mesmo nas sete `$signature` dos dublês de teste listados em **Files**.

- [ ] **Step 4: Tirar o prompt**

Em `src/Console/InstallCommand.php`, remover o bloco inteiro:

```php
        // Theme integration
        if ($this->template === 'react' && confirm('Deseja incluir sistema de temas dinâmicos?')) {
            $this->options['theme'] = true;

            if (!app('crud')->isThemeSystemInstalled()) {
                if (confirm('Sistema de temas não detectado. Instalar agora?')) {
                    $this->call('crud:install-theme-system');
                }
            }
        }
```

- [ ] **Step 5: Tirar os replacements e os dois métodos**

Em `buildReactComponents()`, remover as duas linhas:

```php
            '{{themeImports}}' => $this->option('theme') ? $this->getThemeImports() : '',
            '{{themeComponents}}' => $this->option('theme') ? $this->getThemeComponents() : '',
```

E remover os métodos `getThemeImports()` e `getThemeComponents()` inteiros, com os docblocks.

- [ ] **Step 6: Tirar a linha `{{themeImports}}` dos quatro stubs**

Em `Index.stub`, `Create.stub`, `Edit.stub` e `Show.stub`, apagar a linha que contém só `{{themeImports}}`, logo abaixo do bloco de imports. A linha em branco que separa o bloco de imports do código continua.

- [ ] **Step 7: Rodar a suíte inteira**

Run: `vendor/bin/phpunit`
Expected: PASS. Os testes que renderizam stubs continuam verdes porque `{{themeImports}}` deixou de existir nos dois lados.

- [ ] **Step 8: Commit**

```bash
git add src/Console/InstallCommand.php src/stubs/react tests/Unit
git commit -m "Take the theme out of CRUD generation"
```

---

### Task 8: Aposentar o sistema antigo no pacote

**Files:**
- Delete: `src/Console/InstallThemeSystemCommand.php`, `src/Console/CreateThemeCommand.php`, `src/config/themes.php`, e os oito stubs de tema em `src/stubs/react/`
- Modify: `src/CrudServiceProvider.php`, `src/CrudManager.php`, `src/Facades/Crud.php`
- Delete: `tests/Unit/CrudPackageTest.php`
- Modify: `phpunit.xml` (tirar o `<exclude>`)

**Interfaces:**
- Consumes: o comando novo das tarefas 3-6.
- Produces: `CrudManager::isPaletteInstalled(): bool`, que confere `resources/js/lib/crud-palette.ts`.

- [ ] **Step 1: Escrever o teste que falha**

Criar `tests/Unit/CrudManagerTest.php`:

```php
<?php

namespace Crud\Tests\Unit;

use Crud\CrudServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

class CrudManagerTest extends TestCase
{
    private string $base;

    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/crud-manager-' . uniqid();
        (new Filesystem())->makeDirectory($this->base . '/resources/js/lib', 0755, true);
        $this->app->setBasePath($this->base);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->base);

        parent::tearDown();
    }

    public function test_sem_a_paleta_instalada(): void
    {
        $this->assertFalse(app('crud')->isPaletteInstalled());
    }

    public function test_com_a_paleta_instalada(): void
    {
        (new Filesystem())->put($this->base . '/resources/js/lib/crud-palette.ts', 'export const palettes = [];');

        $this->assertTrue(app('crud')->isPaletteInstalled());
    }

    public function test_a_config_global_de_temas_nao_existe_mais(): void
    {
        $this->assertNull(config('themes'));
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `vendor/bin/phpunit --filter CrudManagerTest`
Expected: FAIL com `Call to undefined method Crud\CrudManager::isPaletteInstalled()`.

- [ ] **Step 3: Reescrever o `CrudManager`**

Substituir `getThemes()` e `isThemeSystemInstalled()` por:

```php
    /**
     * A camada de paleta está instalada neste projeto?
     */
    public function isPaletteInstalled(): bool
    {
        return file_exists(resource_path('js/lib/crud-palette.ts'));
    }
```

Remover o `use Illuminate\Support\Collection;` se ficar sem uso. Ajustar o docblock de `src/Facades/Crud.php` para anunciar `isPaletteInstalled()` no lugar de `getThemes()`.

- [ ] **Step 4: Limpar o ServiceProvider**

```php
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/crud.php', 'crud');

        $this->app->singleton('crud', function ($app) {
            return new \Crud\CrudManager($app);
        });
    }
```

No `boot()`: tirar `CreateThemeCommand::class` e `InstallThemeSystemCommand::class` do array (e os `use`), tirar `config/themes.php` do publish `crud-config`, apagar o publish `crud-assets` inteiro — as duas pastas que ele aponta nunca existiram — e trocar a tag `theme-system` por:

```php
            $this->publishes([
                __DIR__ . '/stubs/palette' => resource_path('js/crud-palette'),
            ], 'crud-palette');
```

- [ ] **Step 5: Apagar os arquivos aposentados**

```bash
git rm src/Console/InstallThemeSystemCommand.php src/Console/CreateThemeCommand.php src/config/themes.php
git rm src/stubs/react/themes.ts.stub src/stubs/react/use-appearance.tsx.stub \
       src/stubs/react/theme-selector.tsx.stub src/stubs/react/appearance-dropdown.tsx.stub \
       src/stubs/react/appearance-tabs.tsx.stub src/stubs/react/appearance-theme-selector.tsx.stub \
       src/stubs/react/theme-demo.tsx.stub src/stubs/react/ThemeExample.tsx.stub
git rm tests/Unit/CrudPackageTest.php
```

`CrudPackageTest.php` sai junto: ele testava `getThemes()` e caminhos que não existem mais, e é o arquivo que o `phpunit.xml` excluía. Tirar também o bloco `<exclude>` do `phpunit.xml`, junto do comentário que o explica.

- [ ] **Step 6: Rodar tudo**

Run: `vendor/bin/phpunit && vendor/bin/phpstan analyse --no-progress && composer validate --strict`
Expected: PASS, sem erro de análise, composer válido.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Retire the theme system that fought the starter kit"
```

---

### Task 9: Documentação e verificação manual

**Files:**
- Modify: `README.md`, `CHANGELOG.md`, `CLAUDE.md`

**Interfaces:**
- Consumes: tudo.
- Produces: a 5.0.0 pronta para o dono taguear.

- [ ] **Step 1: CHANGELOG**

Acrescentar no topo, acima de `## [4.0.2]`, uma seção `## [5.0.0] - <data>` com:

- **⚠️ Leia antes de atualizar** — o `crud:install-theme-system` sobrescrevia `hooks/use-appearance.tsx` e `components/appearance-tabs.tsx`, que são do starter kit, e no Laravel 13 isso quebra o `tsc` do projeto (`Property 'resolvedAppearance' does not exist`). Quem instalou deve recuperar os dois do git.
- **Removido** — flag `--theme` e o prompt dela, `{{themeImports}}`, `{{themeComponents}}`, `crud:install-theme-system`, `crud:create-theme`, `config/themes.php` e a chave global `config('themes')`, a tag `theme-system`, a tag `crud-assets` (apontava para duas pastas inexistentes), `CrudManager::getThemes()`, oito stubs.
- **Adicionado** — `crud:install-palette`, `crud:create-palette`, a tag `crud-palette`, `config('crud.palette.settings_page')`, e a explicação de que a paleta mexe em nove variáveis de acento e em nenhuma superfície.
- **Migração** — a tabela de-para dos comandos e a instrução do `git checkout --`.

- [ ] **Step 2: README**

Trocar o título para `# Laravel CRUD Generator v5.0.0`. Substituir a seção "🎨 Sistema de Temas" por uma de paleta que cubra: os dois comandos, os três arquivos instalados, as três edições que o install faz, o `config('crud.palette.settings_page')`, o fato de o claro/escuro continuar sendo do starter kit, e a receita de acrescentar o atributo no `app.blade.php` para quem quiser zero flash:

```blade
<html data-crud-palette="{{ request()->cookie('crud-palette') }}">
```

Tirar `--theme` de todos os exemplos de `getic:install`.

- [ ] **Step 3: CLAUDE.md**

Na tabela de mapa, trocar `InstallThemeSystemCommand.php` e `CreateThemeCommand.php` por `InstallPaletteCommand.php` e `CreatePaletteCommand.php`, e acrescentar `MarkedRegion.php`. Em "Pendências conhecidas", apagar o item do `--theme` descartado e o da tag `crud-assets`. Em "API pública", trocar a tag `theme-system` por `crud-palette`.

- [ ] **Step 4: Verificação manual nos três starter kits**

`projeto-exemplo-react`, `-vue` e `-svelte`. O `-livewire` fica de fora: a paleta dele é
outra spec, porque lá as variáveis são do Flux (`--color-accent`), o seletor é Blade com
Alpine e a persistência é do store do Flux.

Em cada um dos três, dentro de `/home/sp1d3r/Documentos/projetos/pacotes/laravel/`:

```bash
tar -czf /tmp/antes-<stack>.tgz resources package.json
php artisan crud:install-palette
npm run lint:check
npm run types:check     # tsc no react, vue-tsc no vue, svelte-check no svelte
php artisan crud:install-palette --force   # segunda vez
```

Esperado nos três: `lint:check` e o checador de tipos limpos; a segunda execução não altera
arquivo nenhum, e o `diff` contra o backup mostra só os três arquivos novos e as três linhas
acrescentadas. No navegador: trocar de paleta e alternar claro/escuro, confirmando que as
duas dimensões são independentes.

Confirmar também a pergunta de stack cruzada: rodar `php artisan crud:install-palette
--stack=react` dentro do `projeto-exemplo-vue` e verificar que ele pergunta antes, em vez de
escrever um `.tsx` num projeto Vue.

Restaurar os projetos ao fim, ou deixá-los instalados se o dono preferir.

- [ ] **Step 5: Carimbar e commitar**

```bash
git add README.md CHANGELOG.md CLAUDE.md
git commit -m "Stamp 5.0.0"
```

- [ ] **Step 6: Fechar a branch**

```bash
git checkout main
git merge --no-ff palette-layer -m "Merge branch 'palette-layer'"
vendor/bin/phpunit && vendor/bin/phpstan analyse --no-progress
```

Parar aqui. O push e a tag `v5.0.0` são do dono.
