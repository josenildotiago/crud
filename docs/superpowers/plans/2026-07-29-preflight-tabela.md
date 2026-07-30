# Pré-voo da tabela — plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Antes de escrever qualquer arquivo, avisar no terminal que a tabela tem características que o código gerado não suporta, e deixar a pessoa decidir se segue.

**Architecture:** Uma classe `final` sem framework e sem I/O (`src/TableInspection.php`) recebe as colunas no formato de `SHOW COLUMNS` e devolve achados estruturados (`code` + `columns`). O `InstallCommand` traduz código em frase portuguesa, pergunta, e repete um resumo no fim da execução. O `debugColumns()` sai no mesmo movimento.

**Tech Stack:** PHP 8.2+, Laravel 12/13, `laravel/prompts` (`warning`, `confirm`, `info`), PHPUnit 11.5.50+/12, Orchestra Testbench 10/11.

**Spec:** `docs/superpowers/specs/2026-07-29-preflight-tabela-design.md`

## Global Constraints

- Branch `preflight-tabela`. Merge na `main` no fim e **parar** — o dono revisa e dá o push. **Nunca** `git push`. Tag só com confirmação dele.
- Mensagens de console e prompts em **português**; mensagens de commit em **inglês**.
- A suíte não pode regredir. Estado inicial: `OK (42 tests, 93 assertions)`.
- **Nenhuma chave de config nova, nenhuma flag nova.** A API pública do pacote não muda: nomes e flags de comandos, chaves de `crud.php`/`themes.php`, tags de publish, placeholders `{{...}}` e a estrutura dos arquivos gerados ficam idênticos.
- Ordem fixa dos achados: `timestamps`, depois o de chave primária, depois `column-identifier`.
- `timestamps` é um achado só, mesmo faltando as duas colunas. `primary-key-missing` e `primary-key-not-id` são mutuamente exclusivos. `column-identifier` é um achado com todos os nomes ofensores.
- Regex de identificador, exatamente esta: `/^[A-Za-z_\x80-\xFF][A-Za-z0-9_\x80-\xFF]*$/`. É a regra do PHP, então `endereço` **não** dispara. Palavra reservada **não** é checada.
- Este trabalho **não** conserta o código gerado para schema fora da convenção (o `orderBy`, os campos de timestamp, `$timestamps`/`$primaryKey` no Model). Isso foi cortado do escopo pelo dono. O pré-voo avisa; não corrige.
- Ler config sempre com default (`config('crud.x.y', $default)`) — não se aplica aqui porque nenhuma config nova entra, mas vale se surgir a tentação.

## File Structure

| Arquivo | Responsabilidade |
|---|---|
| `src/TableInspection.php` | **Criar.** Única responsabilidade: dado o schema, dizer o que está fora da convenção. Sem framework, sem I/O, sem texto de interface. |
| `tests/Unit/TableInspectionTest.php` | **Criar.** PHPUnit puro, sem Testbench e sem banco. |
| `src/Console/InstallCommand.php` | **Modificar.** Chamar o pré-voo no `handle()`, traduzir achados, perguntar, imprimir o resumo final, remover `debugColumns()`. |
| `tests/Unit/InstallCommandPreflightTest.php` | **Criar.** A emenda entre classe e comando: prompt aparece, "não" não escreve nada, tabela convencional não pergunta. |
| `tests/Unit/InstallCommandSidebarNavigationTest.php` | **Modificar.** O dublê passa a fabricar colunas (hoje não sobrescreve `getColumns()` e iria ao banco); sai o override de `debugColumns()`. |
| `tests/Unit/InstallCommandNavigationRouteTest.php` | **Modificar.** O dublê fabrica colunas **sem timestamps** hoje, o que faria o pré-voo disparar e quebrar três testes; acrescentar `created_at`/`updated_at`. Sai o override de `debugColumns()`. |
| `tests/Unit/InstallCommandStackTest.php` | **Modificar.** Mesmo caso do sidebar: dublê sem `getColumns()`. Sai o override de `debugColumns()`. |
| `CHANGELOG.md`, `README.md`, `CLAUDE.md` | **Modificar.** Entrada da 3.3.0, nota de uso, e a pendência do `debugColumns()` tachada. |

