# Link de navegação na sidebar — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ao gerar um CRUD na stack react, inserir um link para ele na sidebar do projeto, dentro de uma região delimitada por comentários que o pacote gerencia sozinho.

**Architecture:** Uma classe sem dependências, `Crud\NavigationRegion`, faz todo o trabalho de texto — achar a região, inserir ou substituir um item, criar a região a partir de uma âncora. Ela recebe string e devolve string, ou `null` quando não há como escrever com segurança. O `InstallCommand` fica só com a orquestração: localizar o arquivo da stack, perguntar, escrever, avisar. Essa separação existe porque a parte de texto é a única do pacote testável sem MySQL.

**Tech Stack:** PHP 8.2+, Laravel 12/13, PHPUnit 12, `laravel/prompts`.

**Spec:** `docs/superpowers/specs/2026-07-29-sidebar-nav-link-design.md`

## Global Constraints

- Mensagens de console e prompts em **português**; mensagens de commit em **inglês**.
- Config lida sempre com default: `config('crud.navigation.sidebar', true)` — `mergeConfigFrom()` faz merge raso.
- Nunca escrever em arquivo do usuário fora da região gerenciada, exceto na instalação inicial, e essa passa por `confirm()`.
- Falha de qualquer tipo (arquivo ausente, âncora não encontrada, marcador malformado) emite `warning()` e **não altera o arquivo**. Nunca aborta a geração do CRUD.
- Nada de `env()` fora de `src/config/`.
- Não tocar em `src/stubs/views/heron/` (stack congelada).
- Placeholders novos, se houver, entram no mapa de `buildReplacements()`.
- Não commitar nada além do que cada tarefa lista. O merge para `main` e a tag são do dono do projeto.

---

## File Structure

| Arquivo | Responsabilidade |
|---|---|
| `src/NavigationRegion.php` (criar) | Manipulação de texto da região. Sem Laravel, sem I/O. |
| `tests/Unit/NavigationRegionTest.php` (criar) | Os 5 casos do spec, PHPUnit puro. |
| `phpunit.xml` (modificar) | Registrar o arquivo de teste novo — a suíte lista arquivo por arquivo. |
| `src/config/crud.php` (modificar) | Bloco `navigation`. |
| `src/Console/InstallCommand.php` (modificar) | Orquestração: localizar, confirmar, escrever, avisar. |

---

### Task 1: NavigationRegion — inserir e substituir dentro de uma região existente

**Files:**
- Create: `src/NavigationRegion.php`
- Create: `tests/Unit/NavigationRegionTest.php`
- Modify: `phpunit.xml`

**Interfaces:**
- Consumes: nada.
- Produces: `Crud\NavigationRegion::__construct(string $startMarker, string $endMarker)` e `upsert(string $content, string $key, string $item): ?string`. A Task 2 acrescenta `install()` à mesma classe; a Task 3 consome as duas.

- [ ] **Step 1: Escrever os testes que falham**

Criar `tests/Unit/NavigationRegionTest.php`:

