# josenildotiago/crud

Pacote Composer que gera CRUD completo em projetos Laravel a partir de uma tabela
existente no banco. **Este repositório não é uma aplicação Laravel** — não existe
`app/`, `routes/` nem `database/migrations/`. O pacote não serve nada em runtime:
ele escreve arquivos dentro do projeto do usuário via comandos Artisan.

Laravel 12 e 13, PHP 8.2+ — `InstallCommand::isLaravel12OrHigher()` falha abaixo de 12.
Não ampliar a faixa para baixo sem pedido.

**Política de major do Laravel:** a cada novo major do framework, reverificar as cinco
stacks contra os projetos de exemplo e, se algo mudou, corrigir e lançar nova versão +
tag de release. O pacote está publicado em https://packagist.org/packages/josenildotiago/crud.

**Fluxo de git:** trabalhar em branch, merge na `main` no fim, e parar — o dono revisa e
dá o push ele mesmo. Nunca dar `git push`. Tag de release só após confirmação dele.
Mensagens de commit em inglês; mensagens de console e prompts em português.

## Projetos de teste

Em `/home/sp1d3r/Documentos/projetos/pacotes/laravel/` (o diretório que contém este
repositório) existem quatro starter kits oficiais Laravel 13.23.0 para verificar a
geração: `projeto-exemplo-react`, `-vue`, `-svelte`, `-livewire`. O pacote entra neles
por repositório `path` com symlink, então editar um stub reflete na hora. Todos apontam
para o MySQL `getran52_ordemservicos_dev` (61 tabelas com dados reais).

### O que mudou no Laravel 13

Levantado em 29/07/2026 nos starter kits. Atinge o código gerado, não o gerador:

- **`laravel/wayfinder`** — rotas viram funções TypeScript importadas, no lugar de string
  ou helper `route()`. Muda os componentes React/Vue/Svelte gerados.
- **`inertiajs/inertia-laravel` ^3.0** — Inertia 3, era 2.x.
- **`laravel/fortify`** — auth dos starter kits.
- **`livewire/livewire` ^4.1 + `livewire/flux` ^2.13 + `livewire/blaze`** — base da stack livewire.
- `laravel/chisel`, `laravel/pao`, `laravel/passkeys` — novos, impacto não avaliado.

## Subagentes

- `laravel-pacote` — empacotamento: ServiceProvider, composer.json, tags de publish,
  comandos Artisan, organização de stubs, Testbench, semver.
- `laravel-especialista` — o código Laravel/PHP: stubs e a lógica de geração.

## As duas camadas

Nunca confundir:

1. **Código gerador** (`src/Console/`, `src/ModelGenerator.php`, `src/CrudManager.php`) —
   PHP que roda no app do usuário durante `php artisan`. Regras normais de Laravel.
2. **Código gerado** (`src/stubs/`) — texto com placeholders `{{...}}`. Não é PHP válido
   até a substituição. Não executar, não lintar, não "corrigir" a sintaxe dos `{{}}`.

Todo placeholder novo precisa entrar no mapa de `GeneratorCommand::buildReplacements()`
(ou no override em `InstallCommand`), senão vai literal para o arquivo do usuário.

Em stubs Blade convivem `{{ $var }}` (Blade, **com** espaços) e `{{placeholder}}` (do
pacote, **sem** espaços). A separação é só o espaço.

## Mapa

```
src/
  CrudServiceProvider.php     # merge de crud.php + themes.php; singleton 'crud'; publishes
  CrudManager.php             # binding 'crud'; detecta sistema de temas instalado
  ModelGenerator.php          # infere relacionamentos Eloquent do schema
  Facades/Crud.php
  config/crud.php             # config mora em src/config/, não em config/
  config/themes.php
  Console/
    GeneratorCommand.php      # base abstrata: introspecção de schema + replacements
    InstallCommand.php        # getic:install — fluxo principal
    InstallThemeSystemCommand.php   # crud:install-theme-system
    CreateThemeCommand.php          # crud:create-theme
    InstallOnlyServicesCommand.php  # crud:install-only-services
    buildOptions.php          # trait
  stubs/                      # o produto real do pacote
```

Fluxo do `getic:install {tabela}`:
`buildOptions → buildController → buildModel → buildViews → buildRouter`,
e com `--api` também `buildApiController → buildApiRoutes → buildApiResources → buildFormRequest`.

Gera no app do usuário: Model, Controller (Inertia ou clássico), componentes React,
`routes/{model}.php` com `require` idempotente no `web.php`, e opcionalmente
API Controller / Resource / Collection / FormRequest.

## Stacks