---

### Task 1: A classe `TableInspection`

**Files:**
- Create: `src/TableInspection.php`
- Test: `tests/Unit/TableInspectionTest.php`

**Interfaces:**
- Consumes: nada. A classe é independente do resto do pacote.
- Produces: `Crud\TableInspection::inspect(array $columns): array`, onde `$columns` é `array<int, object>` no formato de `SHOW COLUMNS` (propriedades `Field`, `Type`, `Null`, `Key`, `Default`, `Extra`) e o retorno é `array<int, array{code: string, columns: array<int, string>}>`. Códigos possíveis: `timestamps`, `primary-key-missing`, `primary-key-not-id`, `column-identifier`.

- [ ] **Step 1: Escrever o arquivo de teste com o primeiro caso falhando**

Criar `tests/Unit/TableInspectionTest.php`. Note que estende `PHPUnit\Framework\TestCase` direto — **sem** Testbench, sem banco, no molde de `tests/Unit/NavigationRegionTest.php`:

```php
<?php

namespace Crud\Tests\Unit;

use Crud\TableInspection;
use PHPUnit\Framework\TestCase;

class TableInspectionTest extends TestCase
{
    /**
     * Uma coluna no formato que `SHOW COLUMNS` devolve.
     */
    private static function column(string $field, string $type = 'varchar(255)', string $key = ''): object
    {
        return (object) [
            'Field' => $field,
            'Type' => $type,
            'Null' => 'YES',
            'Key' => $key,
            'Default' => null,
            'Extra' => '',
        ];
    }

    /**
     * Tabela criada por migration do Laravel: nada a avisar.
     *
     * @return array<int, object>
     */
    private static function conventional(): array
    {
        return [
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('nome'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ];
    }

    private function codes(array $findings): array
    {
        return array_column($findings, 'code');
    }

    public function test_tabela_sem_os_dois_timestamps_gera_um_aviso_so(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('nome'),
        ]);

        $this->assertSame(['timestamps'], $this->codes($findings));
        $this->assertSame([], $findings[0]['columns']);
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha pelo motivo certo**

Run: `vendor/bin/phpunit --filter TableInspectionTest`
Expected: FAIL com `Error: Class "Crud\TableInspection" not found`. É o motivo esperado — a classe ainda não existe.

- [ ] **Step 3: Criar a classe com o mínimo para passar**

Criar `src/TableInspection.php`:

```php
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
```

- [ ] **Step 4: Rodar e confirmar que passa**

Run: `vendor/bin/phpunit --filter TableInspectionTest`
Expected: `OK (1 test, 2 assertions)`

- [ ] **Step 5: Commit**

```bash
git add src/TableInspection.php tests/Unit/TableInspectionTest.php
git commit -m "Report a table missing the Laravel timestamps"
```

- [ ] **Step 6: Teste falhando para a tabela convencional e para a falta de uma coluna só**

Acrescentar a `TableInspectionTest`:

```php
    public function test_tabela_convencional_nao_gera_aviso(): void
    {
        $this->assertSame([], (new TableInspection())->inspect(self::conventional()));
    }

    public function test_falta_de_uma_coluna_de_timestamp_ja_gera_o_aviso(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('created_at', 'timestamp'),
        ]);

        $this->assertSame(['timestamps'], $this->codes($findings));
    }
```

- [ ] **Step 7: Rodar**

Run: `vendor/bin/phpunit --filter TableInspectionTest`
Expected: PASS nos três. Estes dois já passam com a implementação do Step 3 — são testes de guarda contra falso positivo e contra a regra do "ou", não novo comportamento. Se algum falhar, a implementação do Step 3 está errada.

- [ ] **Step 8: Commit**

```bash
git add tests/Unit/TableInspectionTest.php
git commit -m "Cover the conventional table and the half-missing timestamps"
```

- [ ] **Step 9: Testes falhando para a chave primária**

Acrescentar a `TableInspectionTest`:

```php
    public function test_tabela_sem_chave_primaria_declarada(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('idClientes', 'int'),
            self::column('nomeCliente'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ]);

        $this->assertSame(['primary-key-missing'], $this->codes($findings));
        $this->assertSame([], $findings[0]['columns']);
    }

    public function test_chave_primaria_com_nome_diferente_de_id(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('idClientes', 'int', 'PRI'),
            self::column('nomeCliente'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ]);

        $this->assertSame(['primary-key-not-id'], $this->codes($findings));
        $this->assertSame(['idClientes'], $findings[0]['columns']);
    }