```php
<?php

namespace Crud\Tests\Unit;

use Crud\NavigationRegion;
use PHPUnit\Framework\TestCase;

class NavigationRegionTest extends TestCase
{
    private function region(): NavigationRegion
    {
        return new NavigationRegion('// crud:nav:start', '// crud:nav:end');
    }

    private function sidebarWithEmptyRegion(): string
    {
        return <<<'TSX'
        const mainNavItems: NavItem[] = [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            // crud:nav:start
            // crud:nav:end
        ];
        TSX;
    }

    public function test_insere_item_em_regiao_vazia(): void
    {
        $result = $this->region()->upsert(
            $this->sidebarWithEmptyRegion(),
            "'/clientes'",
            "{ title: 'Clientes', href: '/clientes', icon: List },"
        );

        $this->assertStringContainsString(
            "    { title: 'Clientes', href: '/clientes', icon: List },",
            $result
        );
        $this->assertStringContainsString('// crud:nav:start', $result);
        $this->assertStringContainsString('// crud:nav:end', $result);
        $this->assertStringContainsString("{ title: 'Dashboard'", $result);
    }

    public function test_substitui_item_com_o_mesmo_href_em_vez_de_duplicar(): void
    {
        $region = $this->region();

        $once = $region->upsert(
            $this->sidebarWithEmptyRegion(),
            "'/clientes'",
            "{ title: 'Clientes', href: '/clientes', icon: List },"
        );

        $twice = $region->upsert(
            $once,
            "'/clientes'",
            "{ title: 'Clientes', href: '/clientes', icon: List },"
        );

        $this->assertSame(1, substr_count($twice, "href: '/clientes'"));
    }

    public function test_acrescenta_segundo_item_sem_apagar_o_primeiro(): void
    {
        $region = $this->region();

        $withFirst = $region->upsert(
            $this->sidebarWithEmptyRegion(),
            "'/clientes'",
            "{ title: 'Clientes', href: '/clientes', icon: List },"
        );

        $withBoth = $region->upsert(
            $withFirst,
            "'/produtos'",
            "{ title: 'Produtos', href: '/produtos', icon: List },"
        );

        $this->assertStringContainsString("href: '/clientes'", $withBoth);
        $this->assertStringContainsString("href: '/produtos'", $withBoth);
    }

    public function test_devolve_null_quando_nao_ha_marcadores(): void
    {
        $content = "const mainNavItems: NavItem[] = [\n];\n";

        $this->assertNull($this->region()->upsert($content, "'/clientes'", 'item'));
    }

    public function test_devolve_null_quando_falta_o_marcador_final(): void
    {
        $content = "const mainNavItems: NavItem[] = [\n    // crud:nav:start\n];\n";

        $this->assertNull($this->region()->upsert($content, "'/clientes'", 'item'));
    }

    public function test_devolve_null_quando_o_marcador_final_vem_antes_do_inicial(): void
    {
        $content = "// crud:nav:end\n// crud:nav:start\n";

        $this->assertNull($this->region()->upsert($content, "'/clientes'", 'item'));
    }
}
```

Registrar o arquivo em `phpunit.xml`, dentro de `<testsuite name="Unit">`, logo após a linha do `InstallCommandStackTest.php`:

```xml
            <file>./tests/Unit/NavigationRegionTest.php</file>
```

- [ ] **Step 2: Rodar para confirmar que falha**

Run: `vendor/bin/phpunit --filter NavigationRegionTest`
Expected: FAIL — `Class "Crud\NavigationRegion" not found`

- [ ] **Step 3: Implementar o mínimo**

Criar `src/NavigationRegion.php`:

```php
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
```

- [ ] **Step 4: Rodar para confirmar que passa**

Run: `vendor/bin/phpunit --filter NavigationRegionTest`
Expected: PASS — 6 testes

Run: `vendor/bin/phpunit`
Expected: PASS — a suíte inteira, sem regressão nos 16 já existentes

- [ ] **Step 5: Commit**

```bash
git add src/NavigationRegion.php tests/Unit/NavigationRegionTest.php phpunit.xml
git commit -m "Add NavigationRegion to manage a marked span of a user file

The sidebar is a file the user edits, and each stack stores navigation in a
different format, so the package manages only the span between two comment
markers. This class does that as pure text: content in, content out, or null
when the region is missing or malformed. Refusing to write beats writing half
a file.

It takes no Laravel dependency, which makes it the first part of generation
that can be tested without MySQL."
```

---

### Task 2: NavigationRegion — criar a região a partir de uma âncora

**Files:**
- Modify: `src/NavigationRegion.php`
- Modify: `tests/Unit/NavigationRegionTest.php`

