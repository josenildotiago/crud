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
  TableInspection.php         # pré-voo: o que na tabela o código gerado não suporta
  NavigationRegion.php        # região marcada da navegação do usuário; molde do TableInspection
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
`buildOptions → buildController → buildModel → buildViews → buildRouter`.

Gera no app do usuário: Model, Controller (Inertia ou clássico), componentes React e
`routes/{model}.php` com `require` idempotente no `web.php`.

**O pacote não gera API.** A flag `--api` e os cinco stubs dela saíram na 4.0.0: nunca
funcionaram em release nenhuma — pressupunham um motor de templating handlebars que jamais
existiu, e 21 dos 29 placeholders deles não estavam no mapa de replacements. Não
ressuscitar sem decidir antes qual motor processa os stubs.

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

O item 5 é o que mais gera dúvida, então a régua é esta: **o critério é o que sobrevive à
regeração, não o que o pacote escreve.** Nome de rota, nome de método do Controller,
nome de componente e forma das props são API — o app do usuário referencia isso de fora do
arquivo gerado, e mudar quebra código que ele escreveu. **URL gerada também é**: `route()`
continua resolvendo, mas link, favorito, teste de feature e integração externa apontam para
a string. Foi por isso que a troca de `GET /{recurso}/index` por `GET /{recurso}` na 3.2.0
deveria ter sido major, e não minor.

Não é API o que existe só dentro do arquivo gerado: layout do JSX, ordem dos imports,
nomes de variável local, formatação. Também não são as classes internas do gerador
(`NavigationRegion`, `TableInspection`) — elas rodam durante o `php artisan` e nada no app
do usuário as referencia.

Agravante que pesa na decisão: `routes/{model}.php` e as páginas `.tsx` são sobrescritos
**sem perguntar**, então mudar a saída deles não é só "mudar a próxima geração" — é apagar
o que o usuário editou, na hora em que ele regera.

## Pendências conhecidas

Levantadas em 29/07/2026, ainda não corrigidas. Ao mexer perto de uma delas, corrigir
junto ou avisar — não fingir que não existe.

**Bugs**
- ~~`--stack` não é honrado.~~ Corrigido em 29/07/2026: `handle()` resolve
  `$this->template ??= $this->option('stack')` e valida contra `InstallCommand::STACKS`;
  `afterPromptingForMissingArguments()` pula o prompt quando `--stack` vem explícito na
  linha de comando. Coberto por `tests/Unit/InstallCommandStackTest.php`.
- ~~`debugColumns()` roda em toda geração, despejando JSON no terminal.~~ Resolvido: o
  método saiu, e o pré-voo da tabela (`src/TableInspection.php`, chamado no `handle()`
  antes da primeira escrita) ocupou o lugar com algo que serve — ver
  `docs/superpowers/specs/2026-07-29-preflight-tabela-design.md`.
- `InstallCommand::STACKS` é `['react', 'vue', 'blade']` — `svelte` e `livewire` são
  rejeitadas no `handle()` antes de qualquer geração.