```

- [ ] **Step 10: Rodar e confirmar que os dois falham**

Run: `vendor/bin/phpunit --filter TableInspectionTest`
Expected: FAIL nos dois novos, ambos com `Failed asserting that two arrays are identical` — o array devolvido vem vazio porque a checagem de chave não existe.

- [ ] **Step 11: Implementar a checagem de chave primária**

Em `src/TableInspection.php`, dentro de `inspect()`, entre o bloco de timestamps e o `return`:

```php
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
```

O `break` na primeira `PRI` é deliberado: em chave composta o MySQL marca `PRI` em várias colunas, e para o efeito que nos interessa — `$model->id` vir nulo — basta saber que a chave não é `id`.

- [ ] **Step 12: Rodar**

Run: `vendor/bin/phpunit --filter TableInspectionTest`
Expected: `OK (5 tests, ...)`

- [ ] **Step 13: Commit**

```bash
git add src/TableInspection.php tests/Unit/TableInspectionTest.php
git commit -m "Report a primary key the generated code cannot use"
```

- [ ] **Step 14: Testes falhando para nome de coluna inválido**

Acrescentar a `TableInspectionTest`:

```php
    public function test_coluna_com_nome_que_nao_e_identificador_valido(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('2fa_secret'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ]);

        $this->assertSame(['column-identifier'], $this->codes($findings));
        $this->assertSame(['2fa_secret'], $findings[0]['columns']);
    }

    public function test_varias_colunas_invalidas_viram_um_achado_so(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('2fa_secret'),
            self::column('nome-cliente'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ]);

        $this->assertSame(['column-identifier'], $this->codes($findings));
        $this->assertSame(['2fa_secret', 'nome-cliente'], $findings[0]['columns']);
    }

    public function test_nome_acentuado_e_identificador_valido_em_php(): void
    {
        $findings = (new TableInspection())->inspect([
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('endereço'),
            self::column('órgão'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ]);

        $this->assertSame([], $findings, 'Acento não quebra `$model->endereço` nem o tipo TS.');
    }
```

- [ ] **Step 15: Rodar e confirmar que os dois primeiros falham e o terceiro passa**

Run: `vendor/bin/phpunit --filter TableInspectionTest`
Expected: FAIL em `test_coluna_com_nome_que_nao_e_identificador_valido` e em `test_varias_colunas_invalidas_viram_um_achado_so`; o de acento **passa** desde já (nenhuma checagem existe ainda, então nada é reportado). O de acento é guarda contra falso positivo que a implementação do próximo step poderia introduzir.

- [ ] **Step 16: Implementar a checagem de identificador**

Em `src/TableInspection.php`, dentro de `inspect()`, imediatamente antes do `return $findings;`:

```php
        $invalid = array_values(array_filter(
            $names,
            static fn (string $name): bool => preg_match(self::IDENTIFIER, $name) !== 1
        ));

        if ($invalid !== []) {
            $findings[] = ['code' => 'column-identifier', 'columns' => $invalid];
        }
```

- [ ] **Step 17: Rodar a suíte inteira**

Run: `vendor/bin/phpunit`
Expected: `OK (51 tests, ...)` — os 42 de antes mais os 9 novos, sem nenhuma falha. Se algum teste antigo falhar aqui, pare: a classe nova não deveria ter afetado nada ainda, porque ninguém a chama.

- [ ] **Step 18: Commit**

```bash
git add src/TableInspection.php tests/Unit/TableInspectionTest.php
git commit -m "Report column names that are not valid identifiers"
```

- [ ] **Step 19: Teste falhando para a ordem e para a tabela `clientes` real**

Acrescentar a `TableInspectionTest`:

```php
    public function test_a_ordem_dos_achados_e_fixa(): void
    {
        // `clientes` do banco de teste, reduzida: sem timestamps, sem PRI, e com um nome
        // inválido acrescentado para exercitar os três de uma vez.
        $findings = (new TableInspection())->inspect([
            self::column('idClientes', 'int'),
            self::column('2fa_secret'),
        ]);

        $this->assertSame(
            ['timestamps', 'primary-key-missing', 'column-identifier'],
            $this->codes($findings)
        );
    }
```

- [ ] **Step 20: Rodar**

Run: `vendor/bin/phpunit --filter TableInspectionTest`
Expected: PASS. A ordem já sai certa porque os blocos foram escritos nessa sequência; este teste é o que impede alguém de reordená-los sem perceber.

- [ ] **Step 21: Commit**

```bash
git add tests/Unit/TableInspectionTest.php
git commit -m "Pin the order the findings come back in"
```

---

### Task 2: Ligar no comando e enterrar o `debugColumns()`

**Files:**
- Modify: `src/Console/InstallCommand.php`
- Create: `tests/Unit/InstallCommandPreflightTest.php`
- Modify: `tests/Unit/InstallCommandSidebarNavigationTest.php`
- Modify: `tests/Unit/InstallCommandNavigationRouteTest.php`
- Modify: `tests/Unit/InstallCommandStackTest.php`

**Interfaces:**
- Consumes: `Crud\TableInspection::inspect()` da Task 1, com a forma exata declarada lá.
- Produces: `InstallCommand::preflightTable(): bool` (protected) — `true` para seguir, `false` para abortar; e a propriedade `protected int $preflightWarnings` com a contagem, lida no fim do `handle()`.

**Por que os três arquivos de teste existentes entram nesta task:** o pré-voo chama `getColumns()` dentro do `handle()`, e hoje

- `SidebarSpyInstallCommand` e o dublê de `InstallCommandStackTest` **não** sobrescrevem `getColumns()` — passariam a tocar o banco, que não existe no Testbench;
- `RouteAndSidebarSpyInstallCommand` sobrescreve, mas devolve colunas **sem** `created_at`/`updated_at` — o pré-voo dispararia, o prompt apareceria, e os três testes daquele arquivo quebrariam com expectativa de confirmação não registrada.

Todos os dublês passam a fabricar um schema **convencional**, para que o pré-voo fique silencioso e nenhum teste existente precise ganhar `expectsConfirmation`.

- [ ] **Step 1: Escrever o teste da emenda, falhando**

Criar `tests/Unit/InstallCommandPreflightTest.php`:

```php
<?php

namespace Crud\Tests\Unit;

use Crud\Console\InstallCommand;
use Crud\CrudServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

/**
 * Dublê que só deixa o pré-voo e o `handle()` reais. `buildRouter()` registra que rodou:
 * é assim que o teste prova que responder "não" não escreveu nada, sem depender de
 * inspecionar disco.
 */
class PreflightSpyInstallCommand extends InstallCommand
{
    protected $signature = 'test:preflight {name : Table name}
                                            {--stack=react : Frontend stack (react, vue, blade)}
                                            {--routes= : Route helper for the generated components (ziggy, wayfinder)}
                                            {--route= : Custom route name}
                                            {--relationship : Specify if you want to establish a relationship}
                                            {--api : Generate API endpoints}
                                            {--theme : Include theme-aware components}';

    /** @var array<int, object> */
    public array $columns = [];

    public bool $generated = false;

    protected function tableExists()
    {
        return true;
    }

    protected function getColumns()
    {
        return $this->columns;
    }

    protected function buildController(): self
    {
        return $this;
    }

    protected function buildModel(): self
    {
        return $this;
    }

    protected function buildViews(): self
    {
        return $this;
    }

    public function buildRouter(): self
    {
        $this->generated = true;

        return $this;
    }

    protected function generateWayfinderRoutes(): self
    {
        return $this;
    }
}

class InstallCommandPreflightTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [CrudServiceProvider::class];
    }

    private static function column(string $field, string $type = 'varchar(255)', string $key = ''): object
    {
        return (object) [
            'Field' => $field,
            'Type' => $type,
            'Null' => 'YES',
            'Key' => $key,
            'Default' => null,
            'Extra' => '',
        ];
    }

    /** @return array<int, object> */
    private static function legacy(): array
    {
        return [
            self::column('idClientes', 'int'),
            self::column('nomeCliente'),
        ];
    }

    /** @return array<int, object> */
    private static function conventional(): array
    {
        return [
            self::column('id', 'bigint unsigned', 'PRI'),
            self::column('nome'),
            self::column('created_at', 'timestamp'),
            self::column('updated_at', 'timestamp'),
        ];
    }

    /** @param array<int, object> $columns */
    private function spyCommand(array $columns): PreflightSpyInstallCommand
    {
        $command = new PreflightSpyInstallCommand(new Filesystem());
        $command->columns = $columns;

        $this->app[Kernel::class]->registerCommand($command);

        return $command;
    }

    public function test_recusar_o_aviso_nao_gera_nada(): void
    {
        $command = $this->spyCommand(self::legacy());

        $this->artisan('test:preflight clientes')
            ->expectsConfirmation('Gerar mesmo assim?', 'no')
            ->assertFailed();

        $this->assertFalse($command->generated, 'Nenhum arquivo deveria ter sido gerado.');
    }

    public function test_aceitar_o_aviso_gera(): void
    {
        $command = $this->spyCommand(self::legacy());

        $this->artisan('test:preflight clientes')
            ->expectsConfirmation('Gerar mesmo assim?', 'yes')
            ->assertExitCode(0);

        $this->assertTrue($command->generated);
    }

    public function test_tabela_convencional_nao_pergunta_nada(): void
    {
        $command = $this->spyCommand(self::conventional());

        // Sem expectsConfirmation: se o comando perguntar, o mock não tem expectativa
        // registrada e o teste falha. É esse o ponto.
        $this->artisan('test:preflight clientes')->assertExitCode(0);

        $this->assertTrue($command->generated);
    }
}
```

- [ ] **Step 2: Rodar e confirmar a falha**

Run: `vendor/bin/phpunit --filter InstallCommandPreflightTest`
Expected: FAIL. `test_recusar_o_aviso_nao_gera_nada` falha porque o comando não pergunta nada e gera de qualquer forma (`assertFailed()` não bate, e `generated` é `true`). `test_aceitar_o_aviso_gera` falha pela confirmação esperada e não recebida. O terceiro passa.

- [ ] **Step 3: Ligar o pré-voo no `handle()`**

Em `src/Console/InstallCommand.php`, no `handle()`, **entre** a checagem de versão do Laravel e a atribuição de `$this->name`:

```php
        // Check Laravel version
        if (!$this->isLaravel12OrHigher()) {
            $this->components->error('Este pacote requer Laravel 12 ou superior');
            return self::FAILURE;
        }

        if (!$this->preflightTable()) {
            return self::FAILURE;
        }

        $this->name = $this->_buildClassName();