**Interfaces:**
- Consumes: a classe da Task 1.
- Produces: `install(string $content, string $openPattern, string $importLine): ?string`. Devolve o conteúdo com o import garantido e a região vazia criada antes do fechamento do array, ou `null` se a âncora não for encontrada.

- [ ] **Step 1: Escrever os testes que falham**

Acrescentar a `tests/Unit/NavigationRegionTest.php`:

```php
    private const OPEN_PATTERN = '/^const mainNavItems\s*:/';

    private function sidebarWithoutRegion(): string
    {
        return <<<'TSX'
        import { BookOpen, LayoutGrid } from 'lucide-react';
        import type { NavItem } from '@/types';

        const mainNavItems: NavItem[] = [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        ];
        TSX;
    }

    public function test_install_cria_a_regiao_antes_do_fechamento_do_array(): void
    {
        $result = $this->region()->install(
            $this->sidebarWithoutRegion(),
            self::OPEN_PATTERN,
            "import { List } from 'lucide-react';"
        );

        $this->assertNotNull($result);

        $dashboard = strpos($result, "title: 'Dashboard'");
        $start = strpos($result, '// crud:nav:start');
        $end = strpos($result, '// crud:nav:end');
        $close = strpos($result, '];');

        $this->assertGreaterThan($dashboard, $start, 'a região deve ficar abaixo do Dashboard');
        $this->assertGreaterThan($start, $end);
        $this->assertGreaterThan($end, $close, 'a região deve ficar dentro do array');
    }

    public function test_install_acrescenta_o_import_do_icone(): void
    {
        $result = $this->region()->install(
            $this->sidebarWithoutRegion(),
            self::OPEN_PATTERN,
            "import { List } from 'lucide-react';"
        );

        $this->assertSame(1, substr_count($result, "import { List } from 'lucide-react';"));
    }

    public function test_install_nao_duplica_um_import_ja_presente(): void
    {
        $region = $this->region();
        $import = "import { List } from 'lucide-react';";

        $once = $region->install($this->sidebarWithoutRegion(), self::OPEN_PATTERN, $import);
        $twice = $region->install($once, self::OPEN_PATTERN, $import);

        $this->assertSame(1, substr_count($twice, $import));
    }

    public function test_install_devolve_null_quando_a_ancora_nao_existe(): void
    {
        $content = "const outraCoisa = [\n];\n";

        $result = $this->region()->install(
            $content,
            self::OPEN_PATTERN,
            "import { List } from 'lucide-react';"
        );

        $this->assertNull($result);
    }

    public function test_o_resultado_do_install_aceita_upsert(): void
    {
        $region = $this->region();

        $installed = $region->install(
            $this->sidebarWithoutRegion(),
            self::OPEN_PATTERN,
            "import { List } from 'lucide-react';"
        );

        $result = $region->upsert(
            $installed,
            "'/clientes'",
            "{ title: 'Clientes', href: '/clientes', icon: List },"
        );

        $this->assertNotNull($result);
        $this->assertStringContainsString("href: '/clientes'", $result);
    }
```

- [ ] **Step 2: Rodar para confirmar que falha**

Run: `vendor/bin/phpunit --filter NavigationRegionTest`
Expected: FAIL — `Call to undefined method Crud\NavigationRegion::install()`

- [ ] **Step 3: Implementar o mínimo**

Acrescentar a `src/NavigationRegion.php`, depois de `upsert()`:

```php
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
```

- [ ] **Step 4: Rodar para confirmar que passa**

Run: `vendor/bin/phpunit --filter NavigationRegionTest`
Expected: PASS — 11 testes

Run: `vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/NavigationRegion.php tests/Unit/NavigationRegionTest.php
git commit -m "Let NavigationRegion create its own span from an anchor

Installing the markers is the one time the package writes outside a span it
already controls, so it needs an anchor. The caller supplies a pattern for the
line that opens the navigation array, and the span goes in before that array's
closing bracket, which puts generated entries below whatever the user already
has.

Anchoring on the array open rather than on the Dashboard entry is deliberate:
that entry is a multi-line object literal the user may have renamed or removed,
and matching the end of an object literal by regex is the parsing this design
exists to avoid. A missing anchor returns null, and the caller falls back to
printing the snippet."
```