| Stack | Situação |
|---|---|
| `react` | Implementado — Inertia + TypeScript + shadcn/ui |
| `vue` | Stubs existem em `src/stubs/views/vue-*`; `buildVueComponents()` **vazio** |
| `blade` | Stubs existem em `src/stubs/views/blade-*`; `buildBladeViews()` **vazio** |
| `svelte` | A criar — não existe nada ainda |
| `livewire` | A criar. **Não é a stack `blade`** — o starter kit é Livewire 4 + Flux 2 |
| `heron` | **Congelado.** Stack interna, Blade/Bootstrap, `src/stubs/views/heron/`. Manter funcionando, não alterar. |

## API pública (mudar = breaking)

1. Nome e assinatura dos comandos Artisan e suas flags
2. Chaves de `src/config/crud.php` e `src/config/themes.php`
3. Tags de publish (`crud-config`, `crud-assets`, `theme-system`)
4. Placeholders `{{...}}` — quem usa `crud.stub_path` customizado depende deles
5. Estrutura dos arquivos gerados que o usuário depois edita à mão

## Pendências conhecidas

Levantadas em 29/07/2026, ainda não corrigidas. Ao mexer perto de uma delas, corrigir
junto ou avisar — não fingir que não existe.

**Bugs**
- ~~`--stack` não é honrado.~~ Corrigido em 29/07/2026: `handle()` resolve
  `$this->template ??= $this->option('stack')` e valida contra `InstallCommand::STACKS`;
  `afterPromptingForMissingArguments()` pula o prompt quando `--stack` vem explícito na
  linha de comando. Coberto por `tests/Unit/InstallCommandStackTest.php`.
- `debugColumns()` roda em toda geração, despejando JSON no terminal.
- `InstallCommand::STACKS` é `['react', 'vue', 'blade']` — `svelte` e `livewire` são
  rejeitadas no `handle()` antes de qualquer geração.
- **As respostas interativas de `--api` e `--theme` são descartadas** — mesma raiz do bug
  do `--stack`. `afterPromptingForMissingArguments()` grava em `$this->options['api']` e
  `$this->options['theme']` (o array custom do `GeneratorCommand`, que só é lido para
  `route`), mas `handle()` e `buildReactComponents()` leem `$this->option('api')` /
  `$this->option('theme')` (as opções do Symfony). Responder "sim" no prompt não faz nada;
  só funciona passando as flags na linha de comando.
- Tag `crud-assets` publica `src/stubs/js` e `src/stubs/css` — nenhum dos dois existe.
- `$this->name = $this->_buildClassName()` sobrescreve o `$name` do `Illuminate\Console\Command`.

**Contradições**
- `config/crud.php` anuncia `mysql, pgsql, sqlite, sqlsrv`, mas `getColumns()` e
  `getAllTableNames()` usam `SHOW COLUMNS` / `SHOW TABLES` — MySQL puro. É isso que
  impede testar geração com sqlite `:memory:` no Testbench.
- `mergeConfigFrom(..., 'themes')` ocupa a chave global `config('themes')`; a tag
  `theme-system` não é prefixada. Ambas colidem com outros pacotes.
- `{{modelTable}}` definido duas vezes com valores diferentes: o pai usa o nome da classe,
  `InstallCommand` sobrescreve com o nome da tabela (o override vence no `array_merge`).
- `tests/Unit/CrudPackageTest.php` foi escrito contra API inexistente: espera `getThemes()`
  devolvendo array associativo (devolve `Collection` de ids) e arquivos em
  `js/types/themes.ts` (o código usa `js/lib/themes.ts`). Especificação aspiracional, não
  suíte válida. O `phpunit.xml` só carrega `InstallCommandTest.php`, então isso passa batido.
- Versões divergentes: `composer.json` 3.1.4, README 3.0.18, REPORT.md 2.1.3. O campo
  `version` do composer.json deveria sair (Packagist deriva de tag git) — o próprio
  `composer validate` avisa sobre isso.
- README manda `vendor:publish --tag="config"`; a tag real é `crud-config`.
- Prefixo `getic:` no comando principal vs `crud:` nos outros três.
- `test_install.php` solto na raiz.

## Convenções

- Prompts e mensagens de console em **português** (`laravel/prompts`).
- Nunca sobrescrever arquivo do usuário sem `confirm()` ou `--force`.
- Ler config sempre com default: `config('crud.x.y', $default)` — `mergeConfigFrom()`
  faz merge raso, então array publicado pelo usuário substitui o array inteiro.
- Nada de `env()` fora de `src/config/`.
- Nome de tabela vindo do usuário só entra em query depois de `Schema::hasTable()`.

## Verificação

```bash
vendor/bin/phpunit          # hoje só roda InstallCommandTest
composer validate
```

Ao mudar geração, o teste natural é rodar o comando, ler o arquivo gerado e asseverar
que contém o esperado **e que não sobrou nenhum `{{`**.