```

Este é o último ponto em que nenhum arquivo do CRUD foi escrito. (O `crud:install-theme-system`, quando aceito no prompt inicial, roda antes disto: ele é disparado no `interact()` do Artisan, fora do `handle()`.)

- [ ] **Step 4: Acrescentar a propriedade e os dois métodos**

Ainda em `src/Console/InstallCommand.php`. A propriedade junto das outras do topo da classe:

```php
    /**
     * Quantos avisos o pré-voo levantou, para o resumo do fim da execução.
     */
    protected int $preflightWarnings = 0;
```

E os dois métodos, logo depois de `isLaravel12OrHigher()`:

```php
    /**
     * Avisa sobre a tabela antes de escrever qualquer arquivo do CRUD.
     *
     * Avisa, não bloqueia: a tabela é do usuário, e gerar em cima de uma tabela torta
     * para ajustar o Model à mão depois é caso legítimo. Devolve `false` só quando a
     * pessoa responde que não quer seguir.
     */
    protected function preflightTable(): bool
    {
        $findings = (new TableInspection())->inspect($this->getColumns());

        if ($findings === []) {
            return true;
        }

        $this->preflightWarnings = count($findings);

        warning(sprintf(
            '%d %s sobre a tabela `%s`:',
            $this->preflightWarnings,
            $this->preflightWarnings === 1 ? 'aviso' : 'avisos',
            $this->table
        ));

        foreach ($findings as $finding) {
            $this->line('   • ' . $this->preflightMessage($finding));
        }

        // Sem terminal interativo não há como confirmar, e a postura é não bloquear:
        // segue, mas dizendo que segue por conta e risco de quem chamou.
        if (!$this->input->isInteractive()) {
            $this->line('   Modo não interativo: seguindo por sua conta e risco.');

            return true;
        }

        if (confirm('Gerar mesmo assim?', default: true)) {
            return true;
        }

        info('Geração cancelada.');

        return false;
    }

    /**
     * Frase em português para cada achado do pré-voo.
     *
     * @param array{code: string, columns: array<int, string>} $finding
     */
    protected function preflightMessage(array $finding): string
    {
        return match ($finding['code']) {
            'timestamps' => 'Sem `created_at`/`updated_at`: a listagem gerada ordena por `created_at` e vai falhar no banco.',
            'primary-key-missing' => 'Sem chave primária declarada: o `Index` usa `id` para a key da linha e para os links de ver/editar/excluir, e ele vai vir nulo.',
            'primary-key-not-id' => sprintf(
                'A chave primária é `%s`, não `id`: o Model gerado não declara `$primaryKey`, então `id` vai vir nulo no `Index`.',
                $finding['columns'][0]
            ),
            'column-identifier' => count($finding['columns']) === 1
                ? sprintf(
                    'A coluna `%s` não é um identificador válido: o Controller e o tipo TypeScript gerados não vão compilar.',
                    $finding['columns'][0]
                )
                : sprintf(
                    'As colunas %s não são identificadores válidos: o Controller e o tipo TypeScript gerados não vão compilar.',
                    implode(', ', array_map(static fn (string $column): string => "`{$column}`", $finding['columns']))
                ),
        };
    }