---

### Task 3: Ligar ao InstallCommand na stack react

**Files:**
- Modify: `src/config/crud.php`
- Modify: `src/Console/InstallCommand.php:390` (encadear no fluxo da react) e acrescentar métodos junto de `buildUiComponents()` (linha ~1158)

**Interfaces:**
- Consumes: `Crud\NavigationRegion::upsert()` e `install()` das Tasks 1 e 2.
- Produces: `buildSidebarNavigation(): self`, encadeável no fluxo de `buildReactComponents()`.

- [ ] **Step 1: Acrescentar a chave de config**

Em `src/config/crud.php`, após o bloco `'inertia' => [...]` que termina antes de `'model' => [`:

```php
    /*
    |--------------------------------------------------------------------------
    | Navegação
    |--------------------------------------------------------------------------
    |
    | Insere um link para o CRUD gerado na sidebar do projeto, dentro de uma
    | região delimitada por comentários que o pacote gerencia. Em `false`, o
    | pacote nunca toca no arquivo de navegação.
    |
    */
    'navigation' => [
        'sidebar' => true,
    ],
```

- [ ] **Step 2: Implementar a orquestração**

Em `src/Console/InstallCommand.php`, acrescentar junto de `buildUiComponents()`:

```php
    /**
     * Arquivo de navegação e âncora de cada stack.
     *
     * O comentário acompanha a sintaxe do arquivo. Só a react está mapeada; as demais
     * entram junto com a implementação do respectivo builder.
     *
     * @var array<string, array{file: string, open: string, start: string, end: string, import: string}>
     */
    protected const SIDEBARS = [
        'react' => [
            'file' => 'js/components/app-sidebar.tsx',
            'open' => '/^const mainNavItems\s*:/',
            'start' => '// crud:nav:start',
            'end' => '// crud:nav:end',
            'import' => "import { List } from 'lucide-react';",
        ],
    ];

    /**
     * Insere o link do CRUD gerado na sidebar do projeto.
     *
     * Nunca aborta a geração: qualquer impedimento vira aviso mais o trecho para o
     * usuário colar onde quiser.
     */
    protected function buildSidebarNavigation(): self
    {
        if (!config('crud.navigation.sidebar', true)) {
            return $this;
        }

        $config = self::SIDEBARS[$this->template] ?? null;

        if ($config === null) {
            return $this;
        }

        $path = resource_path($config['file']);
        $item = sprintf(
            "{ title: '%s', href: '/%s', icon: List },",
            Str::title(Str::snake(Str::plural($this->name), ' ')),
            Str::kebab(Str::plural($this->name))
        );

        if (!$this->files->exists($path)) {
            warning("Sidebar não encontrada em {$config['file']}. Adicione o item à mão:");
            $this->line($item);

            return $this;
        }

        $region = new NavigationRegion($config['start'], $config['end']);
        $content = $this->files->get($path);
        $key = sprintf("'/%s'", Str::kebab(Str::plural($this->name)));

        if (!str_contains($content, $config['start'])) {
            if (!confirm('Adicionar o link na sidebar?', default: true)) {
                $this->line($item);

                return $this;
            }

            $installed = $region->install($content, $config['open'], $config['import']);

            if ($installed === null) {
                warning('Não consegui localizar a navegação em ' . $config['file'] . '. Adicione o item à mão:');
                $this->line($item);

                return $this;
            }

            $content = $installed;
        }

        $updated = $region->upsert($content, $key, $item);

        if ($updated === null) {
            warning('Marcadores de navegação malformados em ' . $config['file'] . '. Nada foi alterado.');
            $this->line($item);

            return $this;
        }

        $this->write($path, $updated);
        info('Link adicionado à sidebar.');

        return $this;
    }
```