- `TableInspection` nomeia só a **primeira** coluna `PRI` em chave composta, porque dá
  `break` na primeira. O aviso sai incompleto ("A chave primária é `permission_id`, não
  `id`"), embora a conclusão — o Model gerado não vai funcionar — continue válida. Casos
  reais no banco de referência: `model_has_permissions`, `model_has_roles`,
  `role_has_permissions`. Nenhum teste cobre chave composta.
- **A resposta interativa de `--theme` é descartada** — mesma raiz do bug do `--stack`.
  `afterPromptingForMissingArguments()` grava em `$this->options['theme']` (o array custom
  do `GeneratorCommand`, que só é lido para `route`), mas `buildReactComponents()` lê
  `$this->option('theme')` (a opção do Symfony). Responder "sim" no prompt não faz nada; só
  funciona passando `--theme` na linha de comando. Era o mesmo com `--api`, que saiu na
  4.0.0 — este é o último caso.
- Tag `crud-assets` publica `src/stubs/js` e `src/stubs/css` — nenhum dos dois existe.
- `$this->name = $this->_buildClassName()` sobrescreve o `$name` do `Illuminate\Console\Command`.
- **O Controller das stacks `vue` e `blade` redireciona para rota que não existe.**
  `InstallCommand.php:290` escolhe `Controller.stub` quando a stack não é `react`, e ele
  redireciona para `route('{{modelRouteNotPlural}}.index')` — nome no singular
  (`cliente.index`). Mas `buildRouter()` gera as rotas a partir de `ModelRoutes.stub`, que
  nomeia no plural (`clientes.index`). Store, update e destroy morrem com
  `RouteNotFoundException`. Com `--route=X` os dois placeholders viram `X` e funciona por
  acidente. A stack `react` não é afetada: `InertiaController.stub` usa `{{modelRoute}}`.
  Levantado em 29/07/2026, fora do escopo da 3.2.0 porque a stack `blade` não gera view
  nenhuma de qualquer forma.

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
  suíte válida. Desde 29/07/2026 o `phpunit.xml` carrega `./tests/Unit` inteiro com
  `<exclude>` explícito deste arquivo — antes ele passava batido por não estar na lista.
- ~~Versões divergentes entre `composer.json`, README e REPORT.md.~~ Resolvido em
  29/07/2026: o campo `version` saiu do composer.json (Packagist deriva da tag git), o
  README foi para 3.2.0, e o `REPORT.md` foi carimbado como documento histórico — é a
  análise de 24/08/2025 sobre a 2.1.3 e não descreve o pacote atual.
- ~~README manda `vendor:publish --tag="config"`.~~ Corrigido em 29/07/2026: a tag real,
  `crud-config`, está no README.
- `src/stubs/routes.stub` e `src/stubs/InertiaRoutes.stub` são arquivos mortos: o único
  stub de rota carregado é `ModelRoutes`. O `routes.stub` declara rotas num formato antigo
  (`{{modelNameLowerCase}}-index`) que nada gera mais; o `InertiaRoutes.stub` não é citado
  em lugar nenhum (`grep -rn InertiaRoutes src/` devolve zero).
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
vendor/bin/phpunit          # tests/Unit inteiro, menos CrudPackageTest.php
vendor/bin/phpstan analyse  # nível 5 sobre src/, tem que ficar em zero
composer validate
```

O PHPStan entrou porque `php -l` não vê método que nunca alcança o `return` — foi assim que
a 4.0.0 saiu com `getRouteImports()` sem retorno e nenhuma geração react funcionando. Ele
reporta isso como `return.missing`, desde o nível 0 e sem permitir supressão. O nível 5 é
o teto de graça: o 6 exige tipo nativo em ~68 pontos dos métodos antigos do
`GeneratorCommand`.

`.github/workflows/tests.yml` cobre a faixa que o `composer.json` promete: 8 jobs, PHP
8.2/8.3/8.4 × Testbench ^10 (Laravel 12) / ^11 (Laravel 13), mais três jobs
`--prefer-lowest`. Eles provam o piso do constraint só onde o pacote é a restrição
vinculante — vale para os `illuminate/*`, não vale para `symfony/console` e
`symfony/process`, cujo piso `^7.0` o framework já eleva antes de chegar em nós. Dois
limites que a matrix codifica e que não são óbvios:

- **Laravel 13 exige PHP `^8.3`** — a combinação PHP 8.2 + Laravel 13 não existe, e é por
  isso que o `php: >=8.2.0` do composer.json só é honesto via Laravel 12.
- **`symfony/console` 8.0 exige PHP `>=8.4`** — o braço `^8.0` do constraint só é
  exercitado nos jobs de PHP 8.4. Numa máquina com PHP 8.3 ele é inalcançável.

A suíte não toca banco, e é só por isso que o CI roda sem serviço de MySQL. Teste que
exercite geração de verdade precisa de MySQL (`SHOW COLUMNS`) e obriga a acrescentar um
`services:` ao workflow.

Ao mudar geração, o teste natural é rodar o comando, ler o arquivo gerado e asseverar
que contém o esperado **e que não sobrou nenhum `{{`**.