```

E o `use` da classe nova, junto dos outros imports no topo do arquivo:

```php
use Crud\TableInspection;
```

Os helpers `warning`, `confirm` e `info` do `laravel/prompts` já estão importados (linhas 13-18).

- [ ] **Step 5: Rodar o teste da emenda**

Run: `vendor/bin/phpunit --filter InstallCommandPreflightTest`
Expected: `OK (3 tests, ...)`

- [ ] **Step 6: Rodar a suíte inteira e ver os três arquivos antigos quebrarem**

Run: `vendor/bin/phpunit`
Expected: FALHAS em `InstallCommandSidebarNavigationTest`, `InstallCommandNavigationRouteTest` e `InstallCommandStackTest`. É esperado e está previsto: o `handle()` agora chama `getColumns()`, e os dublês daqueles arquivos ou vão ao banco ou devolvem schema sem timestamps. Os próximos steps consertam. **Não** conserte relaxando o pré-voo.

- [ ] **Step 7: Dar colunas convencionais ao dublê do sidebar**

Em `tests/Unit/InstallCommandSidebarNavigationTest.php`, na classe `SidebarSpyInstallCommand`, **substituir** o método `debugColumns()` por um `getColumns()`:

```php
    /**
     * Schema convencional para o pré-voo ficar silencioso: este arquivo é sobre a
     * sidebar, não sobre a inspeção da tabela.
     */
    protected function getColumns()
    {
        return [
            (object) ['Field' => 'id', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Key' => 'PRI', 'Default' => null, 'Extra' => 'auto_increment'],
            (object) ['Field' => 'nome', 'Type' => 'varchar(255)', 'Null' => 'NO', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'created_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'updated_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
        ];
    }
```

- [ ] **Step 8: Acrescentar os timestamps ao dublê de rotas e tirar o `debugColumns()`**

Em `tests/Unit/InstallCommandNavigationRouteTest.php`, na classe `RouteAndSidebarSpyInstallCommand`: remover o override de `debugColumns()` e acrescentar as duas colunas de timestamp ao `getColumns()` existente, que passa a ser:

```php
    protected function getColumns()
    {
        return [
            (object) ['Field' => 'id', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Key' => 'PRI', 'Default' => null, 'Extra' => 'auto_increment'],
            (object) ['Field' => 'nome', 'Type' => 'varchar(255)', 'Null' => 'NO', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'created_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'updated_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
        ];
    }
```

- [ ] **Step 9: Dar colunas convencionais ao dublê do stack**

Em `tests/Unit/InstallCommandStackTest.php`, no dublê: remover o override de `debugColumns()` e acrescentar:

```php
    /**
     * Schema convencional para o pré-voo ficar silencioso: este arquivo é sobre a
     * resolução de stack, não sobre a inspeção da tabela.
     */
    protected function getColumns()
    {
        return [
            (object) ['Field' => 'id', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Key' => 'PRI', 'Default' => null, 'Extra' => 'auto_increment'],
            (object) ['Field' => 'nome', 'Type' => 'varchar(255)', 'Null' => 'NO', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'created_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
            (object) ['Field' => 'updated_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
        ];
    }
```

Atenção ao `test_invalid_stack_fails_before_touching_the_database`: ele continua valendo, porque a stack é validada antes do pré-voo no `handle()`.

- [ ] **Step 10: Remover o `debugColumns()` do comando**

Em `src/Console/InstallCommand.php`, remover do `handle()`:

```php
        // Adicionar temporariamente para debug
        $this->debugColumns();
```

E remover o método `debugColumns()` inteiro (hoje por volta da linha 1393, começando em `protected function debugColumns(): void`).

- [ ] **Step 11: Rodar a suíte inteira**

Run: `vendor/bin/phpunit`
Expected: `OK (57 tests, ...)` — os 54 do fim da Task 1 (9 de TableInspectionTest + 45 existentes) mais os 3 novos de InstallCommandPreflightTest, todos verdes. Se sobrar falha em algum dos três arquivos antigos, o dublê correspondente ainda não está fabricando schema convencional.

- [ ] **Step 12: Acrescentar o resumo do fim da execução**

Em `src/Console/InstallCommand.php`, no fim do `handle()`, entre o `bulletList` e o `return`:

```php
        $this->components->info('Arquivos gerados:');
        $this->components->bulletList($generated);

        if ($this->preflightWarnings > 0) {
            warning(sprintf(
                'Gerado com %d %s sobre `%s` (acima). Revise antes de usar.',
                $this->preflightWarnings,
                $this->preflightWarnings === 1 ? 'aviso' : 'avisos',
                $this->table
            ));
        }

        return self::SUCCESS;
```

- [ ] **Step 13: Rodar a suíte de novo**

Run: `vendor/bin/phpunit`
Expected: `OK (57 tests, ...)`. O resumo não tem teste próprio de propósito: é uma linha de console derivada de uma contagem já coberta, e testar texto de interface deixa a prosa presa ao teste.

- [ ] **Step 14: Verificar no app real, nos dois schemas**

```bash
cd /home/sp1d3r/Documentos/projetos/pacotes/laravel/projeto-exemplo-react

# tabela legada: dois avisos e o prompt
php artisan getic:install clientes --stack=react
# responda "no" e confirme que nada foi escrito:
ls app/Http/Controllers/ClienteController.php routes/cliente.php

# modo não interativo: avisa, segue, e repete no fim
php artisan getic:install clientes --stack=react --no-interaction | tail -20

# tabela Laravel de verdade: nenhum aviso, nenhum prompt
php artisan getic:install items --stack=react --no-interaction | tail -12
```

Esperado: na `clientes`, `2 avisos` (timestamps e chave primária ausente); com `--no-interaction`, os avisos mais a linha de conta e risco, e o resumo como última coisa na tela; na `items`, nenhum aviso e nenhum prompt. O JSON do `debugColumns()` não aparece em nenhum dos três.

Limpe o app depois: os arquivos de `clientes` e `items` gerados aqui não devem ficar.

- [ ] **Step 15: Commit**

```bash
git add src/Console/InstallCommand.php tests/Unit/InstallCommandPreflightTest.php \
        tests/Unit/InstallCommandSidebarNavigationTest.php \
        tests/Unit/InstallCommandNavigationRouteTest.php \
        tests/Unit/InstallCommandStackTest.php
git commit -m "Warn about the table before writing any file

The command only ever checked that the table existed, so generating on a legacy
table succeeded and the page 500'd on the first visit. The preflight now runs
before the first write, says what the generated code cannot handle, and lets the
caller decide -- it warns, it does not block.

debugColumns() goes away with it: it dumped every column as JSON on every run and
did so after the files were already written."
```

---

### Task 3: Documentação

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `README.md`
- Modify: `CLAUDE.md`

**Interfaces:**
- Consumes: o comportamento entregue nas Tasks 1 e 2.
- Produces: nada que outra task consuma.

- [ ] **Step 1: Entrada no CHANGELOG**

Em `CHANGELOG.md`, **acima** de `## [3.2.0] - 2026-07-29`:

```markdown
## [Não lançado]

### Adicionado

- **Pré-voo da tabela.** Antes de escrever qualquer arquivo, o `getic:install` inspeciona
  a tabela e avisa o que o código gerado não suporta: falta de `created_at`/`updated_at`
  (a listagem gerada ordena por `created_at` e falha no banco), chave primária ausente ou
  com nome diferente de `id` (o `Index` usa `id` para a key da linha e para os links), e
  coluna cujo nome não é um identificador válido (o Controller e o tipo TypeScript não
  compilam). **Avisa, não bloqueia:** você confirma e a geração segue. Em modo não
  interativo ele avisa, segue, e repete o resumo no fim da execução. Numa tabela
  convencional é silencioso — não pergunta nada.

### Removido

- `debugColumns()`, que despejava o JSON de todas as colunas no terminal em toda geração,
  e o fazia depois de os arquivos já estarem escritos. O pré-voo entrega, antes da
  escrita, o que ele fingia entregar.
```

- [ ] **Step 2: Nota no README**

Em `README.md`, depois da seção "Link na sidebar" (que a 3.2.0 acrescentou):

```markdown
#### Pré-voo da tabela

Antes de escrever qualquer arquivo, o pacote confere se a tabela tem o que o código
gerado assume: `created_at`/`updated_at`, chave primária chamada `id`, e nomes de coluna
que sejam identificadores válidos. Se algo falta, ele lista os avisos e pergunta se você
quer gerar mesmo assim — **avisa, não bloqueia**, porque gerar em cima de uma tabela
legada e ajustar o Model à mão depois é caso legítimo. Numa tabela criada pelas migrations
do Laravel ele não diz nada.

Em modo não interativo (`--no-interaction`, script, CI) ele imprime os avisos, segue, e
repete o resumo no fim — por sua conta e risco.
```

- [ ] **Step 3: Tachar a pendência no CLAUDE.md**

Em `CLAUDE.md`, na seção "Pendências conhecidas", em **Bugs**, substituir a linha do `debugColumns()` por:

```markdown
- ~~`debugColumns()` roda em toda geração, despejando JSON no terminal.~~ Resolvido: o
  método saiu, e o pré-voo da tabela (`src/TableInspection.php`, chamado no `handle()`
  antes da primeira escrita) ocupou o lugar com algo que serve — ver
  `docs/superpowers/specs/2026-07-29-preflight-tabela-design.md`.
```

E no **Mapa**, na listagem de `src/`, acrescentar depois de `ModelGenerator.php`:

```markdown
  TableInspection.php          # pré-voo: o que na tabela o código gerado não suporta
```

- [ ] **Step 4: Conferir que nada mais afirma o antigo**

Run: `grep -rn "debugColumns" README.md CLAUDE.md CHANGELOG.md src/ tests/`
Expected: nenhuma ocorrência fora da linha tachada do `CLAUDE.md` e da entrada de "Removido" do `CHANGELOG.md`.

- [ ] **Step 5: Commit**

```bash
git add CHANGELOG.md README.md CLAUDE.md
git commit -m "Document the table preflight and the debugColumns removal"
```

---

## Fechamento

- [ ] **Rodar a verificação completa**

```bash
vendor/bin/phpunit
composer validate --strict
```

Esperado: `OK (57 tests, ...)` e `./composer.json is valid`.

- [ ] **Merge na `main` e parar**

O histórico do repositório é linear e nunca teve merge commit, então fast-forward:

```bash
git checkout main
git merge --ff-only preflight-tabela
vendor/bin/phpunit
```

**Não** dar `git push` e **não** criar tag. O dono revisa o diff, dá o push, e o CI (`.github/workflows/tests.yml`, 7 jobs) roda no push da `main`. A tag `v3.3.0` sai só depois da confirmação dele.