Acrescentar o import no topo do arquivo, junto dos demais `use`:

```php
use Crud\NavigationRegion;
```

- [ ] **Step 3: Encadear no fluxo da react**

Em `src/Console/InstallCommand.php:390`, trocar:

```php
        $this->buildListComponent()->buildTypeScriptTypes()->buildUiComponents();
```

por:

```php
        $this->buildListComponent()->buildTypeScriptTypes()->buildUiComponents()->buildSidebarNavigation();
```

- [ ] **Step 4: Verificar no app real**

```bash
cd /home/sp1d3r/Documentos/projetos/pacotes/laravel/crud && vendor/bin/phpunit
```
Expected: PASS

```bash
cd /home/sp1d3r/Documentos/projetos/pacotes/laravel/projeto-exemplo-react
grep -n "crud:nav" resources/js/components/app-sidebar.tsx || echo "sem marcadores ainda"
php artisan getic:install clientes --stack=react -n
grep -n "crud:nav\|Clientes\|List" resources/js/components/app-sidebar.tsx
```
Expected: marcadores criados abaixo do Dashboard, item `{ title: 'Clientes', href: '/clientes', icon: List },` entre eles, e `import { List } from 'lucide-react';` presente uma vez.

```bash
php artisan getic:install clientes --stack=react -n
grep -c "href: '/clientes'" resources/js/components/app-sidebar.tsx
```
Expected: `1` — regerar não duplica.

```bash
npm run types:check 2>&1 | grep -E "app-sidebar" || echo "sidebar compila limpa"
```
Expected: sem erros no `app-sidebar.tsx`.

- [ ] **Step 5: Atualizar o CHANGELOG**

Em `CHANGELOG.md`, na seção `## [Não lançado]` → `### Adicionado`:

```markdown
- A stack `react` passa a inserir um link para o CRUD gerado na sidebar do projeto,
  numa região delimitada pelos comentários `crud:nav:start` / `crud:nav:end` que o
  pacote gerencia sozinho — o resto do seu menu nunca é tocado. Na primeira geração o
  pacote pergunta antes de criar a região. Desligue com `crud.navigation.sidebar => false`.
```

- [ ] **Step 6: Commit**

```bash
git add src/config/crud.php src/Console/InstallCommand.php CHANGELOG.md
git commit -m "Add the generated CRUD to the react sidebar

Generating a CRUD left no way to reach it: the sidebar still showed only
Dashboard, so the user had to type the URL.

The entry goes in a span delimited by crud:nav comments, and nothing outside
that span is ever rewritten. On the first generation the package asks before
creating the span; declining prints the snippet instead. crud.navigation.sidebar
turns the whole thing off.

href is a plain string rather than a wayfinder call. A typed route would need an
import at the top of the file, outside the managed span, which would double the
edit surface for a menu entry -- the generated pages still use wayfinder.

Only react is mapped. The other four stacks get their entry in SIDEBARS when
their builders are implemented; the text machinery is already stack-agnostic."
```

---

## Notas de implementação

**Import duplicado do lucide-react.** O arquivo já traz `import { BookOpen, FolderGit2, LayoutGrid } from 'lucide-react';`, e o pacote acrescenta um segundo import do mesmo módulo. É ES module válido e o TypeScript compila; um lint com `no-duplicate-imports` reclamaria. Fundir no import existente exigiria editar uma linha fora da região gerenciada, que é justamente o que este desenho evita.

**`{{modelTitlePlural}}` e `{{modelRoutePlural}}` não são usados como placeholders aqui.** O item é montado em PHP, não vem de stub, então as mesmas expressões `Str::` que alimentam esses placeholders em `buildReplacements()` (linhas 1389-1390) são repetidas em `buildSidebarNavigation()`. Se um dia divergirem, o link aponta para lugar errado — vale extrair para um método se aparecer um terceiro uso.
